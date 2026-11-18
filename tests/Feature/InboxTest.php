<?php

namespace Tests\Feature;

use App\Livewire\EmailInbox;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

        $this->actingAs($user)->get('/inbox')->assertOk()->assertSee('1–3 από 8');
        $this->assertSame([[1, 2, 3]], $this->mailbox->fetches, 'first visit: the 3 messages of the page, in one request');

        $this->actingAs($user)->get('/inbox')->assertOk();
        $this->actingAs($this->writer())->get('/inbox')->assertOk();   // also another user
        $this->assertSame([[1, 2, 3]], $this->mailbox->fetches, 'later visits download nothing');

        $this->actingAs($user)->get('/inbox?page=2')->assertOk();
        $this->assertSame([4, 5, 6], $this->mailbox->fetches[1], 'another page: only its messages');
        $this->actingAs($user)->get('/inbox?page=3')->assertOk();
        $this->assertSame([7, 8], $this->mailbox->fetches[2]);

        $this->mailbox->messages[9] = $this->mailbox->messages[1];   // a new e-mail arrives
        $this->setting('emailFetchOrderDesc', 1);
        $this->actingAs($user)->get('/inbox')->assertOk();
        $this->assertSame([9], $this->mailbox->fetches[3], 'newest first (9, 8, 7): only the new message is downloaded');
    }

    public function test_cache_keeps_only_what_is_still_in_the_inbox_and_follows_uidvalidity(): void
    {
        $user = $this->writer();
        $this->actingAs($user)->get('/inbox')->assertOk();
        $this->assertCount(3, $this->cachedFiles());

        unset($this->mailbox->messages[2]);   // registered / moved by someone
        $this->actingAs($user)->get('/inbox')->assertOk();
        $this->assertCount(3, $this->cachedFiles(), 'UID 2 gone, UID 4 now on the page');
        $this->assertFalse(collect($this->cachedFiles())->contains(fn ($f) => str_ends_with($f, '/2.eml')));

        // the server rebuilt the folder: same UIDs may now be other messages → everything is downloaded again
        $this->mailbox->validity = '2002';
        $this->mailbox->fetches = [];
        $this->actingAs($user)->get('/inbox')->assertOk();
        $this->assertSame([[1, 3, 4]], $this->mailbox->fetches);
        $this->assertSame([], array_filter($this->cachedFiles(), fn ($f) => str_contains($f, '/1001/')), 'old UIDVALIDITY removed');
    }

    /**
     * Review finding on R5a: expiry must not remove what another request is writing (".part") or has just added (a
     * UID newer than this request's list); it now runs under the download lock and leaves both alone.
     */
    public function test_expiry_leaves_files_of_concurrent_requests_alone(): void
    {
        $cache = app(\App\Services\Mail\MailCache::class);
        $cache->put('test/INBOX', '1001', 1, 'one');
        $cache->put('test/INBOX', '1001', 2, 'two (registered meanwhile)');
        $cache->put('test/INBOX', '1001', 9, 'nine (arrived after this list)');
        $dir = 'mail-cache/'.basename(dirname(Storage::disk('local')->allFiles('mail-cache')[0], 2)).'/1001';
        Storage::disk('local')->put("$dir/5.eml.abcd.part", 'being written');
        Storage::disk('local')->put("$dir/6.eml.dead.part", 'left by a crashed request');
        touch(Storage::disk('local')->path("$dir/6.eml.dead.part"), time() - 7200);

        $cache->keepOnly('test/INBOX', '1001', [1, 3]);

        $this->assertNotNull($cache->get('test/INBOX', '1001', 1));
        $this->assertNull($cache->get('test/INBOX', '1001', 2), 'no longer in the inbox');
        $this->assertNotNull($cache->get('test/INBOX', '1001', 9), 'newer than the list: kept');
        $this->assertTrue(Storage::disk('local')->exists("$dir/5.eml.abcd.part"), 'being written: kept');
        $this->assertFalse(Storage::disk('local')->exists("$dir/6.eml.dead.part"), 'hour-old leftover removed');
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

        $page = $this->actingAs($user)->get('/inbox')->assertOk();

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

        $page = $this->actingAs($this->writer())->get('/inbox')->assertOk();

        $page->assertSee('Σύλλογος Διδασκόντων Γυμνασίου Δοκιμής');
        $page->assertDontSee('??');
    }

    public function test_html_preview_is_served_to_inbox_users_only_in_a_sandbox(): void
    {
        $this->mailbox->messages = [7 => $this->htmlEml()];
        $user = $this->writer();
        $this->actingAs($user)->get('/inbox')->assertOk()->assertSee('/viewEmailHtml/7', false);

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
        $this->actingAs($user)->get('/inbox')->assertOk();

        $pdf = $this->actingAs($user)->get('/viewEmailAttachment/1/0')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Πρόσκληση ημερίδας 2026.pdf'), $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('inline;', $pdf->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $pdf->headers->get('X-Content-Type-Options'));
        // R4a: no sandbox for a PDF (Chrome refuses to show sandboxed PDFs); risky types: SafeFileTest
        $this->actingAs($user)->get('/viewEmailAttachment/1/9')->assertNotFound();

        $this->setting('allowedEmailUsers', 'someone-else');
        $this->actingAs($user)->get('/viewEmailAttachment/1/0')->assertRedirect();
    }

    /** R5c: the page says so itself (before: back to the previous page with a pop-up) */
    public function test_unreachable_mail_server_shows_a_reference_code(): void
    {
        $this->mailbox->unreachable = true;

        $page = $this->actingAs($this->writer())->get('/inbox')->assertOk();

        $page->assertSee('Η σύνδεση με τον λογαριασμό email απέτυχε');
        $this->assertMatchesRegularExpression('/Κωδικός σφάλματος: E-[A-Z0-9]{6}/', $page->getContent());
    }

    /**
     * R5c: one page of the inbox: a list of its e-mails on the left (links to each message), each message with its
     * form, its attachments (type and size from the MIME structure) and its text/HTML in sections that open and close.
     */
    public function test_page_lists_its_messages_with_attachments_and_sections(): void
    {
        $this->setting('emailNumFetch', 8);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $html = $page->html();
        foreach (range(1, 8) as $uid) {
            $this->assertStringContainsString('href="#msg-'.$uid.'"', $html, "sidebar link to $uid");
            $this->assertStringContainsString('id="msg-'.$uid.'"', $html);
        }
        $page->assertSee('Πρόσκληση ημερίδας 2026.pdf')            // 01: attachment name
            ->assertSeeHtml('data-part-toggle="text"')->assertSeeHtml('data-part-toggle="html"')
            ->assertSeeHtml('/viewEmailAttachment/1/0');
        // size of the decoded attachment, as stored (not of its base64 text)
        $size = strlen(\App\Support\Mail\MailContent::attachment(\App\Support\Mail\MailParser::parse($this->mailbox->messages[1]), '0')['content']);
        $page->assertSee(\App\Support\FileBadge::size($size));
        // the form is filled in from the e-mail (the old inbox's defaults)
        $this->assertStringStartsWith('Διεύθυνση Π.Ε. Δοκιμής <', $page->get('forms.1.in_arxi_ekdosis'));
        $this->assertSame([true], $page->get('forms.1.files'), 'attachments ticked');
    }

    public function test_pages_and_an_emptied_page_goes_back_to_the_first(): void
    {
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $this->assertSame([1, 2, 3], $page->get('uids'));
        $page->call('goToPage', 3);
        $this->assertSame([7, 8], $page->get('uids'));
        $page->assertSee('Σελίδα 3 από 3');

        // the last page's e-mails were registered by someone else meanwhile
        unset($this->mailbox->messages[7], $this->mailbox->messages[8]);
        $page->call('refresh');
        $this->assertSame(1, $page->get('page'));
        $this->assertSame([1, 2, 3], $page->get('uids'));
    }

    public function test_mark_as_read_moves_the_email_and_says_so_in_its_place(): void
    {
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->call('markRead', 2)->assertHasNoErrors()->assertSee('Μεταφέρθηκε στα αναγνωσμένα');
        $this->assertSame([[2, 'INBOX.beenRead']], $this->mailbox->moves);
        $page->call('markRead', 2);   // twice: nothing more
        $this->assertCount(1, $this->mailbox->moves);
        $page->call('markRead', 99);  // not on this page: nothing
        $this->assertCount(1, $this->mailbox->moves);

        $this->mailbox->refuseMoves = true;
        $page->call('markRead', 3)->assertHasErrors('forms.3.form');
    }

    /** R5c: registering on the page: the result in the e-mail's place, an error at its field (the e-mail stays). */
    public function test_register_shows_the_result_or_the_error_in_place(): void
    {
        $this->setting('protocolValidate', 1);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        // 01 has an attachment: without a folder it is not registered, and the page says why at the folder
        $page->call('register', 1, false)->assertHasErrors('forms.1.fakelos');
        $this->assertSame(0, \App\Models\Protocol::count());
        $page->set('forms.1.thema', '')->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasErrors('forms.1.thema');

        $page->set('forms.1.thema', 'Ημερίδα')->call('register', 1, false)->assertHasNoErrors();
        $p = \App\Models\Protocol::sole();
        $page->assertSeeHtml("Καταχωρίστηκε με αριθμό <strong>{$p->protocolnum}/{$p->etos}</strong>")->assertSeeHtml('/home/'.$p->id);
        $this->assertSame([[1, 'INBOX.inProtocol']], $this->mailbox->moves);
        $page->call('register', 1, false);   // pressed again: no second protocol
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /** The move after registering fails: the protocol is registered, the page says not to register it again. */
    public function test_failed_move_after_registering_is_a_warning(): void
    {
        $this->mailbox->refuseMoves = true;
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->set('forms.1.files.0', false)->call('register', 1, false)->assertHasNoErrors();

        $this->assertSame(1, \App\Models\Protocol::count());
        $page->assertSee('μην το καταχωρίσετε ξανά');
    }

    public function test_receipt_needs_an_address_and_is_sent_to_it(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $page->set('forms.2.reply_to_email', '')->call('register', 2, true)->assertHasErrors('forms.2.reply_to_email');
        $this->assertSame(0, \App\Models\Protocol::count());

        $page->set('forms.2.reply_to_email', 'sender@example.org')->set('forms.2.files', [])->call('register', 2, true)->assertHasNoErrors();
        $this->assertStringContainsString('απόδειξη παραλαβής σε sender@example.org', \App\Models\Protocol::sole()->paratiriseis);
    }

    /** Readers, users without inbox access and logged-out visitors get neither the page nor its actions. */
    public function test_only_writers_with_inbox_access(): void
    {
        $this->actingAs($this->writer())->get('/inbox')->assertOk()->assertSeeLivewire(EmailInbox::class);
        $this->actingAs($this->writer())->get('/viewEmails?imap_page=2')->assertRedirect('/inbox?page=2');
        $this->actingAs($this->reader())->get('/inbox')->assertRedirect();
        Livewire::actingAs($this->reader())->test(EmailInbox::class)->assertForbidden();

        $writer = $this->writer();
        $page = Livewire::actingAs($writer)->test(EmailInbox::class);
        $this->setting('allowedEmailUsers', 'someone-else');
        $page->call('register', 1, false)->assertForbidden();
        $this->actingAs($writer)->get('/inbox')->assertRedirect();
        auth()->logout();
        $this->get('/inbox')->assertRedirect('/login');
    }

    /**
     * Review of R5c: users who may choose the number (administrators, checks off) or the date (allowWriterChangeDate)
     * register e-mails too: the number is given on save, dated today. Before the fix: refused on fields the page lacks.
     */
    public function test_users_who_may_choose_number_or_date_register_too(): void
    {
        $this->setting('protocolValidate', 0);
        $this->setting('allowWriterChangeDate', 1);
        foreach ([[$this->admin(), 2], [$this->writer(), 3]] as [$user, $uid]) {
            Livewire::actingAs($user)->test(EmailInbox::class)->set("forms.$uid.files", [])->call('register', $uid, false)->assertHasNoErrors();
        }
        $this->assertSame(2, \App\Models\Protocol::count());
        foreach (\App\Models\Protocol::all() as $p) {
            $this->assertSame((int) now()->year, (int) $p->etos);
            $this->assertSame(now()->format('Y-m-d'), $p->dateOf('protocoldate')->format('Y-m-d'));
        }
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
