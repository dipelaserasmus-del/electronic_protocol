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
use Livewire\Attributes\On;
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
 *
 * R5n: the page is drawn without the mail server: the e-mails of the last check (Inbox::snapshot, shared by all users,
 * from the cache) or, with none yet, only the waiting banner; the browser then calls connect() (wire:init) and the
 * fresh list replaces it. Registering still needs the server, and InboxClaims keeps it to once per e-mail.
 * Config imap.deferred_inbox false (tests of the loaded page): mount() asks the server itself, as before.
 *
 * R5m: the page arrives at once with «Σύνδεση με τον διακομιστή email…» and a seconds counter (placeholder);
 * the component then connects in a second request. The organisation's server is often slow in the mornings: the
 * page says so, and a failure says whether the server did not answer in time, refused the login, or something else.
 */
class EmailInbox extends Component
{
    /** The fields each e-mail's form shows (errors about anything else go above the form) */
    public const FIELDS = ['fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis',
        'in_perilipsi', 'out_to', 'out_date', 'out_perilipsi', 'diekp_date', 'keywords', 'keep', 'handle', 'diekp_eos', 'reply_to_email'];

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

    /** R5m: App\Support\Mail\MailboxFailure::SLOW / LOGIN / OTHER, and how long the attempt took */
    #[Locked]
    public string $failureKind = '';

    #[Locked]
    public int $failureSeconds = 0;

    /** R5m (owner, 2026-11-23): time the e-mails last came from the mail server («Τελευταία ανανέωση: 10:42») */
    #[Locked]
    public ?string $checkedAt = null;

    /** R5n: the mail server is still to be asked (connect() runs once the page is in the browser) */
    #[Locked]
    public bool $connecting = false;

