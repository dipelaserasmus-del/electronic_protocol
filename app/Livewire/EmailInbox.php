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

    public function register(int $uid, bool $receipt, EmailRegistration $registration): void
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
            $mailbox = $this->mailbox();
        } catch (\Throwable $e) {
            report($e);
            $this->addError("forms.$uid.form", 'Η σύνδεση με τον λογαριασμό email απέτυχε: το email δεν καταχωρίστηκε. Κωδικός σφάλματος: '.\App\Exceptions\Handler::errorId());
            return;
        }
        try {
            $result = $registration->register($user, $raw, $input, $keys, $to, $mailbox, $uid);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $field = $field === 'diekperaiosi' ? 'handle' : $field;
                $this->addError("forms.$uid.$field", $messages[0]);
            }
            return;
        } catch (AttachmentsNotSaved $e) {
            $this->addError("forms.$uid.form", 'Δεν ήταν δυνατή η αποθήκευση των συνημμένων αρχείων: το email δεν καταχωρίστηκε και παραμένει στα εισερχόμενα. Κωδικός σφάλματος: '.\App\Exceptions\Handler::errorId());
            return;
        } catch (AuthorizationException $e) {
            $this->addError("forms.$uid.form", $e->getMessage());
            return;
        }
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
        $this->done[$uid] = ['type' => 'read'];
    }

    private function load(): void
    {
        $this->failure = null;
        $perPage = max(1, (int) (Config::getConfigValueOf('emailNumFetch') ?: 1));
        $days = (int) Config::getConfigValueOf('daysToCheckEmailBack');
        try {
            $mailbox = $this->mailbox();
            $inbox = app(Inbox::class);
            $since = Carbon::now()->subDays($days)->startOfDay();
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

    public static function keepFor(?string $folder): string
    {
        $k = $folder ? Keepvalue::whereFakelos($folder)->first() : null;

        return (string) ($k?->keep ?: $k?->keep_alt ?: '');
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

        return view('livewire.email-inbox', [
            'messages' => $messages,
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
