<?php

namespace App\Livewire;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\ProtocolNumbering;
use App\Services\Protocols\ProtocolAccess;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Protocols\SaveResult;
use App\Support\DateInput;
use App\Support\UserPreferences;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * R1b: the protocol form (new, edit, copy as new) on the rebuilt layout. Everything it saves goes through
 * ProtocolWriter (R1a), which applies the same rules again: this screen only shows them.
 *
 * Owner's requests built in: the number shows at once whether it is free or "Δεσμευμένος"; TAB from the number goes
 * to the date; date fields with ◀ ▶, "Σήμερα" and a calendar; an existing subject or incoming number is shown as a
 * notice next to the field instead of the old YES/NO dialog; actions as pills.
 */
class ProtocolForm extends Component
{
    use WithFileUploads;

    public const FIELDS = ['etos', 'protocolnum', 'protocoldate', 'fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis',
        'in_arxi_ekdosis', 'in_paraliptis', 'in_perilipsi', 'diekp_eos', 'diekp_date', 'sxetiko', 'out_to', 'out_date',
        'out_perilipsi', 'keywords', 'paratiriseis', 'keep', 'reply_to_email'];

    /** Locked: the browser can't point the form at another protocol (it would show it without the access check) */
    #[Locked]
    public ?int $protocolId = null;
    #[Locked]
    public ?int $copyOf = null;

    /** @var array<string, string> the form's fields, as typed */
    public array $form = [];

    /** @var list<string> user ids for διεκπεραίωση / ενημέρωση */
    public array $handle = [];
    public array $inform = [];

    public bool $replacePlaceholder = false;
    /** R1g: the user changed the number or year (or came from a missing-number row): only then "✔ Ελεύθερος" is said. */
    public bool $numberTouched = false;
    public bool $noStamp = false;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> new files, saved with the protocol */
    public $uploads = [];
    /** @var array<int, string> ΑΔΑ of each new file (same index as $uploads) */
    public array $uploadAdas = [];
    /** R1c: files just chosen or dropped on the window; added to $uploads (choosing again never drops earlier ones) */
    public $picked = [];
    public string $newAda = '';
    /** R1b: receipt by e-mail for a protocol already saved */
    public string $receiptEmail = '';

    public function mount(?Protocol $protocol = null, bool $copy = false, ?int $prefillNum = null): void
    {
        $protocol ??= new Protocol();
        if ($protocol->exists) {
            abort_if(Auth::user()->cannot('view', $protocol), 404);
        }
        $this->form = array_fill_keys(self::FIELDS, '');
        foreach (self::FIELDS as $f) {
            if (in_array($f, ProtocolWriter::DATES, true)) {
                $this->form[$f] = (string) $protocol->dateOf($f)?->format(DateInput::FORMAT);
            } elseif ($protocol->exists && $protocol->getAttribute($f) !== null && ! in_array($f, ['keep', 'reply_to_email'], true)) {
                $this->form[$f] = (string) $protocol->getAttribute($f);
            }
        }
        foreach ($protocol->exists ? $protocol->assignments : [] as $a) {
            $a->type === 'd' ? $this->handle[] = (string) $a->user_id : $this->inform[] = (string) $a->user_id;
        }
        $this->form['keep'] = (string) ($protocol->exists ? $protocol->attachments()->value('keep') : '') ?: $this->keepFor($this->form['fakelos']);

        if ($protocol->exists && ! $copy) {
            $this->protocolId = $protocol->id;
            return;
        }
        // new, or a copy: number and year are given on save; the copy keeps its date when the user may set it (R1a)
        $access = ProtocolAccess::for(Auth::user(), new Protocol());
        $this->copyOf = $copy && $protocol->exists ? $protocol->id : null;
        $this->form['etos'] = (string) $this->activeYear();
        $this->form['protocolnum'] = $prefillNum && $access->number ? (string) $prefillNum : '';
        $this->numberTouched = $this->form['protocolnum'] !== '';
        if (! $this->copyOf || ! $access->date || $this->form['protocoldate'] === '') {
            $this->form['protocoldate'] = Carbon::today()->format(DateInput::FORMAT);
        }
        $this->form['diekp_date'] = '';
        if (! $access->canAssign) {
            $this->handle = $this->inform = [];
            $this->form['diekp_eos'] = '';
        }
    }

    private function protocol(): Protocol
    {
        if (! $this->protocolId) {
            return new Protocol();
        }
        $protocol = Protocol::with('assignments', 'attachments')->findOrFail($this->protocolId);
        abort_if(Auth::user()->cannot('view', $protocol), 404);   // on every request, not only when the page opened
        return $protocol;
    }

