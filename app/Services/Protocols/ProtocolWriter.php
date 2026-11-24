<?php

namespace App\Services\Protocols;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Services\OutgoingMail;
use App\Services\ProtocolNumberTaken;
use App\Services\ProtocolNumbering;
use App\Support\Assignments;
use App\Support\FolderRules;
use App\Support\DateInput;
use App\Support\FileNames;
use App\Support\TextInput;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * R1a: saving a protocol, in one place for every screen (legacy form now, the rebuilt form in R1b).
 *
 * Input: the form's fields (dates as typed: "9/4/25", "09/04/2025" …; assignments as ["d14", "e10"] or "d14,e10").
 * What it does, in order: cleans every text (TextInput), applies who may change what (ProtocolAccess: locked parts
 * keep their stored value whatever is posted), validates, takes the number (B03/B08 rules, ΕΛΕΥΘΕΡΟ placeholder),
 * saves the protocol and its assignments, stores new attachments (unstamped since R4f; size/hash), moves files when the folder
 * changes, links related numbers both ways, and sends the assignment / receipt e-mails.
 *
 * Errors the user can fix come back as a ValidationException with field keys, never as an SQL error.
 */
final class ProtocolWriter
{
    /** Thema of a protocol that only keeps a number free (created by hand); a manual number may replace it. */
    public const PLACEHOLDER = 'ΕΛΕΥΘΕΡΟ';

    public const SINGLE_LINE = ['fakelos', 'thema', 'in_num', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'out_to'];
    public const MULTI_LINE = ['in_perilipsi', 'out_perilipsi', 'paratiriseis'];
    public const LISTS = ['keywords', 'sxetiko'];
    public const DATES = ['protocoldate', 'in_date', 'out_date', 'diekp_eos', 'diekp_date'];

    /** Fields of each part of the form (see ProtocolAccess) */
    public const SECTIONS = [
        'head' => ['fakelos', 'thema', 'sxetiko', 'keywords', 'paratiriseis'],
        'in' => ['in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'in_perilipsi'],
        'out' => ['out_to', 'out_date', 'out_perilipsi'],
        'doneDate' => ['diekp_date'],
    ];

    /** VARCHAR(255) columns; the TEXT ones take Protocol::LONG_TEXT_MAX, paratiriseis (MEDIUMTEXT) more */
    private const SHORT = ['fakelos', 'in_num', 'in_topos_ekdosis', 'in_paraliptis', 'out_to', 'sxetiko', 'keywords'];
    private const LONG = ['thema', 'in_arxi_ekdosis', 'in_perilipsi', 'out_perilipsi'];

    public function __construct(
        private ProtocolNumbering $numbering,
        private AssignmentMail $assignmentMail,
        private OutgoingMail $outgoing,
    ) {}

    /**
     * @param list<array{file?: ?UploadedFile, ada?: ?string, content?: string, name?: string, mime?: string, stamp?: bool, dir?: string, prefix?: string}> $attachments
     *        a row is an uploaded file, a ΑΔΑ, or (R5c, e-mail registration) a file's content with its name and type
     * @param bool $allOrNothing R5c (e-mail registration, R5a rule): if a file cannot be stored, nothing is kept (files,
     *        attachment rows, the protocol itself) and AttachmentsNotSaved is thrown, so the e-mail can be registered
     *        again; otherwise a file that fails is a warning on the saved protocol
     * @throws ValidationException
     * @throws AttachmentsNotSaved
     */
    public function create(User $user, array $input, array $attachments = [], bool $allOrNothing = false): SaveResult
    {
        $access = ProtocolAccess::for($user, new Protocol());
        // readers may not register protocols (review of R1b: the form's save could be called directly; the old POST
        // route had the writer middleware)
        if (! $access->canSave) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Δεν επιτρέπεται η καταχώριση πρωτοκόλλου.');
        }
        $v = $this->clean($input);
        $record = $this->pick($v, $access, null);
        $assignments = $access->canAssign ? $v['assignments'] : [];
        $record['diekp_eos'] = $access->canAssign ? $v['diekp_eos'] : null;

        $etos = $access->number ? $v['etos'] : (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
        $record['protocoldate'] = $access->date ? $v['protocoldate'] : Carbon::today()->format(DateInput::FORMAT);

        $manual = $access->number && $this->numbering->isManual($input['protocolnum'] ?? null, $input['protocolnum_proposed'] ?? null);
        $posted = (int) ($input['protocolnum'] ?? 0);
        // R6d: only an administrator with "Έλεγχοι καταχώρισης" off chooses the number; for everyone else it is given
        // on save (was: with safeNewProtocolNum off, any posted number was taken, from any user)
        $chosen = $manual ? $posted : null;

        $raw = $this->rawNumberErrors($input, $access->number, $manual);
        $this->validate($v, $record, $assignments, $assignments, $access, null, $etos, $manual ? $posted : null, $raw, $attachments);
        // R1d: the folder's automatic assignments, whoever registers (after the checks: they are not the user's doing)
        [$assignments, $auto] = FolderRules::merge($assignments, FolderRules::for($record['fakelos'] ?? null));

        $data = $this->toColumns($record) + ['user_id' => $user->id, 'etos' => $etos];
        $replaced = false;
        try {
            if ($chosen === null) {
                $protocol = $this->numbering->create($etos, null, fn (int $num) => $this->insert($data + ['protocolnum' => $num], $assignments));
            } else {
                $protocol = $this->numbering->locked(function () use ($etos, $chosen, $data, $assignments, $v, &$replaced) {
                    $holder = Protocol::where('etos', $etos)->where('protocolnum', $chosen)->lockForUpdate()->first();
                    if (! $holder) {
                        return $this->insert($data + ['protocolnum' => $chosen], $assignments);
                    }
                    $this->assertReplaceable($holder, $v['replace_placeholder']);
                    // the placeholder becomes the new protocol: same row, number and year; everything else replaced
                    $holder->forceFill($data);
                    $holder->diekperaiosi = Assignments::asText($assignments);
                    $holder->created_at = Carbon::now();
                    $holder->save();
                    $replaced = true;
                    return $holder;
                });
            }
        } catch (ProtocolNumberTaken|\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['protocolnum' => $this->takenMessage($chosen ?? 0, $etos)]);
        }

        $result = new SaveResult($protocol->fresh(), $replaced);
        if ($replaced) {
            $result->messages[] = "Αντικαταστάθηκε το «" . self::PLACEHOLDER . "» πρωτόκολλο {$protocol->protocolnum}/{$protocol->etos}.";
        }
        if ($auto) {
            $result->messages[] = FolderRules::describe((string) $record['fakelos'], $auto);
        }
        if ($allOrNothing) {
            $written = [];
            try {
                $this->storeAttachments($result, $attachments, $v, $written);
            } catch (\Throwable $e) {
                report($e);
                $this->undoCreate($result->protocol, $written);
                throw new AttachmentsNotSaved(previous: $e);
            }
            $attachments = [];
        }
        // the protocol is saved: from here on a failure is a warning on the saved protocol, never a reason to register
        // it again (review of R1a: a retry created a second protocol)
        $this->afterCommit($result, fn () => $this->afterSave($result, $v, [], $assignments, $attachments, $user));
        $this->afterCommit($result, fn () => $this->sendReceipt($result, $v['reply_to_email'], $v['email_date']));

        return $result;
    }

