<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailbox;

/**
 * R5a: the inbox page with an in-memory mailbox (FakeMailbox) instead of an IMAP server. Messages are the committed
 * synthetic e-mails (tests/fixtures/emails/synthetic, invented data).
 */
class InboxTest extends FeatureTestCase
{
    private FakeMailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('defaultImapEmail', 'default');
        $this->setting('emailNumFetch', 3);
        $this->setting('emailFetchOrderDesc', 0);
        $this->mailbox = FakeMailbox::install($this->app);
        foreach (array_slice(glob(base_path('tests/fixtures/emails/synthetic/*.eml')), 0, 8) as $i => $file) {
            $this->mailbox->messages[$i + 1] = file_get_contents($file);   // UIDs 1..8
        }
    }

    public function test_messages_are_downloaded_once_then_read_from_the_cache(): void
    {
        $user = $this->writer();

        $this->actingAs($user)->get('/viewEmails')->assertOk()->assertSee('από <strong>8</strong> εισερχόμενα', false);
        $this->assertSame([[1, 2, 3]], $this->mailbox->fetches, 'first visit: the 3 messages of the page, in one request');

        $this->actingAs($user)->get('/viewEmails')->assertOk();
        $this->actingAs($this->writer())->get('/viewEmails')->assertOk();   // also another user
        $this->assertSame([[1, 2, 3]], $this->mailbox->fetches, 'later visits download nothing');

        $this->actingAs($user)->get('/viewEmails?imap_page=2')->assertOk();
        $this->assertSame([4, 5, 6], $this->mailbox->fetches[1], 'another page: only its messages');
        $this->actingAs($user)->get('/viewEmails?imap_page=3')->assertOk();
        $this->assertSame([7, 8], $this->mailbox->fetches[2]);

        $this->mailbox->messages[9] = $this->mailbox->messages[1];   // a new e-mail arrives
        $this->setting('emailFetchOrderDesc', 1);
        $this->actingAs($user)->get('/viewEmails')->assertOk();
        $this->assertSame([9], $this->mailbox->fetches[3], 'newest first (9, 8, 7): only the new message is downloaded');
    }

    public function test_cache_keeps_only_what_is_still_in_the_inbox_and_follows_uidvalidity(): void
    {
        $user = $this->writer();
        $this->actingAs($user)->get('/viewEmails')->assertOk();
        $this->assertCount(3, $this->cachedFiles());

        unset($this->mailbox->messages[2]);   // registered / moved by someone
        $this->actingAs($user)->get('/viewEmails')->assertOk();
        $this->assertCount(3, $this->cachedFiles(), 'UID 2 gone, UID 4 now on the page');
        $this->assertFalse(collect($this->cachedFiles())->contains(fn ($f) => str_ends_with($f, '/2.eml')));

        // the server rebuilt the folder: same UIDs may now be other messages → everything is downloaded again
        $this->mailbox->validity = '2002';
        $this->mailbox->fetches = [];
        $this->actingAs($user)->get('/viewEmails')->assertOk();
        $this->assertSame([[1, 3, 4]], $this->mailbox->fetches);
        $this->assertSame([], array_filter($this->cachedFiles(), fn ($f) => str_contains($f, '/1001/')), 'old UIDVALIDITY removed');
    }

    public function test_page_shows_decoded_greek_and_writes_nothing_to_public_tmp(): void
    {
        $user = $this->writer();
        $old = public_path('tmp/u'.$user->id);
        $othersOld = public_path('tmp/u'.($user->id + 1000));   // another user's, never visiting again
        foreach ([$old, $othersOld] as $dir) {
            @mkdir($dir, 0777, true);
            file_put_contents("$dir/1.eml", 'copy left by the old inbox');
        }

        $page = $this->actingAs($user)->get('/viewEmails')->assertOk();

        $page->assertSee('Διεύθυνση Π.Ε. Δοκιμής');   // 01: UTF-8 sender
        $page->assertSee('Πρόσκληση σε επιμορφωτική ημερίδα εκπαιδευτικών');
        $page->assertDontSee('??');
        $this->assertDirectoryDoesNotExist($old, 'the old public copies are removed');
        $this->assertDirectoryDoesNotExist($othersOld, 'also those of other users');
        $this->assertFileExists(public_path('tmp/dummy'), 'other files are left alone');
        $this->assertSame([], Storage::disk('tmp')->directories(), 'no per-user copies in public/tmp');
    }

    /** Owner's "??" case: the synthetic e-mail 09 has its sender name split inside the letter "ί". */
    public function test_sender_name_split_inside_a_letter_is_shown_correctly(): void
    {
        $this->mailbox->messages = [9 => file_get_contents(base_path('tests/fixtures/emails/synthetic/09-sender-split-inside-letter.eml'))];

        $page = $this->actingAs($this->writer())->get('/viewEmails')->assertOk();

        $page->assertSee('Σύλλογος Διδασκόντων Γυμνασίου Δοκιμής');
        $page->assertDontSee('??');
    }

    public function test_html_preview_is_served_to_inbox_users_only_in_a_sandbox(): void
    {
        $this->mailbox->messages = [7 => $this->htmlEml()];
        $user = $this->writer();
        $this->actingAs($user)->get('/viewEmails')->assertOk()->assertSee('/viewEmailHtml/7', false);

        $html = $this->actingAs($user)->get('/viewEmailHtml/7')->assertOk();
        $html->assertSee('<b>σύσκεψη</b>', false);
        $this->assertStringStartsWith('sandbox', $html->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'none'", $html->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('allow-scripts', $html->headers->get('Content-Security-Policy'));

        $this->actingAs($user)->get('/viewEmailHtml/8')->assertNotFound();
        $this->setting('allowedEmailUsers', 'someone-else');
        $this->actingAs($user)->get('/viewEmailHtml/7')->assertRedirect();   // no inbox access
        auth()->logout();
        $this->get('/viewEmailHtml/7')->assertRedirect('/login');
    }

    public function test_attachment_is_served_with_its_greek_name_to_inbox_users_only(): void
    {
        $user = $this->writer();
        $this->actingAs($user)->get('/viewEmails')->assertOk();

        $pdf = $this->actingAs($user)->get('/viewEmailAttachment/1/0')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Πρόσκληση ημερίδας 2026.pdf'), $pdf->headers->get('Content-Disposition'));
        $this->assertSame('sandbox', $pdf->headers->get('Content-Security-Policy'));
        $this->actingAs($user)->get('/viewEmailAttachment/1/9')->assertNotFound();

        $this->setting('allowedEmailUsers', 'someone-else');
        $this->actingAs($user)->get('/viewEmailAttachment/1/0')->assertRedirect();
    }

    public function test_unreachable_mail_server_shows_a_reference_code(): void
    {
        $this->mailbox->unreachable = true;

        $this->actingAs($this->writer())->from('/home')->get('/viewEmails')
            ->assertRedirect('/home')
            ->assertSessionHas('notification.alert-type', 'error');
        $this->assertMatchesRegularExpression('/Κωδικός σφάλματος: E-[A-Z0-9]{6}/', session('notification.message'));
    }

    /** @return list<string> */
    private function cachedFiles(): array
    {
        return Storage::disk('local')->allFiles('mail-cache');
    }

    private function htmlEml(): string
    {
        return implode("\r\n", ['From: grafeio@example.org', 'To: protocol@example.org', 'Subject: HTML',
            'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0', 'Content-Type: text/html; charset=utf-8', '',
            '<p>Καλημέρα, <b>σύσκεψη</b></p><script>alert(1)</script>', '']);
    }
}
