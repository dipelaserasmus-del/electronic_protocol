<?php

namespace App\Livewire;

use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\Mail\EmailRegistration;
use App\Services\Mail\Inbox;
use App\Services\Mail\Mailbox;
use App\Services\Mail\MailboxConnector;
use App\Services\Protocols\AttachmentsNotSaved;
use App\Services\Protocols\ProtocolAccess;
use App\Support\InboxAccess;
use App\Support\KeywordSuggestions;
use App\Support\SimilarProtocols;
use App\Support\SubjectFromFile;
use App\Support\Suggestions;
use App\Support\TagList;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * R5c: "Εισερχόμενα" (was the old viewEmails page). The messages of one page, each with the form that registers it;
 * a list of the page's messages on the left (a click scrolls to the message); text and HTML of each e-mail in sections
 * that open and close (remembered by the browser); the attachments with type and size; actions as pills.
 *
 * Registering happens on the page (no reload): an error appears at the field it is about and the e-mail stays as it
 * was; a registered (or read) e-mail shows what happened in its place until the page is opened again.
 * The mail server is asked only when the page is opened, changed or refreshed; clicks read the cached messages.
 */
class EmailInbox extends Component
{
    /** The fields each e-mail's form shows (errors about anything else go above the form) */
    private const FIELDS = ['fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis',
        'in_perilipsi', 'keywords', 'keep', 'handle', 'diekp_eos', 'reply_to_email'];

    #[Url(as: 'page', except: 1)]
    public int $page = 1;

    #[Locked]
    public string $mailboxId = '';

    #[Locked]
    public string $uidValidity = '';

    /** @var list<int> UIDs of the page, in the order shown */
    #[Locked]
    public array $uids = [];

    #[Locked]
    public int $total = 0;

    #[Locked]
    public int $perPage = 1;

    /** Reference code when the mail server could not be reached (shown instead of the messages) */
    #[Locked]
    public ?string $failure = null;

    /** @var array<int, array<string, mixed>> the form of each message, by UID */
    public array $forms = [];

    /** @var array<int, bool> R5d: e-mails waiting for "register anyway" (similar protocols found), with the receipt choice */
    #[Locked]
    public array $confirming = [];

    /** @var array<int, array{type: string, protocol_id?: int, label?: string, messages?: list<string>, warnings?: list<string>}> done, by UID */
    #[Locked]
    public array $done = [];

    public function mount(): void
    {
        $this->allowed();
        $this->load();
    }

    /** The mail server again (new e-mails); the forms of messages still shown keep what was typed. */
    public function refresh(): void
    {
        $this->allowed();
        $this->done = [];
        $this->load();
    }

    public function goToPage(int $page): void
    {
        $this->allowed();
        $this->page = max(1, $page);
        $this->done = [];
        $this->load();
    }

    /** The retention follows the folder, as in the protocol form (setting allowUserChangeKeepSelect decides the rest). */
    public function updatedForms(mixed $value, string $key): void
    {
        if (preg_match('/^(\d+)\.fakelos$/', $key, $m) && isset($this->forms[(int) $m[1]])) {
            $this->forms[(int) $m[1]]['keep'] = self::keepFor((string) $value);
        }
    }

    /**
     * R5d: Λέξεις κλειδιά as tags (as in the protocol form): add what was typed or a suggestion, edit in place, remove.
     * Called by tagBox('keywords', true, [uid]).
     */
    public function addTag(string $field, string $typed, int $uid): void
    {
        $this->tagged($field, $uid, fn (string $value) => TagList::keywords()->add($value, $typed));
    }

    public function editTag(string $field, int $index, string $typed, int $uid): void
    {
        $this->tagged($field, $uid, fn (string $value) => TagList::keywords()->replace($value, $index, $typed));
    }

    public function removeTag(string $field, int $index, int $uid): void
    {
        $this->tagged($field, $uid, fn (string $value) => TagList::keywords()->remove($value, $index));
    }

    private function tagged(string $field, int $uid, \Closure $change): void
    {
        abort_unless($field === 'keywords' && isset($this->forms[$uid]), 400);
        $this->forms[$uid]['keywords'] = $change((string) ($this->forms[$uid]['keywords'] ?? ''));
    }