    /** R5n: the e-mails shown are those of the last check (snapshot), not yet confirmed by the server */
    #[Locked]
    public bool $stale = false;

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
        if (! config('imap.deferred_inbox', true)) {
            $this->load();

            return;
        }
        $this->connecting = true;
        $this->showSnapshot();
    }

    /** R5n: called by the page once it is shown (wire:init): now ask the mail server */
    public function connect(): void
    {
        $this->allowed();
        if (! $this->connecting) {
            return;
        }
        $this->connecting = false;
        $this->done = [];   // the fresh list decides (a registered e-mail whose move failed is workable again)
        $this->load();
    }

    /**
     * R5n: the e-mails of the last check, from the cache only. E-mails registered or read since then (InboxClaims) show
     * as done, so nobody starts on them.
     */
    private function showSnapshot(): void
    {
        $inbox = app(Inbox::class);
        $snapshot = $inbox->snapshot((string) Config::getConfigValueOf('defaultImapEmail'), (string) config('imapFolders.inbox'));
        if ($snapshot === null) {
            return;
        }
        $perPage = max(1, (int) (Config::getConfigValueOf('emailNumFetch') ?: 1));
        $newest = (bool) Config::getConfigValueOf('emailFetchOrderDesc');
        // the page asked for, even if the cache has nothing for it: only the server's answer may change the page
        $page = $inbox->cachedPage($snapshot, $this->page, $perPage, $newest);
        $this->stale = true;
        $this->checkedAt = \Carbon\Carbon::parse($snapshot['at'])->setTimezone(EmailRegistration::zone())->format('H:i');
        $this->show($page);
        foreach ($this->uids as $uid) {
            $this->doneByOther($uid);   // done since the snapshot: shown as done (connect() then shows the fresh list)
        }
    }

    /** The mail server again (new e-mails); the forms of messages still shown keep what was typed. */
    public function refresh(): void
    {
        $this->allowed();
        $this->connecting = false;
        $this->done = [];
        $this->load();
    }

    /** A page of the inbox; also the list pager's gotoPage(n, 'page'); past the last page: the last (R5g) */
    public function gotoPage(int $page, string $name = 'page'): void
    {
        $this->allowed();
        $this->connecting = false;
        $before = $this->page;
        $this->page = max(1, min($page, (int) ceil($this->total / max(1, $this->perPage)) ?: 1));
        $this->done = [];
        $this->load();
        if ($this->failure !== null && $this->uids !== []) {
            $this->page = $before;   // R5n: the e-mails still shown are those of the page before
        }
    }

    /** R5g: the list pager's previous / next (x-list-pager, as on the other paged screens) */
    public function previousPage(string $name = 'page'): void
    {
        $this->gotoPage($this->page - 1);
    }

    public function nextPage(string $name = 'page'): void
    {
        $this->gotoPage($this->page + 1);
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
        $this->registerChecked($uid, $receipt, $confirmed);
        // R5r (owner, 2026-11-25): refused (a field to fill, the same document registered before, …): the browser brings
        // the reason into view, since the field can be far above the button (resources/modern/inbox.js)
        if (collect($this->getErrorBag()->keys())->contains(fn ($k) => str_starts_with($k, "forms.$uid."))) {
            $this->dispatch('inbox-register-refused', uid: $uid);
        }
    }

    private function registerChecked(int $uid, bool $receipt, bool $confirmed): void
    {
        $this->allowed();
        if (! in_array($uid, $this->uids, true) || isset($this->done[$uid]) || $this->doneByOther($uid)) {
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
        if (! $access->out) {
            $form['out_to'] = $form['out_date'] = $form['out_perilipsi'] = '';
        }
        if (! $access->doneDate) {
            $form['diekp_date'] = '';
        }
        $input = array_intersect_key($form, array_flip(['fakelos', 'thema', 'in_num', 'in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis',
            'in_paraliptis', 'in_perilipsi', 'out_to', 'out_date', 'out_perilipsi', 'diekp_date', 'keywords', 'keep', 'diekp_eos']))
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
        $this->claimed($uid, fn () => $this->registerNow($uid, $user, $raw, $input, $keys, $to));
    }

    /** register(), once this user holds the e-mail (R5m: claimed) */
    private function registerNow(int $uid, User $user, string $raw, array $input, array $keys, ?string $to): void
    {
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
        // review of R5m: registered, but its move failed (it stayed in the inbox on purpose): «Αναγνωσμένο» still moves
        // it out, as before; it is never registered again. Read already: nothing to do.
        if ($this->doneByOther($uid, moveOnly: true)) {
            return;
        }
        $this->claimed($uid, fn () => $this->markReadNow($uid), moveOnly: true);
    }

    private function markReadNow(int $uid): void
    {
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

    /**
     * R5m: $work runs while this user holds the e-mail (InboxClaims): another user's click on the same e-mail is told
     * to wait, and an e-mail done meanwhile by someone else shows that instead. What $work did is remembered for all.
     */
    private function claimed(int $uid, \Closure $work, bool $moveOnly = false): void
    {
        $claims = app(\App\Services\Mail\InboxClaims::class);
        $lock = $claims->lock($this->mailboxId, $this->uidValidity, $uid);
        if (! $lock->get()) {
            $this->addError("forms.$uid.form", 'Άλλος χρήστης καταχωρίζει αυτή τη στιγμή αυτό το email. Περιμένετε λίγο και πατήστε «Ανανέωση».');

            return;
        }
        try {
            if ($this->doneByOther($uid, $moveOnly)) {
                return;
            }
            $registered = $claims->done($this->mailboxId, $this->uidValidity, $uid);   // only with $moveOnly: registered, not moved
            $work();
            if ($registered !== null && ($this->done[$uid]['type'] ?? null) === 'read') {
                // moved now: the page says what it was (number, by whom); the record stays "registered"
                $who = trim((string) ($registered['user'] ?? '')) ?: 'άλλος χρήστης';
                $this->done[$uid] = ['messages' => ["Το email είχε ήδη καταχωριστεί ($who): μεταφέρθηκε τώρα στα αναγνωσμένα."], 'warnings' => []] + $registered;
            } elseif (isset($this->done[$uid])) {
                $claims->remember($this->mailboxId, $this->uidValidity, $uid, $this->done[$uid] + ['user' => (string) Auth::user()->name]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * R5m: done by another user (or in another tab) meanwhile: the page shows that, and nothing is done again.
     * $moveOnly («Αναγνωσμένο»): a registered e-mail may still be moved out (its move had failed), so it is not "done".
     */
    private function doneByOther(int $uid, bool $moveOnly = false): bool
    {
        $state = app(\App\Services\Mail\InboxClaims::class)->done($this->mailboxId, $this->uidValidity, $uid);
        if ($state === null || ($moveOnly && ($state['type'] ?? null) === 'registered')) {
            return false;
        }
        $who = trim((string) ($state['user'] ?? '')) ?: 'άλλος χρήστης';
        $state['messages'] = [($state['type'] ?? '') === 'registered'
            ? "Το email είχε ήδη καταχωριστεί ($who): δεν καταχωρίστηκε ξανά."
            : "Το email είχε ήδη μεταφερθεί στα αναγνωσμένα ($who)."];
        $state['warnings'] = [];
        $this->done[$uid] = $state;
        $this->resetErrorBag("forms.$uid");

        return true;
    }

    /**
     * R5f: an e-mail was registered or read: count the inbox again for everybody, and show it here at once. Counted, not
     * worked out from this page (review of R5f): other users may have emptied it meanwhile, and a registered e-mail
     * whose move failed is still there.
     */
    private function leftInbox(): void
    {
        \App\Services\Mail\InboxCount::forget();
        $count = app(\App\Services\Mail\InboxCount::class)->count();
        if ($count !== null) {
            $this->dispatch('inbox-count', count: $count);
        }
    }

    /** @return array{host: string, timeout: int} the mail server of the inbox and the seconds it is given */
    public static function serverFacts(): array
    {
        $account = (string) Config::getConfigValueOf('defaultImapEmail');

        return ['host' => (string) config("imap.accounts.$account.host", ''), 'timeout' => \App\Support\Mail\MailboxFailure::timeout($account)];
    }

    private function load(): void
    {
        $this->failure = null;
        $this->failureKind = '';
        $this->failureSeconds = 0;
        $started = microtime(true);
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
            $seconds = microtime(true) - $started;
            $this->failureSeconds = (int) round($seconds);
            $this->failureKind = \App\Support\Mail\MailboxFailure::kind($e, $seconds, self::serverFacts()['timeout']);
            // R5n: e-mails already shown stay (from the last check); the page says they may not be current
            if ($this->uids === []) {
                $this->total = 0;
            } else {
                $this->stale = true;
            }
            return;
        }
        $this->stale = false;
        $this->checkedAt = now()->setTimezone(EmailRegistration::zone())->format('H:i');
        app(Inbox::class)->rememberSnapshot((string) Config::getConfigValueOf('defaultImapEmail'), (string) config('imapFolders.inbox'), $page);
        // R5f: the top bar's badge, for everybody, and at once on this page
        \App\Services\Mail\InboxCount::put($page->total);
        $this->dispatch('inbox-count', count: $page->total);
        $this->show($page);
    }

    /** The page's e-mails (from the server, or R5n: from the snapshot); forms keep what was typed */
    private function show(\App\Services\Mail\InboxPage $page): void
    {
        // review of R5n: another mailbox or UIDVALIDITY (the server renumbered it): a UID may now be another e-mail, so
        // nothing typed or done is kept for it (a form keeps its text only for the very same e-mail)
        if ($this->mailboxId !== $page->mailboxId || $this->uidValidity !== $page->uidValidity) {
            $this->forms = [];
            $this->done = [];
            $this->confirming = [];
        }
        $this->mailboxId = $page->mailboxId;
        $this->uidValidity = $page->uidValidity;
        $this->total = $page->total;
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

    /** R5q: the user chose another look in their menu: draw the page again (from the cache, not the mail server) */
    #[On('envelope-changed')]
    public function envelopeChanged(): void
    {
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
            // R5p: the envelope's look; R5q (owner, 2026-11-25): each user's own, the setting the default; «none» = a line
            'envelope' => \App\Support\UserPreferences::envelope($user),
            'lastPage' => max(1, (int) ceil($this->total / max(1, $this->perPage))),
            // R5g: for the list pager above and below the e-mails
            'paginator' => new \Illuminate\Pagination\LengthAwarePaginator(array_fill(0, count($this->uids), null), $this->total, max(1, $this->perPage), $this->page),
            'canAssign' => ProtocolAccess::for($user, new \App\Models\Protocol())->canAssign,
            'canOut' => ProtocolAccess::for($user, new \App\Models\Protocol())->out,   // R5o: the Εξερχόμενο fields
            // review of R5o: an outgoing document with Διεκπεραίωση needs «Διεκπεραιώθηκε την» (protocol checks)
            'canDone' => ProtocolAccess::for($user, new \App\Models\Protocol())->doneDate,
            'mayChangeKeep' => (bool) Config::getConfigValueOf('allowUserChangeKeepSelect'),
            'receiptOnly' => (bool) Config::getConfigValueOf('alwaysSendReceitForEmails'),
            'keepYears' => Keepvalue::whereNotNull('keep')->distinct()->orderBy('keep')->pluck('keep'),
            'keepWords' => Keepvalue::whereNotNull('keep_alt')->distinct()->orderBy('keep_alt')->pluck('keep_alt'),
            'users' => User::get_writers_and_admins(),
            'zone' => EmailRegistration::zone(),
            'connecting' => $this->connecting,
            'stale' => $this->stale,
        ] + self::serverFacts());
    }
}
