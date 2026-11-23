<?php

namespace Tests\Feature;

use App\Livewire\EmailInbox;
use App\Support\Mail\MailboxFailure;
use Livewire\Livewire;
use Tests\Support\FakeMailbox;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

/**
 * R5m (owner, 2026-11-23): the organisation's mail server is often overloaded, mornings above all. The inbox page
 * appears at once and says it is waiting for the mail server (deferred component); a failure says whether the server
 * did not answer in time (not the application's fault), refused the login, or something else, with «Δοκιμή ξανά».
 */
class InboxLoadingTest extends FeatureTestCase
{
    private FakeMailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('defaultImapEmail', 'default');
        $this->setting('emailNumFetch', 3);
        $this->mailbox = FakeMailbox::install($this->app);
        $this->mailbox->messages[1] = file_get_contents(glob(base_path('tests/fixtures/emails/synthetic/*.eml'))[0]);
        config(['imap.accounts.default.host' => 'imap.example.org', 'imap.accounts.default.timeout' => 45]);
        config(['imap.deferred_inbox' => false]);   // the loaded page; the tests of the first view switch it on
    }

    public function test_the_page_arrives_at_once_and_waits_for_the_mail_server(): void
    {
        config(['imap.deferred_inbox' => true]);
        $this->mailbox->unreachable = true;   // not even asked yet: the page must not wait for it

        $html = $this->actingAs($this->writer())->get('/inbox')->assertOk()->getContent();

        $this->assertStringContainsString('id="inbox-connecting"', $html);
        $this->assertStringContainsString('Σύνδεση με τον διακομιστή email (imap.example.org)…', $html);
        $this->assertStringContainsString('Παρακαλώ περιμένετε…', $html);
        $this->assertStringContainsString('x-data="inboxWait(true)"', $html);   // the seconds counter
        // a web server that gives up (504) turns this banner into a plain message (owner: not Livewire's popup)
        $this->assertStringContainsString('@inbox-unreachable.window="unreachable()"', $html);
        $this->assertStringContainsString('Ο διακομιστής email δεν απάντησε εγκαίρως.', $html);
        $this->assertStringNotContainsString('πρόβλημα της εφαρμογής', $html);
        $this->assertStringNotContainsString('id="inbox-failure"', $html);
        $this->assertStringNotContainsString('inbox-message', $html);
        $this->assertStringNotContainsString('Δεν υπάρχουν εισερχόμενα', $html);   // not "empty" while waiting
        $this->assertStringContainsString('wire:init="connect"', $html);
        $this->assertStringContainsString('data-inbox-state="connecting"', $html);
    }

    /** R5n: with no earlier check, the page waits with the banner only; connect() then shows the e-mails */
    public function test_connect_asks_the_mail_server_once_the_page_is_shown(): void
    {
        config(['imap.deferred_inbox' => true]);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('id="inbox-connecting"', false)->assertDontSee('inbox-message', false);
        $this->assertSame([], $this->mailbox->fetches);

        $page->call('connect')->assertSee('inbox-message', false)->assertSee('data-inbox-state="ready"', false)
            ->assertDontSee('id="inbox-connecting"', false)->assertDontSee('wire:init="connect"', false);
        $fetches = count($this->mailbox->fetches);
        $page->call('connect');   // once only (a second call does nothing)
        $this->assertSame($fetches, count($this->mailbox->fetches));
    }

    /**
     * R5n (owner, 2026-11-23): while the server is slow, the e-mails of the last check are shown at once, from the
     * cache, to every user (one shared snapshot); if the server then fails, they stay, marked as possibly not current.
     */
    public function test_the_last_checks_emails_are_shown_at_once_while_it_connects(): void
    {
        $this->setting('timeZone', 'Europe/Athens');
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-23 06:05:00', 'UTC'));
        Livewire::actingAs($this->admin())->test(EmailInbox::class)->assertSee('inbox-message', false);   // a check, by someone

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-23 07:30:00', 'UTC'));
        config(['imap.deferred_inbox' => true]);
        $this->mailbox->unreachable = true;   // a slow morning: the page must not wait for it
        $fetches = count($this->mailbox->fetches);
        $html = $this->actingAs($this->writer())->get('/inbox')->assertOk()->getContent();   // another user

        $this->assertStringContainsString('inbox-message', $html);
        $this->assertStringContainsString('Μέχρι τότε βλέπετε τα email της τελευταίας ανανέωσης (08:05).', $html);
        $this->assertStringContainsString('Τελευταία ανανέωση: 08:05', $html);
        $this->assertStringContainsString('wire:init="connect"', $html);
        $this->assertStringNotContainsString('id="inbox-failure"', $html);
        $this->assertSame($fetches, count($this->mailbox->fetches));   // from the cache, not the server

        // the server fails: the e-mails stay, said to be of the last check
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)->call('connect')
            ->assertSee('id="inbox-failure"', false)->assertSee('inbox-message', false)
            ->assertSee('Τα email που βλέπετε είναι της τελευταίας ανανέωσης (08:05): μπορεί να μην είναι τα τρέχοντα.');

        // it answers again: fresh, no longer "of the last check"
        $this->mailbox->unreachable = false;
        $page->call('refresh')->assertDontSee('id="inbox-failure"', false)->assertDontSee('τελευταίας ανανέωσης')
            ->assertSee('Τελευταία ανανέωση: 09:30');
        \Carbon\Carbon::setTestNow();
    }

    /** R5n: the cached first view keeps the page asked for (?page=3), even when the cache has nothing for it */
    public function test_the_first_view_keeps_the_page_asked_for(): void
    {
        foreach (range(2, 8) as $uid) {
            $this->mailbox->messages[$uid] = $this->mailbox->messages[1];
        }
        Livewire::actingAs($this->admin())->test(EmailInbox::class);   // snapshot: 8 e-mails, page 1 cached only
        config(['imap.deferred_inbox' => true]);

        $page = Livewire::withQueryParams(['page' => 3])->actingAs($this->writer())->test(EmailInbox::class);
        $this->assertSame(3, $page->get('page'));
        $page->call('connect');
        $this->assertSame(3, $page->get('page'));
        $this->assertSame([7, 8], $page->get('uids'));
    }

    /** Review of R5n: the server renumbered the mailbox (new UIDVALIDITY): a reused UID gets its own e-mail's form */
    public function test_a_renumbered_mailbox_does_not_keep_the_old_forms(): void
    {
        $files = glob(base_path('tests/fixtures/emails/synthetic/*.eml'));
        Livewire::actingAs($this->admin())->test(EmailInbox::class);   // snapshot: UID 1 = the first e-mail
        config(['imap.deferred_inbox' => true]);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $old = $page->get('forms.1.thema');

        $this->mailbox->validity = '2002';   // rebuilt: UID 1 is now another e-mail
        $this->mailbox->messages = [1 => file_get_contents($files[1])];
        $page->set('forms.1.in_num', 'typed for the old e-mail')->call('connect');

        $this->assertNotSame($old, $page->get('forms.1.thema'));
        $this->assertNotSame('typed for the old e-mail', $page->get('forms.1.in_num'));
        $this->assertSame('2002', $page->get('uidValidity'));
    }

    /** R5n: an e-mail registered since the last check shows as done in the first view, so nobody starts on it */
    public function test_emails_done_since_the_last_check_show_as_done(): void
    {
        $this->setting('protocolValidate', 0);
        Livewire::actingAs($this->admin())->test(EmailInbox::class)->set('forms.1.files', [])
            ->call('register', 1, false)->assertHasNoErrors();   // the snapshot (taken before) still lists it
        config(['imap.deferred_inbox' => true]);

        Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('Το email είχε ήδη καταχωριστεί')->assertSee('inbox-done', false)
            ->call('register', 1, false)
            ->call('connect')->assertDontSee('inbox-message', false);   // gone from the server's list
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /** with the switch off (other inbox tests) the page shows the e-mails at once */
    public function test_switched_off_the_page_is_loaded_at_once(): void
    {
        config(['imap.deferred_inbox' => false]);
        $html = $this->actingAs($this->writer())->get('/inbox')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="inbox-connecting"', $html);
        $this->assertStringContainsString('inbox-message', $html);
    }

    public function test_a_server_that_does_not_answer_in_time_is_said_to_be_slow_with_a_retry(): void
    {
        $this->mailbox->failWith = new ConnectionFailedException('connection failed', 0,
            new \ErrorException('stream_socket_client(): Unable to connect to imap.example.org:993 (Connection timed out)'));

        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('id="inbox-failure"', false)->assertSee('data-kind="slow"', false)
            ->assertSee('Ο διακομιστής email (imap.example.org) δεν απάντησε μέσα σε')
            ->assertSee('Δοκιμάστε ξανά σε λίγο.')->assertDontSee('πρόβλημα της εφαρμογής')
            ->assertSee('id="inbox-retry"', false)->assertSee('Κωδικός σφάλματος: E-', false)
            ->assertDontSee('Η σύνδεση με τον λογαριασμό email απέτυχε');

        // the server answers again: «Δοκιμή ξανά» (refresh) shows the e-mails
        $this->mailbox->failWith = null;
        $page->call('refresh')->assertDontSee('id="inbox-failure"', false)->assertSee('inbox-message', false)
            ->assertSee('data-inbox-state="ready"', false);
    }

    public function test_a_refused_login_and_other_failures_say_so(): void
    {
        $this->mailbox->failWith = new AuthFailedException('failed to authenticate');
        Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('data-kind="login"', false)->assertSee('δεν δέχτηκε τη σύνδεση του λογαριασμού')
            ->assertSee('data-inbox-state="failed"', false);

        $this->mailbox->failWith = null;
        $this->mailbox->unreachable = true;   // "connection refused", at once
        Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('data-kind="other"', false)->assertSee('Η σύνδεση με τον λογαριασμό email απέτυχε')
            ->assertSee('id="inbox-retry"', false);
    }

    public function test_refresh_and_page_changes_show_the_waiting_notice(): void
    {
        Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSee('wire:target="refresh,gotoPage,previousPage,nextPage"', false)
            ->assertSee('id="inbox-reconnecting"', false)->assertSee('Παρακαλώ περιμένετε…')
            // a refresh the web server gave up on: said above the e-mails, with «Δοκιμή ξανά» (refresh)
            ->assertSee('id="inbox-unreachable"', false)->assertSee('@click="failed = false; $wire.refresh()"', false);
    }

    /** owner, 2026-11-23: «Τελευταία ανανέωση: ΩΡΑ» next to «Ανανέωση»; a failed refresh keeps the last good time */
    public function test_the_last_refresh_time_is_shown_next_to_the_button(): void
    {
        $this->setting('timeZone', 'Europe/Athens');
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-23 06:42:00', 'UTC'));
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)
            ->assertSeeHtml('id="inbox-checked-at"')->assertSee('Τελευταία ανανέωση: 08:42');

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-11-23 07:15:00', 'UTC'));
        $this->mailbox->unreachable = true;
        $page->call('refresh')->assertSee('id="inbox-failure"', false)->assertSee('Τελευταία ανανέωση: 08:42');

        $this->mailbox->unreachable = false;
        $page->call('refresh')->assertSee('Τελευταία ανανέωση: 09:15');
        \Carbon\Carbon::setTestNow();
    }

    public function test_failure_kinds(): void
    {
        $this->assertSame(MailboxFailure::LOGIN, MailboxFailure::kind(new ConnectionFailedException('x', 0, new AuthFailedException()), 1, 30));
        $this->assertSame(MailboxFailure::SLOW, MailboxFailure::kind(new \RuntimeException('Operation timed out'), 2, 30));
        // a reply that never came only says "empty response": slow when it took (almost) the whole timeout
        $this->assertSame(MailboxFailure::SLOW, MailboxFailure::kind(new \RuntimeException('empty response'), 29.5, 30));
        $this->assertSame(MailboxFailure::OTHER, MailboxFailure::kind(new \RuntimeException('empty response'), 0.4, 30));
        $this->assertSame(MailboxFailure::OTHER, MailboxFailure::kind(new \RuntimeException('connection refused'), 0.1, 30));

        config(['imap.accounts.default.timeout' => null]);
        $this->assertSame(30, MailboxFailure::timeout('default'));
        $this->assertSame(30, MailboxFailure::timeout('no-such-account'));
    }
}