    private function activeYear(): int
    {
        return (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
    }

    private function keepFor(?string $folder): string
    {
        $k = $folder ? Keepvalue::whereFakelos($folder)->first() : null;
        return (string) ($k?->keep ?: $k?->keep_alt ?: '');
    }

    /** A different folder suggests its retention ("Διατήρηση"), as the old form did. */
    public function updatedFormFakelos(string $value): void
    {
        $this->form['keep'] = $this->keepFor($value);
    }

    public function updatedFormEtos(): void
    {
        $this->numberTouched = true;
    }

    public function updatedFormProtocolnum(): void
    {
        $this->numberTouched = true;
        $this->replacePlaceholder = false;
    }

    /**
     * R1e/R1f: "Σχετικοί αριθμοί" and "Λέξεις κλειδιά" as tags. Enter, a comma or leaving the box adds what was typed;
     * a click on a tag edits it in place; ✕ removes it. The stored value stays the same text (App\Support\TagList).
     */
    public function addTag(string $field, string $typed): void
    {
        $this->form[$this->tagField($field)] = $this->tags($field)->add($this->form[$field], $typed);
    }

    public function editTag(string $field, int $index, string $typed): void
    {
        $this->form[$this->tagField($field)] = $this->tags($field)->replace($this->form[$field], $index, $typed);
    }

    public function removeTag(string $field, int $index): void
    {
        $this->form[$this->tagField($field)] = $this->tags($field)->remove($this->form[$field], $index);
    }

    private function tagField(string $field): string
    {
        abort_unless(in_array($field, ['sxetiko', 'keywords'], true), 400);
        return $field;
    }

    private function tags(string $field): \App\Support\TagList
    {
        return \App\Support\TagList::for($this->tagField($field), (int) ($this->form['etos'] ?: $this->activeYear()));
    }

    public function save(ProtocolWriter $writer)
    {
        $input = $this->form + [
            'diekperaiosi' => array_merge(array_map(fn ($id) => "d$id", $this->handle), array_map(fn ($id) => "e$id", $this->inform)),
            'replace_placeholder' => $this->replacePlaceholder,
            'explicityNoStamp' => $this->noStamp,
            'protocolnum_proposed' => '',   // any number typed in the new form is the user's choice (B03)
        ];
        // R1c: an empty subject takes the attachment's name (shown greyed in the field before saving)
        if (trim((string) $this->form['thema']) === '' && ($guess = $this->suggestedSubject())) {
            $input['thema'] = $guess;
        }
        $adas = array_values($this->uploadAdas);
        $files = array_map(fn ($f, $i) => ['file' => $f, 'ada' => $adas[$i] ?? null], array_values((array) $this->uploads), array_keys(array_values((array) $this->uploads)));
        if (trim($this->newAda) !== '') {
            $files[] = ['file' => null, 'ada' => $this->newAda];
        }
        try {
            $result = $this->protocolId
                ? $writer->update(Auth::user(), $this->protocol(), $input, $files)
                : $writer->create(Auth::user(), $input, $files);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return null;
        }
        return $this->done($result, $this->protocolId ? 'Οι αλλαγές αποθηκεύτηκαν.' : "Καταχωρίστηκε με αριθμό {$result->protocol->protocolnum}/{$result->protocol->etos}.");
    }

    public function sendReceipt(ProtocolWriter $writer): void
    {
        try {
            $result = $writer->emailReceipt(Auth::user(), $this->protocol(), $this->receiptEmail);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }
        if ($result->warnings) {
            $this->dispatch('toast', message: $result->warnings[0], type: 'error');
            return;
        }
        $this->receiptEmail = '';
        $this->dispatch('toast', message: 'Στάλθηκε η απόδειξη: ' . ($result->messages[0] ?? ''), type: 'success');
    }

    public function assignOnly(ProtocolWriter $writer)
    {
        $result = $writer->assign(Auth::user(), $this->protocol(),
            array_merge(array_map(fn ($id) => "d$id", $this->handle), array_map(fn ($id) => "e$id", $this->inform)), $this->form['diekp_eos']);
        return $this->done($result, 'Η ανάθεση αποθηκεύτηκε.');
    }

    public function markDone(ProtocolWriter $writer)
    {
        $protocol = $this->protocol();
        $writer->markDone(Auth::user(), $protocol, $this->form['diekp_date'] ?: Carbon::today()->format(DateInput::FORMAT));
        session()->flash('notification', ['message' => 'Καταχωρίστηκε η ημερομηνία διεκπεραίωσης.', 'alert-type' => 'success']);
        return $this->redirect(url("/home/{$protocol->id}"));
    }

    public function removeAttachment(int $attachmentId, ProtocolWriter $writer): void
    {
        $attachment = Attachment::where('protocol_id', $this->protocolId)->findOrFail($attachmentId);
        try {
            $writer->removeAttachment(Auth::user(), $attachment);
            $this->dispatch('toast', message: "Το «{$attachment->name}» μεταφέρθηκε στον κάδο.", type: 'success');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function updatedPicked(): void
    {
        $this->resetErrorBag('picked');
        $refused = [];
        foreach ((array) $this->picked as $file) {
            // R4a: refused at once (size, types that run code), the others are added
            if ($why = \App\Support\SafeFile::refuse($file)) {
                $refused[] = $why;
                continue;
            }
            $this->uploads[] = $file;
            $this->uploadAdas[] = '';
        }
        $this->picked = [];
        if ($refused) {
            $this->addError('picked', implode(' ', $refused));
        }
    }

    public function removeUpload(int $index): void
    {
        $uploads = array_values((array) $this->uploads);
        $adas = array_values($this->uploadAdas);
        unset($uploads[$index], $adas[$index]);
        $this->uploads = array_values($uploads);
        $this->uploadAdas = array_values($adas);
    }

    /** R1c: subject offered from the first new file's name while the subject is empty. */
    private function suggestedSubject(): ?string
    {
        foreach ((array) $this->uploads as $file) {
            if ($guess = \App\Support\SubjectFromFile::guess($file->getClientOriginalName())) {
                return $guess;
            }
        }
        return null;
    }

    public function deleteProtocol(ProtocolWriter $writer)
    {
        $protocol = $this->protocol();
        $label = "{$protocol->protocolnum}/{$protocol->etos}";
        $writer->delete(Auth::user(), $protocol);
        session()->flash('notification', ['message' => "Διαγράφηκε το πρωτόκολλο $label.", 'alert-type' => 'success']);
        return $this->redirect(url('/home/list'));
    }

    private function done(SaveResult $result, string $headline)
    {
        $lines = '';
        foreach ($result->messages as $line) {
            $lines .= '<li>' . e($line) . '</li>';
        }
        foreach ($result->warnings as $line) {
            $lines .= '<li><strong>' . e($line) . '</strong></li>';
        }
        session()->flash('notification', [
            'message' => e($headline) . ($lines ? "<ul>$lines</ul>" : ''),
            'alert-type' => $result->warnings ? 'warning' : 'success',
        ]);
        return $this->redirect(url("/home/{$result->protocol->id}"));
    }

    /**
     * The number field's state: null (not editable), ['auto'], ['free'], ['taken', Protocol], ['placeholder', Protocol]
     * or ['invalid']. "Δεσμευμένος" = used by another protocol (owner's term).
     */
    private function numberStatus(ProtocolAccess $access): ?array
    {
        if (! $access->number) {
            return null;
        }
        $status = $this->checkNumber();
        // R1g (owner, 2026-11-18): nothing to say about a number the user did not touch, unless it is a problem
        return ! $this->numberTouched && in_array($status[0], ['free', 'auto'], true) ? ['quiet'] : $status;
    }

    private function checkNumber(): array
    {
        $num = trim($this->form['protocolnum']);
        if ($num === '') {
            return $this->protocolId ? ['invalid'] : ['auto'];
        }
        if (! ctype_digit($num) || (int) $num < 1 || ! ctype_digit(trim($this->form['etos']))) {
            return ['invalid'];
        }
        $other = Protocol::where('etos', (int) $this->form['etos'])->where('protocolnum', (int) $num)
            ->when($this->protocolId, fn ($q) => $q->where('id', '!=', $this->protocolId))->first();
        if (! $other) {
            return ['free'];
        }
        return [ProtocolWriter::isPlaceholder($other) ? 'placeholder' : 'taken', $other];
    }

    /** Other protocols the user may see with the same subject / incoming number + date (were YES/NO dialogs). */
    private function similar(string $field): array
    {
        return $field === 'thema'
            ? \App\Support\SimilarProtocols::sameSubject(Auth::user(), $this->form['thema'], $this->protocolId)
            : \App\Support\SimilarProtocols::sameIncoming(Auth::user(), $this->form['in_num'], $this->form['in_date'], $this->protocolId);
    }

    /*
     * R1i (owner, 2026-11-18): each user arranges the page's sections: drag a section by its handle to another place
     * (two columns and a row below them), collapse it, one or two columns. Saved with the account (UserPreferences).
     */

    /** wire:sort: $id was dropped at $position of $zone */
    public function moveSection(string $id, int $position, string $zone): void
    {
        $layout = UserPreferences::formLayout(Auth::user());
        if (! isset(UserPreferences::FORM_SECTIONS[$id]) || ! in_array($zone, UserPreferences::FORM_ZONES, true)) {
            return;
        }
        foreach ($layout['zones'] as $z => $ids) {
            $layout['zones'][$z] = array_values(array_diff($ids, [$id]));
        }
        array_splice($layout['zones'][$zone], max(0, min($position, count($layout['zones'][$zone]))), 0, [$id]);
        UserPreferences::setFormLayout(Auth::user(), $layout);
    }

    public function toggleSection(string $id): void
    {
        $layout = UserPreferences::formLayout(Auth::user());
        $layout['collapsed'] = in_array($id, $layout['collapsed'], true)
            ? array_values(array_diff($layout['collapsed'], [$id]))
            : [...$layout['collapsed'], $id];
        UserPreferences::setFormLayout(Auth::user(), $layout);
    }

    public function setColumns(int $columns): void
    {
        $layout = UserPreferences::formLayout(Auth::user());
        $layout['columns'] = $columns;
        UserPreferences::setFormLayout(Auth::user(), $layout);
    }

    public function resetLayout(): void
    {
        UserPreferences::resetFormLayout(Auth::user());
    }

    public function render()
    {
        $protocol = $this->protocol();
        $user = Auth::user();
        $access = ProtocolAccess::for($user, $protocol);
        $isReader = ProtocolPolicy::role($user) === ProtocolPolicy::READER;

        return view('livewire.protocol-form', [
            'layout' => UserPreferences::formLayout($user),
            'protocol' => $protocol,
            'access' => $access,
            'title' => match (true) {
                $this->copyOf !== null => 'Αντιγραφή ως νέο πρωτόκολλο',
                ! $protocol->exists => 'Νέο πρωτόκολλο',
                $access->canSave => 'Επεξεργασία πρωτοκόλλου',
                default => 'Πρωτόκολλο',
            },
            'numberStatus' => $this->numberStatus($access),
            'nextNumber' => $protocol->exists ? null : app(ProtocolNumbering::class)->next((int) ($this->form['etos'] ?: $this->activeYear())),
            'sameSubject' => $this->similar('thema'),
            'sameIncoming' => $this->similar('in_num'),
            'related' => \App\Support\RelatedNumbers::resolve($this->form['sxetiko'], (int) ($this->form['etos'] ?: $this->activeYear())),
            'keywords' => \App\Support\TagList::keywords()->tokens($this->form['keywords']),
            'folder' => $this->form['fakelos'] !== '' ? Keepvalue::where('fakelos', $this->form['fakelos'])->first(['fakelos', 'describe']) : null,
            // R1d: the folder's automatic assignments, when this save will apply them (new protocol, or a new folder)
            'autoAssign' => (! $protocol->exists || trim((string) $protocol->fakelos) !== trim($this->form['fakelos']))
                && ($rules = \App\Support\FolderRules::for($this->form['fakelos'])) ? \App\Support\FolderRules::describe($this->form['fakelos'], $rules) : null,
            'keepYears' => Keepvalue::whereNotNull('keep')->distinct()->orderBy('keep')->pluck('keep'),
            'keepWords' => Keepvalue::whereNotNull('keep_alt')->distinct()->orderBy('keep_alt')->pluck('keep_alt'),
            'users' => User::get_writers_and_admins(),
            'creator' => $protocol->exists ? User::find($protocol->user_id) : null,
            'attachments' => $protocol->exists ? $protocol->attachments : collect(),
            'canComplete' => $protocol->exists && ! $access->canSave && $access->doneDate && $user->can('complete', $protocol),
            'isReader' => $isReader,
            'canEmailReceipt' => $protocol->exists && ! $isReader,
            'putStamp' => (bool) Config::getConfigValueOf('putStamp'),
            'diavgeiaUrl' => Config::getConfigValueOf('diavgeiaUrl'),
            'suggestedSubject' => trim((string) $this->form['thema']) === '' ? $this->suggestedSubject() : null,
        ]);
    }
}
