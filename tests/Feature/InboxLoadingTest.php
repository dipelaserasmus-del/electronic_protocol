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
    }

    public function test_the_page_arrives_at_once_and_waits_for_the_mail_server(): void
    {
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