    /**
     * @param bool $confirmed R5d: the user saw the protocols with the same subject / incoming number and still wants
     *        it registered (was a YES/NO dialog)
     */
    public function register(int $uid, bool $receipt = false, bool $confirmed = false): void
    {
        $this->allowed();
        if (! in_array($uid, $this->uids, true) || isset($this->done[$uid])) {
            return;
        }
        $raw = app(Inbox::class)->raw($this->mailboxId, $this->uidValidity, $uid);
        if ($raw === null) {
            $this->addError("forms.$uid.form", 'Το email δεν είναι πια διαθέσιμο (καταχωρίστηκε ή μετακινήθηκε από άλλον χρήστη;). Πατήστε «Ανανέωση».');
            return;
        }
        $form = $this->forms[$uid] ?? [];
        $user = Auth::user();
        $access = ProtocolAccess::for($user, new \App\Models\Protocol());
        if (! $access->canAssign) {
            $form['handle'] = $form['inform'] = [];
            $form['diekp_eos'] = '';
        }
        if (! Config::getConfigValueOf('allowUserChangeKeepSelect')) {
            $form['keep'] = self::keepFor((string) ($form['fakelos'] ?? ''));
        }
        $input = array_intersect_key($form, array_flip(['fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis',
            'in_paraliptis', 'in_perilipsi', 'keywords', 'keep', 'diekp_eos']))
            + ['diekperaiosi' => array_merge(array_map(fn ($id) => "d$id", (array) ($form['handle'] ?? [])), array_map(fn ($id) => "e$id", (array) ($form['inform'] ?? [])))];
        $keys = array_map('strval', array_keys(array_filter((array) ($form['files'] ?? []))));
        $to = $receipt ? (trim((string) ($form['reply_to_email'] ?? '')) ?: null) : null;
        if ($receipt && $to === null) {
            $this->addError("forms.$uid.reply_to_email", 'Συμπληρώστε τη διεύθυνση για την απόδειξη παραλαβής.');
            return;
        }

        $this->resetErrorBag();
        try {
            EmailRegistration::checkFolder($input, $keys);   // what the user must fix comes first
        } catch (ValidationException $e) {
            $this->addError("forms.$uid.fakelos", $e->errors()['fakelos'][0]);
            return;
        }
        // R5d: an e-mail without a subject takes its attachment's name, as offered in the field
        $input['thema'] = $this->subject($uid);
        // R5d: the same document registered before (by the subject it will have)? Said next to the button; a second,
        // explicit click registers it
        if (! $confirmed && $this->similar($uid) !== [[], []]) {
            $this->confirming[$uid] = $receipt;
            $this->addError("forms.$uid.similar", 'Υπάρχει ήδη πρωτόκολλο που μοιάζει με αυτό το email (δείτε παραπάνω).');
            return;
        }
        unset($this->confirming[$uid]);
        try {
            $mailbox = $this->mailbox();
        } catch (\Throwable $e) {
            report($e);
            $this->addError("forms.$uid.form", 'Η σύνδεση με τον λογαριασμό email απέτυχε: το email δεν καταχωρίστηκε. Κωδικός σφάλματος: '.\App\Exceptions\Handler::errorId());
            return;
        }
        try {
            $result = app(EmailRegistration::class)->register($user, $raw, $input, $keys, $to, $mailbox, $uid);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $field = $field === 'diekperaiosi' ? 'handle' : $field;
                // an error about something the page has no field for is said above the form, never lost (review of R5c)
                $this->addError('forms.'.$uid.'.'.(in_array($field, self::FIELDS, true) ? $field : 'form'), $messages[0]);
            }
            return;
        } catch (AttachmentsNotSaved $e) {
            $this->addError("forms.$uid.form", 'Δεν ήταν δυνατή η αποθήκευση των συνημμένων αρχείων: το email δεν καταχωρίστηκε και παραμένει στα εισερχόμενα. Κωδικός σφάλματος: '.\App\Exceptions\Handler::errorId());
            return;
        } catch (AuthorizationException $e) {
            $this->addError("forms.$uid.form", $e->getMessage());
            return;
        }
        $this->leftInbox();
        $p = $result->protocol;
        $this->done[$uid] = ['type' => 'registered', 'protocol_id' => $p->id, 'label' => "{$p->protocolnum}/{$p->etos}",
            'messages' => $result->messages, 'warnings' => $result->warnings, 'files' => $result->attachmentsAdded];
    }

    /** "Αναγνωσμένο": the e-mail leaves the inbox for the beenRead folder, without a protocol. */
    public function markRead(int $uid): void
    {
        $this->allowed();
        if (! in_array($uid, $this->uids, true) || isset($this->done[$uid])) {
            return;
        }
        try {
            $this->mailbox()->move($uid, (string) config('imapFolders.beenRead'));
        } catch (\Throwable $e) {
            report($e);
            $this->addError("forms.$uid.form", 'Το email δεν μεταφέρθηκε στα αναγνωσμένα. Κωδικός σφάλματος: '.\App\Exceptions\Handler::errorId());
            return;
        }
        $this->leftInbox();
        $this->done[$uid] = ['type' => 'read'];
    }

    /** R5f: an e-mail left the inbox: one less on the badge here at once; every user's badge counts again */
    private function leftInbox(): void
    {
        \App\Services\Mail\InboxCount::forget();
        $this->dispatch('inbox-count', count: max(0, $this->total - count($this->done) - 1));
    }

    private function load(): void
    {
        $this->failure = null;
        $perPage = max(1, (int) (Config::getConfigValueOf('emailNumFetch') ?: 1));
        $days = (int) Config::getConfigValueOf('daysToCheckEmailBack');
        try {
            $mailbox = $this->mailbox();
            $inbox = app(Inbox::class);
            $since = \App\Services\Mail\InboxCount::since();
            $newest = (bool) Config::getConfigValueOf('emailFetchOrderDesc');
            $page = $inbox->page($mailbox, $since, $this->page, $perPage, $newest);
            // a page left empty (its e-mails registered, by this user or others): back to the first page
            if ($page->messages === [] && $page->total > 0 && $this->page > 1) {
                $this->page = 1;
                $page = $inbox->page($mailbox, $since, 1, $perPage, $newest);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->failure = \App\Exceptions\Handler::errorId();
            $this->uids = [];
            $this->total = 0;
            return;
        }
        $this->mailboxId = $page->mailboxId;
        $this->uidValidity = $page->uidValidity;
        $this->total = $page->total;
        // R5f: the top bar's badge, for everybody, and at once on this page
        \App\Services\Mail\InboxCount::put($page->total);
        $this->dispatch('inbox-count', count: $page->total);
        $this->perPage = $page->perPage;
        $this->uids = array_map(fn ($m) => $m->uid, $page->messages);
        // the attachment and HTML addresses read the messages this user's page shows (R5a)
        session()->put('inbox', ['mailbox' => $page->mailboxId, 'uidValidity' => $page->uidValidity]);

        $user = Auth::user();
        $forms = [];
        foreach ($page->messages as $m) {
            $forms[$m->uid] = $this->forms[$m->uid] ?? EmailRegistration::defaults($m->summary, $user);
        }
        $this->forms = $forms;
    }

    private function mailbox(): Mailbox
    {
        return app(MailboxConnector::class)->open((string) Config::getConfigValueOf('defaultImapEmail'), (string) config('imapFolders.inbox'));
    }

    /** Same rule as the page's route: writers and administrators with inbox access (every Livewire call checks it). */
    private function allowed(): void
    {
        $user = Auth::user();
        abort_unless($user && InboxAccess::allows($user) && ProtocolPolicy::role($user) !== ProtocolPolicy::READER, 403);
    }

    /** @return array{0: list<\App\Models\Protocol>, 1: list<\App\Models\Protocol>} same subject, same incoming number + date */
    private function similar(int $uid): array
    {
        $f = $this->forms[$uid] ?? [];

        return [SimilarProtocols::sameSubject(Auth::user(), $this->subject($uid)),
            SimilarProtocols::sameIncoming(Auth::user(), $f['in_num'] ?? '', $f['in_date'] ?? '')];
    }

    /** The subject the protocol will have: as typed; empty, for an e-mail without one, its attachment's name (R5d) */
    private function subject(int $uid): string
    {
        $typed = (string) ($this->forms[$uid]['thema'] ?? '');

        return trim($typed) === '' ? ($this->subjectGuess($uid, onlyWithoutSubject: true) ?? $typed) : $typed;
    }

    /** R5d: the subject offered when the e-mail has none: from its first attachment's name (R1c rule) */
    private function subjectGuess(int $uid, bool $onlyWithoutSubject = false): ?string
    {
        $summary = app(Inbox::class)->summary($this->mailboxId, $this->uidValidity, $uid);
        if ($onlyWithoutSubject && trim((string) ($summary['subject'] ?? '')) !== '') {
            return null;   // the user emptied the subject: say so at the field instead of guessing
        }
        foreach ($summary['attachments'] ?? [] as $a) {
            if ($guess = SubjectFromFile::guess($a['name'])) {
                return $guess;
            }
        }

        return null;
    }

    public static function keepFor(?string $folder): string
    {
        return Keepvalue::retentionOf($folder);
    }

    public function render(Inbox $inbox)
    {
        $messages = [];
        foreach ($this->uids as $uid) {
            if (isset($this->done[$uid])) {
                $messages[$uid] = $inbox->summary($this->mailboxId, $this->uidValidity, $uid) ?? ['subject' => '', 'from_name' => null, 'from_email' => null, 'date' => null, 'attachments' => []];
                continue;
            }
            if (($summary = $inbox->summary($this->mailboxId, $this->uidValidity, $uid)) !== null) {
                $messages[$uid] = $summary;
            }
        }
        $user = Auth::user();
        // R5d: per e-mail still to register: similar protocols, keyword suggestions, the subject offered when it has none
        $known = \Illuminate\Support\Facades\Cache::remember('inbox-keywords:'.$user->id, 60, fn () => Suggestions::keywords($user));
        $spellings = \Illuminate\Support\Facades\Cache::remember('inbox-spellings:'.$user->id, 600, fn () => Suggestions::spellings($user));   // R5e
        $extra = [];
        foreach ($messages as $uid => $m) {
            if (isset($this->done[$uid])) {
                continue;
            }
            $tags = TagList::keywords()->tokens($this->forms[$uid]['keywords'] ?? '');
            $extra[$uid] = [
                'similar' => $this->similar($uid),
                'keywords' => $tags,
                // R5e: the attachments' names too
                'suggested' => KeywordSuggestions::for((string) ($m['subject'] ?? ''), (string) ($m['text'] ?? ''), $known, $tags,
                    array_column($m['attachments'] ?? [], 'name'), $spellings),
                'subjectGuess' => ($m['subject'] ?? '') === '' ? $this->subjectGuess($uid) : null,
            ];
        }

        return view('livewire.email-inbox', [
            'messages' => $messages,
            'extra' => $extra,
            'account' => (string) Config::getConfigValueOf('defaultImapEmail'),
            'newestFirst' => (bool) Config::getConfigValueOf('emailFetchOrderDesc'),
            'lastPage' => max(1, (int) ceil($this->total / max(1, $this->perPage))),
            'canAssign' => ProtocolAccess::for($user, new \App\Models\Protocol())->canAssign,
            'mayChangeKeep' => (bool) Config::getConfigValueOf('allowUserChangeKeepSelect'),
            'receiptOnly' => (bool) Config::getConfigValueOf('alwaysSendReceitForEmails'),
            'keepYears' => Keepvalue::whereNotNull('keep')->distinct()->orderBy('keep')->pluck('keep'),
            'keepWords' => Keepvalue::whereNotNull('keep_alt')->distinct()->orderBy('keep_alt')->pluck('keep_alt'),
            'users' => User::get_writers_and_admins(),
            'zone' => EmailRegistration::zone(),
        ]);
    }
}