    private function afterCommit(SaveResult $result, callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            report($e);
            $result->warnings[] = 'Το πρωτόκολλο αποθηκεύτηκε, αλλά κάτι από τα υπόλοιπα (συνημμένα, email) απέτυχε: ελέγξτε το και επαναλάβετε μόνο ό,τι λείπει.';
        }
    }

    /**
     * Number and year as typed, before they become integers (review of R1a: "12.5" or "2026x" were cast to 12 / 2026).
     * @return array<string, string>
     */
    private function rawNumberErrors(array $input, bool $mayChoose, bool $numberChosen): array
    {
        $errors = [];
        if ($numberChosen && ! preg_match('/^\d+$/', trim((string) ($input['protocolnum'] ?? '')))) {
            $errors['protocolnum'] = 'Ο Αρ. Πρωτοκόλλου πρέπει να είναι θετικός ακέραιος.';
        }
        if ($mayChoose && ! preg_match('/^\d{4}$/', trim((string) ($input['etos'] ?? '')))) {
            $errors['etos'] = 'Το έτος πρέπει να είναι τετραψήφιος αριθμός.';
        }
        return $errors;
    }

    /**
     * @param list<array{file?: ?UploadedFile, ada?: ?string}> $attachments
     * @throws ValidationException
     * @throws \Illuminate\Auth\Access\AuthorizationException when the user may not change this protocol at all
     */
    public function update(User $user, Protocol $protocol, array $input, array $attachments = []): SaveResult
    {
        $access = ProtocolAccess::for($user, $protocol);
        if (! $access->canSave || \Illuminate\Support\Facades\Gate::forUser($user)->denies('update', $protocol)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Δεν επιτρέπεται η αλλαγή αυτού του πρωτοκόλλου.');
        }
        $v = $this->clean($input);
        $record = $this->pick($v, $access, $protocol);
        $before = $protocol->assignments->map(fn ($a) => ['user_id' => (int) $a->user_id, 'type' => $a->type])->all();
        // only administrators and assigners change assignments and the deadline (the form disables both for the
        // others, so nothing was posted and the old update emptied them: review of R1a)
        $assignments = $access->canAssign ? $v['assignments'] : $before;
        $record['diekp_eos'] = $access->canAssign ? $v['diekp_eos'] : $this->stored($protocol, 'diekp_eos');
        $record['protocoldate'] = $access->date ? $v['protocoldate'] : $this->stored($protocol, 'protocoldate');
        $etos = $access->number ? $v['etos'] : (int) $protocol->etos;
        $num = $access->number ? (int) ($input['protocolnum'] ?? 0) : (int) $protocol->protocolnum;

        $key = fn ($a) => $a['type'] . $a['user_id'];
        $added = array_values(array_filter($assignments, fn ($a) => ! in_array($key($a), array_map($key, $before), true)));
        $raw = $this->rawNumberErrors($input, $access->number, $access->number);
        $this->validate($v, $record, $assignments, $added, $access, $protocol, $etos, $access->number ? $num : null, $raw, $attachments);
        if ($protocol->attachments()->exists() && ! $record['fakelos']) {
            throw ValidationException::withMessages(['fakelos' => 'Ο Φάκελος πρωτοκόλλου με συνημμένα αρχεία δεν μπορεί να είναι κενός. Για να γίνει αυτό, διαγράψτε πρώτα τα συνημμένα.']);
        }

        $oldFolder = (string) $protocol->fakelos;
        // R1d: moved into a folder with automatic assignments: its users are added (the same folder again adds nothing,
        // so a user an assigner removed by hand stays removed)
        $auto = [];
        if (trim((string) ($record['fakelos'] ?? '')) !== trim($oldFolder)) {
            [$assignments, $auto] = FolderRules::merge($assignments, FolderRules::for($record['fakelos'] ?? null));
        }
        $data = $this->toColumns($record) + ['etos' => $etos, 'protocolnum' => $num, 'user_id' => $user->id];
        try {
            $this->numbering->locked(function () use ($protocol, $data, $assignments, $etos, $num) {
                if (Protocol::where('etos', $etos)->where('protocolnum', $num)->where('id', '!=', $protocol->id)->exists()) {
                    throw new ProtocolNumberTaken($num, $etos);
                }
                $protocol->forceFill($data);
                $protocol->diekperaiosi = Assignments::asText($assignments);
                $protocol->save();
            });
        } catch (ProtocolNumberTaken|\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['protocolnum' => $this->takenMessage($num, $etos)]);
        }

        $result = new SaveResult($protocol->fresh());
        if ($auto) {
            $result->messages[] = FolderRules::describe((string) $record['fakelos'], $auto);
        }
        if ($oldFolder !== (string) $result->protocol->fakelos) {
            $this->afterCommit($result, fn () => $this->moveFiles($result, $oldFolder));
        }
        $this->afterCommit($result, fn () => $this->afterSave($result, $v, $before, $assignments, $attachments, $user));

        return $result;
    }

    /**
     * R1b: "Διεκπεραιώθηκε": the done date, by anyone the policy allows (also a reader assigned for διεκπεραίωση,
     * who can't save the form). Noted in Παρατηρήσεις, as the legacy button did.
     */
    public function markDone(User $user, Protocol $protocol, string $date): void
    {
        \Illuminate\Support\Facades\Gate::forUser($user)->authorize('complete', $protocol);
        $day = DateInput::expand($date);
        if (! $day) {
            throw ValidationException::withMessages(['diekp_date' => 'Η ημερομηνία δεν είναι έγκυρη (ηη/μμ/εεεε).']);
        }
        $note = Carbon::now()->format('d/m/Y H:i:s') . ' διεκπεραιώθηκε από ' . $user->name;
        $protocol->forceFill([
            'user_id' => $user->id,
            'diekp_date' => CarbonImmutable::createFromFormat('!' . DateInput::FORMAT, $day)->format('Y-m-d'),
            'paratiriseis' => ($protocol->paratiriseis ? $protocol->paratiriseis . ', ' : '') . $note,
        ])->save();
    }

    /**
     * R1b: change only the assignments and the deadline ("Ανάθεση"), for administrators and assigners also when the
     * rest of the protocol is locked for them. The done date is cleared, as the legacy button did.
     *
     * @param list<string>|string $assignments  ["d14", "e10"] or "d14,e10"
     */
    public function assign(User $user, Protocol $protocol, array|string $assignments, ?string $deadline): SaveResult
    {
        \Illuminate\Support\Facades\Gate::forUser($user)->authorize('assign', $protocol);
        $rows = Assignments::parse(is_array($assignments) ? implode(',', $assignments) : $assignments);
        if (! $rows) {
            throw ValidationException::withMessages(['diekperaiosi' => 'Επιλέξτε σε ποιον ανατίθεται.']);
        }
        if (User::whereIn('id', array_column($rows, 'user_id'))->count() < count(array_unique(array_column($rows, 'user_id')))) {
            throw ValidationException::withMessages(['diekperaiosi' => 'Άγνωστος χρήστης στην ανάθεση.']);
        }
        $raw = TextInput::clean($deadline);
        $day = $raw === null ? null : DateInput::expand($raw);
        if ($raw !== null && ! $day) {
            throw ValidationException::withMessages(['diekp_eos' => 'Η ημερομηνία δεν είναι έγκυρη (ηη/μμ/εεεε).']);
        }
        $before = $protocol->assignments->map(fn ($a) => ['user_id' => (int) $a->user_id, 'type' => $a->type])->all();
        $protocol->forceFill([
            'diekp_eos' => $day ? CarbonImmutable::createFromFormat('!' . DateInput::FORMAT, $day)->format('Y-m-d') : null,
            'diekp_date' => null,
        ]);
        $protocol->diekperaiosi = Assignments::asText($rows);
        $protocol->save();

        // the explicit "Ανάθεση" always notifies the users it adds (legacy button), whatever the save setting
        $result = new SaveResult($protocol->fresh());
        $old = array_map(fn ($a) => $a['type'] . $a['user_id'], $before);
        foreach ($rows as $a) {
            if (! in_array($a['type'] . $a['user_id'], $old, true) && ($line = $this->assignmentMail->send($result->protocol, $a['user_id'], $a['type'], $user))) {
                $result->messages[] = $line;
            }
        }
        return $result;
    }

    /**
     * R1b: delete a protocol (administrators; the form offers it with "Έλεγχοι καταχώρισης" OFF). R4h: to the trash
     * (ProtocolTrash): its number is free again, it can be restored; its attachments go to the trash with it (R4b).
     */
    public function delete(User $user, Protocol $protocol): \App\Models\TrashedProtocol
    {
        \Illuminate\Support\Facades\Gate::forUser($user)->authorize('delete', $protocol);
        return app(ProtocolTrash::class)->trash($user, $protocol);
    }

    /** R1b/R4b: an attachment to the trash (restorable for the retention days, see Attachments\Trash). */
    public function removeAttachment(User $user, Attachment $attachment): void
    {
        $protocol = $attachment->protocol;
        if (! $protocol || ! ProtocolAccess::for($user, $protocol)->canSave || \Illuminate\Support\Facades\Gate::forUser($user)->denies('update', $protocol)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Δεν επιτρέπεται η διαγραφή αυτού του συνημμένου.');
        }
        app(\App\Services\Attachments\Trash::class)->trash($attachment, $user);
    }

    /**
     * R4d: rename an attachment. Only the name shown (attachments.name) changes; the file keeps its path. The extension
     * stays the one the attachment has (typed again, it is not doubled); characters not allowed in file names are
     * replaced as on upload (FileNames::clean). Same rule as removing one: whoever may save the protocol.
     *
     * @return string the new name
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \InvalidArgumentException when nothing usable is typed
     */
    public function renameAttachment(User $user, Attachment $attachment, string $typed): string
    {
        $protocol = $attachment->protocol;
        if (! $protocol || ! ProtocolAccess::for($user, $protocol)->canSave || \Illuminate\Support\Facades\Gate::forUser($user)->denies('update', $protocol)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Δεν επιτρέπεται η μετονομασία αυτού του συνημμένου.');
        }
        if ($attachment->trashed() || (string) $attachment->savedPath === '') {
            throw new \InvalidArgumentException('Μόνο συνημμένα με αρχείο μετονομάζονται.');
        }
        $extension = self::extensionOf($attachment);
        $base = trim(TextInput::clean($typed) ?? '');
        if ($extension !== '' && mb_strtolower(mb_substr($base, -mb_strlen($extension) - 1)) === '.' . mb_strtolower($extension)) {
            $base = mb_substr($base, 0, -mb_strlen($extension) - 1);   // typed with its extension
        }
        $base = rtrim($base, ' .');
        if (trim($base, ' .-') === '') {
            throw new \InvalidArgumentException('Γράψτε το νέο όνομα.');
        }
        // cleaned with a dummy extension, so a dot in the base is never read as the extension
        $clean = \App\Support\FileNames::clean($base . '.' . ($extension !== '' ? $extension : 'x'));
        $name = $extension !== '' ? $clean : substr($clean, 0, -2);
        if ($name !== $attachment->name) {
            $attachment->name = $name;
            $attachment->save();
        }
        return $name;
    }

    /** R4d: the extension a renamed attachment keeps: its name's, or else its stored file's */
    public static function extensionOf(Attachment $attachment): string
    {
        $extension = pathinfo((string) $attachment->name, PATHINFO_EXTENSION);
        return $extension !== '' ? $extension : pathinfo((string) $attachment->savedPath, PATHINFO_EXTENSION);
    }

    public static function isPlaceholder(Protocol $p): bool
    {
        $t = mb_strtoupper(trim((string) $p->thema, " \t\n.!-*"));
        return strtr($t, ['Έ' => 'Ε']) === self::PLACEHOLDER;
    }

    /**
     * Cleaned values of everything the form may send: texts (TextInput), dates as dd/mm/yyyy (or the marker
     * INVALID), assignments as rows.
     */
    public function clean(array $input): array
    {
        $v = [];
        foreach (self::SINGLE_LINE as $f) {
            $v[$f] = TextInput::clean($input[$f] ?? null);
        }
        foreach (self::MULTI_LINE as $f) {
            $v[$f] = TextInput::clean($input[$f] ?? null, multiline: true);
        }
        foreach (self::LISTS as $f) {
            $v[$f] = TextInput::list($input[$f] ?? null);
        }
        foreach (self::DATES as $f) {
            $raw = TextInput::clean($input[$f] ?? null);
            $v[$f] = $raw === null ? null : (DateInput::expand($raw) ?? self::INVALID);
        }
        $a = $input['diekperaiosi'] ?? [];
        $v['assignments'] = Assignments::parse(is_array($a) ? implode(',', $a) : (string) $a);
        $v['etos'] = (int) ($input['etos'] ?? 0);
        $v['keep'] = TextInput::clean($input['keep'] ?? null);
        $v['reply_to_email'] = TextInput::clean($input['reply_to_email'] ?? null);
        // R5c: registering an e-mail: its date ("the message you sent on …") and the e-mail form of the receipt
        $v['email_date'] = TextInput::clean($input['email_date'] ?? null);
        $v['replace_placeholder'] = filter_var($input['replace_placeholder'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $v;
    }

    public const INVALID = "\0invalid";

    /** The values to store for the parts the user may change; the stored ones for the rest. */
    private function pick(array $v, ProtocolAccess $access, ?Protocol $stored): array
    {
        $record = [];
        foreach (self::SECTIONS as $section => $fields) {
            foreach ($fields as $f) {
                $record[$f] = ($stored === null || $access->{$section}) ? $v[$f] : $this->stored($stored, $f);
            }
        }
        return $record;
    }

    /** A stored value in the form's shape (dates dd/mm/yyyy) */
    private function stored(Protocol $p, string $field): ?string
    {
        if (in_array($field, self::DATES, true)) {
            return $p->dateOf($field)?->format(DateInput::FORMAT);
        }
        $value = $p->getAttribute($field);
        return $value === null ? null : (string) $value;
    }

    /** @param int|null $num the number the user chose (null: given by the application) */
    /**
     * @param list<array{user_id:int, type:string}> $newAssignments  the ones this save adds: only they must be
     *        existing users (an old assignment to a user deleted since is kept, D02; review of R1a)
     * @param array<string, string> $errors  found before (number/year as typed)
     */
    private function validate(array $v, array $record, array $assignments, array $newAssignments, ProtocolAccess $access, ?Protocol $protocol, int $etos, ?int $num, array $errors = [], array $attachments = []): void
    {
        foreach (self::DATES as $f) {
            if (($record[$f] ?? null) === self::INVALID) {
                $errors[$f] = 'Η ημερομηνία δεν είναι έγκυρη (ηη/μμ/εεεε).';
            }
        }
        if (! $record['protocoldate']) {
            $errors['protocoldate'] = 'Συμπληρώστε την ημερομηνία πρωτοκόλλου.';
        }
        if ($access->number && ($etos < 1000 || $etos > 9999) && ! isset($errors['etos'])) {
            $errors['etos'] = 'Το έτος πρέπει να είναι τετραψήφιος αριθμός.';
        }
        if ($num !== null && $num < 1 && ! isset($errors['protocolnum'])) {
            $errors['protocolnum'] = 'Ο Αρ. Πρωτοκόλλου πρέπει να είναι θετικός ακέραιος.';
        }
        foreach (self::SHORT as $f) {
            if (mb_strlen((string) $record[$f]) > 255) {
                $errors[$f] = 'Έως 255 χαρακτήρες.';
            }
        }
        foreach (self::LONG as $f) {
            if (mb_strlen((string) $record[$f]) > Protocol::LONG_TEXT_MAX) {
                $errors[$f] = 'Έως ' . Protocol::LONG_TEXT_MAX . ' χαρακτήρες.';
            }
        }
        if (mb_strlen((string) $record['paratiriseis']) > 1_000_000) {
            $errors['paratiriseis'] = 'Πολύ μεγάλο κείμενο.';
        }
        // the folder becomes part of the attachments' path (arxeio/<folder>/…): no way out of it
        if ($record['fakelos'] !== null && preg_match('~(^|/)\.\.(/|$)|^/|\\\\|\x00~', $record['fakelos'])) {
            $errors['fakelos'] = 'Μη έγκυρος φάκελος.';
        }
        if ($v['reply_to_email'] !== null && ! filter_var($v['reply_to_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['reply_to_email'] = 'Μη έγκυρη διεύθυνση email.';
        }
        $refused = [];
        foreach ($attachments as $row) {
            if (mb_strlen((string) TextInput::clean($row['ada'] ?? null)) > 255) {
                $errors['ada'] = 'Ο ΑΔΑ είναι έως 255 χαρακτήρες.';
            }
            // R4a: size and type of every new file, whichever screen sent it
            if (($row['file'] ?? null) instanceof UploadedFile && ($why = \App\Support\SafeFile::refuse($row['file']))) {
                $refused[] = $why;
            }
        }
        if ($refused) {
            $errors['uploads'] = implode(' ', $refused);
        }
        if ($newAssignments && User::whereIn('id', array_column($newAssignments, 'user_id'))->count() < count(array_unique(array_column($newAssignments, 'user_id')))) {
            $errors['diekperaiosi'] = 'Άγνωστος χρήστης στην ανάθεση.';
        }

        if (Config::getConfigValueOf('protocolValidate')) {
            $currentYear = (int) Carbon::now()->year;
            if ($protocol === null && $etos !== $currentYear) {
                $when = $etos < $currentYear ? 'παρελθόν' : 'μέλλον';
                $errors['etos'] = "Δεν μπορείτε να καταχωρίσετε Νέο Πρωτόκολλο στο $when έτος $etos. Αλλάξτε στις ρυθμίσεις το «Ενεργό έτος πρωτοκόλλησης» σε $currentYear (αρίθμηση από το 1) ή κενό (επόμενος αριθμός).";
            }
            $filled = fn (string $f) => ($record[$f] ?? null) !== null && $record[$f] !== '';
            $others = array_diff(array_merge(...array_values(self::SECTIONS)), ['thema']);
            $anything = $assignments || collect($others)->contains($filled);
            if (! $filled('thema') && ($anything || ! Config::getConfigValueOf('allowEmptyProtocol'))) {
                $errors['thema'] = 'Συμπληρώστε το θέμα.';
            }
            $in = ['in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis'];
            $need = [
                'in_date' => [['in_num', 'in_topos_ekdosis', 'in_arxi_ekdosis'], 'Συμπληρώστε την ημερομηνία έκδοσης.'],
                'in_topos_ekdosis' => [['in_num', 'in_date', 'in_arxi_ekdosis'], 'Συμπληρώστε τον τόπο έκδοσης.'],
                'in_arxi_ekdosis' => [['in_num', 'in_date', 'in_topos_ekdosis'], 'Συμπληρώστε την αρχή έκδοσης.'],
                'in_paraliptis' => [$in, 'Συμπληρώστε τον παραλήπτη.'],
                'out_date' => [['out_to', 'out_perilipsi'], 'Συμπληρώστε την ημερομηνία εξερχομένου.'],
                'out_to' => [['out_date', 'out_perilipsi'], 'Συμπληρώστε σε ποιον απευθύνεται.'],
            ];
            if ($v['email_date'] !== null) {
                unset($need['in_topos_ekdosis']);   // an e-mail has no place of issue (the old inbox did not ask for it)
            }
            foreach ($need as $f => [$with, $message]) {
                if (! $filled($f) && collect($with)->contains($filled)) {
                    $errors[$f] ??= $message;
                }
            }
            $handled = collect($assignments)->contains(fn ($a) => $a['type'] === Assignments::HANDLE);
            if ($handled && ! $filled('diekp_date') && collect(['out_to', 'out_date', 'out_perilipsi'])->contains($filled)) {
                $errors['diekp_date'] ??= 'Συμπληρώστε την ημερομηνία διεκπεραίωσης.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Form values (dates dd/mm/yyyy) → column values (dates Y-m-d). */
    private function toColumns(array $record): array
    {
        $out = [];
        foreach ($record as $f => $value) {
            $out[$f] = in_array($f, self::DATES, true) && $value !== null
                ? CarbonImmutable::createFromFormat('!' . DateInput::FORMAT, $value)->format('Y-m-d')
                : $value;
        }
        return $out;
    }

    private function insert(array $data, array $assignments): Protocol
    {
        $protocol = new Protocol();
        $protocol->forceFill($data);
        $protocol->diekperaiosi = Assignments::asText($assignments);
        $protocol->save();
        return $protocol;
    }

    private function assertReplaceable(Protocol $holder, bool $confirmed): void
    {
        $label = "{$holder->protocolnum}/{$holder->etos}";
        if (! self::isPlaceholder($holder)) {
            throw ValidationException::withMessages(['protocolnum' => $this->takenMessage($holder->protocolnum, $holder->etos)]);
        }
        if (Attachment::withTrashed()->where('protocol_id', $holder->id)->exists()) {
            throw ValidationException::withMessages(['protocolnum' => "Ο Αρ. Πρωτοκόλλου $label είναι «" . self::PLACEHOLDER . "» αλλά έχει συνημμένα: δεν αντικαθίσταται αυτόματα."]);
        }
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'protocolnum' => "Ο Αρ. Πρωτοκόλλου $label είναι κρατημένος ως «" . self::PLACEHOLDER . "».",
                'replace_placeholder' => 'Επιβεβαιώστε ότι το νέο πρωτόκολλο παίρνει τη θέση του.',
            ]);
        }
    }

    private function takenMessage(int $num, int $etos): string
    {
        return "Ο Αρ. Πρωτοκόλλου $num υπάρχει ήδη για το έτος $etos. Επιλέξτε άλλον αριθμό.";
    }

    /** Attachments, related numbers both ways, assignment e-mails. */
    private function afterSave(SaveResult $result, array $v, array $before, array $after, array $attachments, User $by): void
    {
        $protocol = $result->protocol;
        $this->storeAttachments($result, $attachments, $v);

        if (Config::getConfigValueOf('saveCycleSxetiko') && $protocol->sxetiko) {
            $this->linkRelated($protocol);
        }

        if (Config::getConfigValueOf('sendEmailOnDiekperaiosiChange')) {
            $key = fn ($a) => $a['type'] . $a['user_id'];
            $old = array_map($key, $before);
            foreach ($after as $a) {
                if (! in_array($key($a), $old, true) && ($line = $this->assignmentMail->send($protocol, $a['user_id'], $a['type'], $by))) {
                    $result->messages[] = $line;
                }
            }
        }
    }

    /**
     * @param list<array{file?: ?UploadedFile, ada?: ?string, content?: string, name?: string, mime?: string, stamp?: bool, dir?: string, prefix?: string}> $attachments
     * @param list<string>|null $written strict mode (all or nothing): the paths stored so far; a file that fails throws
     */
    private function storeAttachments(SaveResult $result, array $attachments, array $v, ?array &$written = null): void
    {
        $protocol = $result->protocol;
        $strict = $written !== null;
        $date = $protocol->dateOf('protocoldate');
        // R6d: unless "Ελεύθερη επιλογή χρόνου διατήρησης" is on, the retention is the folder's, whatever was sent
        $keep = Config::getConfigValueOf('allowUserChangeKeepSelect') ? $v['keep'] : (Keepvalue::retentionOf($protocol->fakelos) ?: null);
        $expires = ($keep !== null && is_numeric($keep) && $date) ? $date->addYears((int) $keep)->format('Y-m-d') : null;

        foreach ($attachments as $row) {
            $file = $row['file'] ?? null;
            $content = $row['content'] ?? null;
            $ada = TextInput::clean($row['ada'] ?? null);
            if (! $file && $content === null && $ada === null) {
                continue;
            }
            // "dir": where the file goes when the protocol has no folder (the e-mail's own copy: arxeio/emails)
            $dir = $protocol->fakelos === null && isset($row['dir']) ? $row['dir'] : self::folderPath($protocol->fakelos);
            $name = $mime = $path = null;
            if ($file || $content !== null) {
                $name = $file
                    ? FileNames::clean($file->getClientOriginalName(), $file->getClientMimeType())
                    : FileNames::clean((string) ($row['name'] ?? ''), $row['mime'] ?? null);
                $mime = $file ? $file->getMimeType() : ($row['mime'] ?? null);
                $storeAs = FileNames::fit($protocol->protocolnum . '-' . $date?->format('Ymd') . '_' . ($row['prefix'] ?? ''), $name, $dir);
                try {
                    $path = $file
                        ? ($file->isValid() ? $file->storeAs($dir, $storeAs) : false)
                        : (Storage::put("$dir/$storeAs", $content) ? "$dir/$storeAs" : false);
                } catch (\Throwable $e) {
                    if ($strict) {
                        throw $e;
                    }
                    report($e);
                    $path = false;
                }
                if (! $path) {
                    if ($strict) {
                        throw new \RuntimeException("file not written: $dir/$storeAs");
                    }
                    // before R1a the row was saved anyway, with an empty path, and the save reported success
                    $result->warnings[] = "Το αρχείο «{$name}» δεν αποθηκεύτηκε. Επισυνάψτε το ξανά.";
                    continue;
                }
                if ($strict) {
                    $written[] = $path;
                }
            }
            Attachment::updateOrCreate(
                ['protocol_id' => $protocol->id, 'ada' => $ada, 'name' => $name, 'mimeType' => $mime, 'savedPath' => $path],
                // R4f review: a file uploaded again over an old one is stored unstamped, whatever the old one carried
                ['keep' => $keep, 'expires' => $expires] + ($path ? ['stamp_in_file' => false] : []),
            );
            $result->attachmentsAdded++;
        }
    }

    /** R5c: remove a protocol whose files could not all be stored: its files, attachment rows, assignments, the row. */
    private function undoCreate(Protocol $protocol, array $written): void
    {
        try {
            foreach ($written as $path) {
                Storage::delete($path);
            }
            // Attachment has SoftDeletes: delete for good, or "ghosts" stay in the trash
            Attachment::withTrashed()->where('protocol_id', $protocol->id)->forceDelete();
            $protocol->assignments()->delete();
            $protocol->delete();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** arxeio/<folder> (arxeio/ when there is no folder), as the legacy code built it */
    public static function folderPath(?string $folder): string
    {
        return rtrim('arxeio/' . $folder, '/');
    }

    /** The folder changed: move the files that are directly in the old folder's directory. */
    private function moveFiles(SaveResult $result, string $oldFolder): void
    {
        $from = self::folderPath($oldFolder) . '/';
        $to = self::folderPath($result->protocol->fakelos) . '/';
        foreach ($result->protocol->attachments as $attachment) {
            $path = (string) $attachment->savedPath;
            if (! str_starts_with($path, $from) || str_contains(substr($path, strlen($from)), '/')) {
                continue;
            }
            $newPath = $to . substr($path, strlen($from));
            if (Storage::exists($path)) {
                if (Storage::exists($newPath)) {
                    $result->warnings[] = "Το αρχείο «{$attachment->name}» έμεινε στον παλιό φάκελο: υπάρχει ήδη αρχείο με το ίδιο όνομα στον νέο.";
                    continue;
                }
                Storage::move($path, $newPath);
            }
            $attachment->savedPath = $newPath;
            $attachment->save();
        }
    }

    /** Setting saveCycleSxetiko: each related protocol "N/YYYY" also lists this one (and the others). Legacy rule. */
    private function linkRelated(Protocol $protocol): void
    {
        $self = "{$protocol->protocolnum}/{$protocol->etos}";
        $related = array_map('trim', explode(',', (string) $protocol->sxetiko));
        foreach ($related as $ref) {
            if (! preg_match('~^(\d+)/(\d{4})$~', $ref, $m)) {
                continue;
            }
            $other = Protocol::where('protocolnum', (int) $m[1])->where('etos', (int) $m[2])->first();
            if (! $other || $other->id === $protocol->id) {
                continue;
            }
            $old = $other->sxetiko ? array_map('trim', explode(',', $other->sxetiko)) : [];
            $new = array_values(array_unique(array_merge($old, array_diff($related, [$ref]), [$self])));
            $text = implode(', ', array_filter($new, fn ($s) => $s !== ''));
            if (mb_strlen($text) <= 255 && $text !== $other->sxetiko) {
                $other->sxetiko = $text;
                $other->save();
            }
        }
    }

    /**
     * R1b: send (or send again) the receipt of an existing protocol by e-mail, as the old form's button did. Users who
     * may see the protocol, except readers (the old route had the writer middleware).
     */
    public function emailReceipt(User $user, Protocol $protocol, ?string $to): SaveResult
    {
        if (\App\Policies\ProtocolPolicy::role($user) === \App\Policies\ProtocolPolicy::READER || \Illuminate\Support\Facades\Gate::forUser($user)->denies('view', $protocol)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Δεν επιτρέπεται η αποστολή απόδειξης.');
        }
        $to = TextInput::clean($to);
        if ($to === null || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['receipt_email' => 'Μη έγκυρη διεύθυνση email.']);
        }
        $result = new SaveResult($protocol);
        $this->sendReceipt($result, $to);
        return $result;
    }

    /** "Απόδειξη παραλαβής" to the sender's e-mail, noted in Παρατηρήσεις (legacy rule). */
    private function sendReceipt(SaveResult $result, ?string $to, ?string $emaildate = null): void
    {
        if ($to === null) {
            return;
        }
        $protocol = $result->protocol;
        $protocoldate = $protocol->dateOf('protocoldate')?->format('d/m/Y');
        // an e-mail registered from the inbox gets the e-mail form of the receipt (as before R5c)
        $html = view($emaildate === null ? 'receiptProtocol' : 'receiptEmail', compact('protocol', 'protocoldate', 'emaildate'))->render();
        $subject = $emaildate === null ? 'Καταχώριση στο Ηλεκτρονικό Πρωτόκολλο.' : 'Καταχώριση email στο Ηλεκτρονικό Πρωτόκολλο.';
        if ($this->outgoing->sendHtml($to, $subject, $html)) {
            $note = Carbon::now()->format('d/m/Y H:i:s') . " απόδειξη παραλαβής σε $to";
            $protocol->paratiriseis = ($protocol->paratiriseis ? $protocol->paratiriseis . ', ' : '') . $note;
            $protocol->save();
            $result->messages[] = $note;
        } else {
            $result->warnings[] = "Μη δυνατή αποστολή βεβαίωσης καταχώρισης με email σε $to.";
        }
    }
}
