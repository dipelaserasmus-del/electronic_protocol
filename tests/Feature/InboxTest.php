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
        // R5m: the inbox page is deferred (placeholder first); these tests are about the loaded page (InboxLoadingTest: the wait)
        config(['imap.deferred_inbox' => false]);
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

    /**
     * R5v (owner, 2026-11-26): «11–20 από 55» only at the pager, not again at the top; the order next to it swaps with a
     * click (↑ oldest first, ↓ newest first), back to the first page, and stays that user's choice.
     */
    public function test_each_user_swaps_the_order_and_the_range_is_shown_once(): void
    {
        $user = $this->writer();
        $html = $this->actingAs($user)->get('/inbox?page=2')->assertOk()->getContent();
        $top = substr($html, strpos($html, 'id="inbox-count"'), 900);
        $top = substr($top, 0, strpos($top, '</span>'));
        $this->assertStringNotContainsString('από 8', $top, 'the range is at the pager only');
        $this->assertStringContainsString('data-total="8"', $top);
        $this->assertMatchesRegularExpression('~παλαιότερα πρώτα\s*<i class="bi bi-arrow-up"~u', $top);
        $this->assertStringContainsString('Εμφάνιση 4–6 από 8', $html, 'the range at the pager');

        $page = Livewire::actingAs($user)->test(EmailInbox::class)->call('gotoPage', 2)->assertSet('uids', [4, 5, 6])
            ->call('toggleOrder')
            ->assertSet('page', 1)->assertSet('uids', [8, 7, 6])
            ->assertSeeHtml('data-order="newest"')->assertSee('Αλλαγή σε παλαιότερα πρώτα');
        $this->assertSame('newest', $user->fresh()->preferences['inboxOrder']);
        $this->assertMatchesRegularExpression('~νεότερα πρώτα\s*<i class="bi bi-arrow-down"~u', $this->actingAs($user->fresh())->get('/inbox')->getContent());
        $page->call('toggleOrder')->assertSet('uids', [1, 2, 3]);   // and back
        $this->assertSame('oldest', $user->fresh()->preferences['inboxOrder']);
        Livewire::actingAs($this->writer())->test(EmailInbox::class)->assertSet('uids', [1, 2, 3]);   // another user: the setting
        $this->setting('emailFetchOrderDesc', 1);
        Livewire::actingAs($user->fresh())->test(EmailInbox::class)->assertSet('uids', [1, 2, 3]);   // the own choice wins
        Livewire::actingAs($this->writer())->test(EmailInbox::class)->assertSet('uids', [8, 7, 6]);
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

    /** R4c: an e-mail's attachments have 👁 (‹ › within the e-mail); converted ones are previewed for inbox users only */
    public function test_attachments_are_previewed_for_inbox_users_only(): void
    {
        $csv = iconv('UTF-8', 'WINDOWS-1253', "Επώνυμο;Όνομα\nΔοκιμής;Μαρία\n");
        $this->mailbox->messages = [5 => implode("\r\n", ['From: grafeio@example.org', 'To: protocol@example.org', 'Subject: CSV',
            'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0', 'Content-Type: multipart/mixed; boundary="b"', '',
            '--b', 'Content-Type: text/plain; charset=utf-8', '', 'Σας στέλνω τον πίνακα.', '--b',
            'Content-Type: text/csv; name="pinakas.csv"', 'Content-Disposition: attachment; filename="pinakas.csv"',
            'Content-Transfer-Encoding: base64', '', chunk_split(base64_encode($csv)), '--b--', ''])];
        $user = $this->writer();
        $page = Livewire::actingAs($user)->test(EmailInbox::class);
        $page->assertSeeHtml('data-preview-kind="text" data-preview-src="' . url('/previewEmailAttachment/5/0') . '"')
            ->assertSeeHtml('data-preview-group="mail-5"');

        $this->actingAs($user)->get('/inbox')->assertOk();
        $r = $this->actingAs($user)->get('/previewEmailAttachment/5/0')->assertOk()->assertSee('Δοκιμής;Μαρία');
        $this->assertStringStartsWith('sandbox;', $r->headers->get('Content-Security-Policy'));
        $this->actingAs($user)->get('/previewEmailAttachment/5/9')->assertNotFound();

        $this->setting('allowedEmailUsers', 'someone-else');
        $this->actingAs($user)->get('/previewEmailAttachment/5/0')->assertRedirect();
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
        $page->call('gotoPage', 3);
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
        $page->set('forms.1.thema', '')->set('forms.1.fakelos', 'Φ.1')->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasErrors('forms.1.thema');

        $page->set('forms.1.thema', 'Ημερίδα')->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasNoErrors();
        $p = \App\Models\Protocol::sole();
        $page->assertSeeHtml("Καταχωρίστηκε με αριθμό <strong>{$p->protocolnum}/{$p->etos}</strong>")->assertSeeHtml('/home/'.$p->id);
        $this->assertSame([[1, 'INBOX.inProtocol']], $this->mailbox->moves);
        $page->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false);   // pressed again: no second protocol
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /**
     * R5t (owner, 2026-11-25): an e-mail's attachments have no number before registering: the preview window has no
     * «Με σφραγίδα» for them; once registered, the protocol's files are listed with 👁 and the switch
     */
    public function test_files_are_stamped_only_after_registering(): void
    {
        $this->setting('protocolValidate', 1);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $other = Livewire::actingAs($this->writer())->test(EmailInbox::class);   // another writer, the same e-mails on screen
        $before = $page->html();
        $this->assertStringContainsString('data-preview-kind="pdf"', $before, 'e-mail 1 has a PDF');
        $this->assertStringNotContainsString('data-preview-stampable', $before);
        $this->assertStringNotContainsString('inbox-saved-files', $before);

        $page->set('forms.1.fakelos', 'Φ.1')->set('forms.1.thema', 'Ημερίδα')->call('register', 1, false)->assertHasNoErrors();
        $p = \App\Models\Protocol::sole();
        $html = $page->html();
        $saved = substr($html, strpos($html, 'inbox-saved-files'));
        $saved = substr($saved, 0, strpos($saved, 'inbox-done-actions'));
        $pdf = \App\Models\Attachment::where('protocol_id', $p->id)->where('name', 'like', '%.pdf')->sole();
        $this->assertStringContainsString("Τα αρχεία του πρωτοκόλλου {$p->protocolnum}/{$p->etos}", $saved);
        $this->assertStringContainsString('id="saved-stamp-1-input" data-stamp-toggle', $saved);
        $this->assertMatchesRegularExpression('~data-preview-kind="pdf" data-preview-src="'.preg_quote(url('/download/'.$pdf->id), '~').'".*?data-preview-group="saved-'.$p->id.'".*?data-preview-stampable="1"~s', $saved);
        $this->assertSame(\App\Models\Attachment::where('protocol_id', $p->id)->count(), substr_count($saved, 'inbox-saved-file"'), 'every file, the e-mail itself too');
        // the window's switch: in the page, hidden or shown by preview.js per file
        $this->assertStringContainsString('id="preview-stamp"', $this->actingAs($this->writer())->get('/inbox')->getContent());

        // review of R5t: another user told «already registered» sees its files only if they may see the protocol
        $this->setting('limitProtocolAccessList', 1);
        $other->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false);
        $this->assertStringContainsString('είχε ήδη καταχωριστεί', $other->html());
        $this->assertStringNotContainsString('inbox-saved-files', $other->html(), 'not the creator nor assigned: no file names');
        $this->setting('limitProtocolAccessList', 0);
        $this->assertStringContainsString('inbox-saved-files', $other->call('envelopeChanged')->html(), 'without the limit: as the list shows it');

        // in the trash since: no files to show
        app(\App\Services\Protocols\ProtocolTrash::class)->trash($this->admin(), $p);
        $this->assertStringNotContainsString('inbox-saved-files', $page->call('envelopeChanged')->html());
    }

    /** R5s (owner, 2026-11-25): every e-mail needs a folder, with or without attachments (before: only with ticked files) */
    public function test_every_email_needs_a_folder(): void
    {
        $this->setting('protocolValidate', 1);
        $this->setting('alwaysShowFakelosInViewEmails', 0);   // the old setting, off: no longer matters
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        // attachments unticked (registered before without a folder)
        $page->set('forms.1.files', [])->call('register', 1, false)->assertHasErrors('forms.1.fakelos');
        $this->assertSame('Για να καταχωρίσετε ένα email επιλέξτε Φάκελο.', $page->errors()->first('forms.1.fakelos'));
        // and with the receipt, and «register anyway»: the same
        $page->set('forms.1.reply_to_email', 'sender@example.org')->call('register', 1, true)->assertHasErrors('forms.1.fakelos');
        $page->call('register', 1, false, true)->assertHasErrors('forms.1.fakelos');
        $this->assertSame(0, \App\Models\Protocol::count());
        // with ticked files: said so
        $page->set('forms.2.files', ['0' => true])->call('register', 2, false)->assertHasErrors('forms.2.fakelos');

        // the service refuses it too, whoever calls it
        try {
            \App\Services\Mail\EmailRegistration::checkFolder(['fakelos' => ' '], []);
            $this->fail('no folder accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('fakelos', $e->errors());
        }

        // a folder: registered
        $page->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasNoErrors();
        $this->assertSame('Φ.1', \App\Models\Protocol::sole()->fakelos);

        // the inbox's folder picker has no «— Χωρίς φάκελο»; the setting is gone from Ρυθμίσεις
        $this->assertStringContainsString("folderPicker({ model: 'forms.2.fakelos', choices: [] })", $page->html());
        $this->assertArrayNotHasKey('alwaysShowFakelosInViewEmails', collect(\App\Support\SettingsCatalog::groups())->flatMap(fn ($g) => $g['settings'])->all());
    }

    /** R5r (owner, 2026-11-25): refused: the reason right above the buttons, and the browser is told to show it */
    public function test_a_refused_registration_says_why_above_the_buttons(): void
    {
        $this->setting('protocolValidate', 1);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->call('register', 1, false)->assertHasErrors('forms.1.fakelos')->assertDispatched('inbox-register-refused', uid: 1);
        $html = $page->html();
        $form = substr($html, strpos($html, 'id="msg-1"'), strpos($html, 'id="mail-1"') - strpos($html, 'id="msg-1"'));
        $this->assertMatchesRegularExpression('~inbox-register-error.*?Δεν καταχωρίστηκε\.</strong>\s*Για να καταχωρίσετε email με συνημμένα.*?data-goto-invalid="1".*?</div>.*?data-action="register"~s', $form);
        $this->assertStringContainsString('invalid-feedback', $form, 'and at the field, as before');
        $this->assertStringNotContainsString('inbox-register-error', substr($html, strpos($html, 'id="msg-2"')), 'only that e-mail');
        // the notice «Καταχωρίζεται…» and the spinners wait half a second (a quick refusal does not flash them)
        $this->assertMatchesRegularExpression('~inbox-saving" role="status" style="display: none" wire:loading\.flex\.delay\.longer~', $form);
        $this->assertStringNotContainsString('spinner-border-sm" wire:loading wire:target="register(1', $form);

        // fixed and registered: no message, no event
        $page->set('forms.1.fakelos', 'Φ.1')->set('forms.1.thema', 'Ημερίδα')->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)
            ->assertHasNoErrors()->assertNotDispatched('inbox-register-refused');
        $this->assertStringNotContainsString('inbox-register-error', $page->html());
    }

    /** The move after registering fails: the protocol is registered, the page says not to register it again. */
    public function test_failed_move_after_registering_is_a_warning(): void
    {
        $this->mailbox->refuseMoves = true;
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->set('forms.1.files.0', false)->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasNoErrors();

        $this->assertSame(1, \App\Models\Protocol::count());
        $page->assertSee('μην το καταχωρίσετε ξανά');
        // review of R5f: the e-mail is still in the inbox: the badge says so (8), it is not lowered to 7
        $page->assertDispatched('inbox-count', count: 8);
    }

    public function test_receipt_needs_an_address_and_is_sent_to_it(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $page->set('forms.2.reply_to_email', '')->set('forms.2.fakelos', 'Φ.1')->call('register', 2, true)->assertHasErrors('forms.2.reply_to_email');
        $this->assertSame(0, \App\Models\Protocol::count());

        $page->set('forms.2.reply_to_email', 'sender@example.org')->set('forms.2.files', [])->set('forms.2.fakelos', 'Φ.1')->call('register', 2, true)->assertHasNoErrors();
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
        $page->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertForbidden();
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
            Livewire::actingAs($user)->test(EmailInbox::class)->set("forms.$uid.files", [])->set("forms.$uid.fakelos", 'Φ.1')->call('register', $uid, false)->assertHasNoErrors();
        }
        $this->assertSame(2, \App\Models\Protocol::count());
        foreach (\App\Models\Protocol::all() as $p) {
            $this->assertSame((int) now()->year, (int) $p->etos);
            $this->assertSame(now()->format('Y-m-d'), $p->dateOf('protocoldate')->format('Y-m-d'));
        }
    }

    /**
     * R5m (owner, 2026-11-23: the cache must be safe for several users): two users with the same page open click the
     * same e-mail. The second sees who registered it and its number, and no second protocol is made.
     */
    public function test_an_email_is_registered_once_whoever_has_it_on_screen(): void
    {
        $this->setting('protocolValidate', 0);
        $anna = \App\Models\User::factory()->writer()->create(['name' => 'Άννα Δοκιμαστική']);
        $first = Livewire::actingAs($anna)->test(EmailInbox::class)->set('forms.2.files', []);
        $second = Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.2.files', []);   // opened before

        $writer = $this->writer();
        $this->actingAs($anna);   // each click as its own user (one request each in the browser)
        $first->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasNoErrors();
        $this->assertSame(1, \App\Models\Protocol::count());
        $this->actingAs($writer);
        $label = $first->get('done')[2]['label'];

        $second->set('forms.2.fakelos', 'Φ.1')->call('register', 2, true)->assertHasNoErrors()
            ->assertSee('Το email είχε ήδη καταχωριστεί (Άννα Δοκιμαστική): δεν καταχωρίστηκε ξανά.')->assertSee($label);
        $second->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false, true);   // not even "register anyway"
        $this->assertSame(1, \App\Models\Protocol::count());

        // marked read by one user: another's register does nothing either
        $third = Livewire::actingAs($writer)->test(EmailInbox::class)->set('forms.3.files', []);
        $this->actingAs($anna);
        $first->call('markRead', 3)->assertHasNoErrors();
        $this->actingAs($writer);
        $third->set('forms.3.fakelos', 'Φ.1')->call('register', 3, false)->assertSee('Το email είχε ήδη μεταφερθεί στα αναγνωσμένα (Άννα Δοκιμαστική).');
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /**
     * Review of R5m: registered, but the move failed (the e-mail stays in the inbox on purpose). «Αναγνωσμένο» still
     * moves it out, as before the claims; it is never registered again.
     */
    public function test_a_registered_email_whose_move_failed_can_still_be_marked_read(): void
    {
        $this->setting('protocolValidate', 0);
        $this->mailbox->refuseMoves = true;
        Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.2.files', [])
            ->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertSee('το πρωτόκολλο έχει ήδη καταχωριστεί');
        $this->assertSame(1, \App\Models\Protocol::count());
        $this->assertSame([], $this->mailbox->moves);

        $this->mailbox->refuseMoves = false;   // the server works again; the e-mail is still in the inbox
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $page->call('markRead', 2)->assertHasNoErrors()->assertSee('μεταφέρθηκε τώρα στα αναγνωσμένα');
        $this->assertSame([[2, (string) config('imapFolders.beenRead')]], $this->mailbox->moves);
        $this->assertSame('registered', $page->get('done')[2]['type']);

        // and it is still never registered again
        Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.2.files', [])->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false, true);
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /** R5m: while one user's registration runs, another's click on the same e-mail is told to wait */
    public function test_an_email_being_registered_by_another_user_waits(): void
    {
        $this->setting('protocolValidate', 0);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.2.files', []);
        $lock = app(\App\Services\Mail\InboxClaims::class)->lock($page->get('mailboxId'), $page->get('uidValidity'), 2);
        $this->assertTrue($lock->get());   // someone else holds it

        $page->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasErrors('forms.2.form')
            ->assertSee('Άλλος χρήστης καταχωρίζει αυτή τη στιγμή αυτό το email.');
        $page->call('markRead', 2)->assertHasErrors('forms.2.form');
        $this->assertSame(0, \App\Models\Protocol::count());

        $lock->release();
        $page->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasNoErrors();
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /**
     * R5o (owner, 2026-11-23): the form has the protocol form's sections and colours: Εισερχόμενο (green), Εξερχόμενο
     * (blue, folded); the HTML opens in a new tab from an icon next to «Εμφάνιση HTML»; no "✓ = …" note.
     */
    public function test_the_form_has_incoming_and_folded_outgoing_sections(): void
    {
        $this->setting('emailNumFetch', 8);
        $html = Livewire::actingAs($this->writer())->test(EmailInbox::class)->html();

        $this->assertStringContainsString('data-section="incoming"', $html);
        $this->assertMatchesRegularExpression('~<legend class="card-header[^"]*bg-success-subtle[^"]*">Εισερχόμενο</legend>~', $html);
        $this->assertMatchesRegularExpression('~data-section="outgoing">\s*<legend class="card-header[^"]*bg-info-subtle~', $html);
        $this->assertStringContainsString('id="f-2-out_to"', $html);
        $this->assertStringContainsString('id="f-2-out_date"', $html);
        $this->assertStringContainsString('id="f-2-out_perilipsi"', $html);
        $this->assertMatchesRegularExpression('~id="out-2" x-show="open"\s+style="display: none"~', $html);   // folded
        $this->assertStringContainsString('class="btn btn-sm btn-link px-1 text-reset inbox-html-tab"', $html);
        $this->assertStringNotContainsString('HTML σε νέα καρτέλα</', $html);
        $this->assertStringNotContainsString('αποθηκεύεται με το πρωτόκολλο', $html);
        // the list on the left shows each e-mail's place, as its header; headers are lavender (inbox-message-head)
        $this->assertSame(8, substr_count($html, 'inbox-sidebar-n'));
        $this->assertMatchesRegularExpression('~inbox-sidebar-n">1 από 8</span>~', $html);
        $this->assertMatchesRegularExpression('~inbox-sidebar-n">8 από 8</span>~', $html);
        $this->assertSame(8, substr_count($html, 'card-header d-flex flex-wrap gap-2 align-items-baseline inbox-message-head'));
        // R5p (replaces R5o's thin line): each e-mail in an envelope, «N από M», the sender and the date on its strip
        $this->assertSame(8, substr_count($html, 'class="inbox-envelope inbox-envelope-airmail"'));
        $this->assertStringNotContainsString('inbox-sep', $html);
        $this->assertMatchesRegularExpression('~<span class="inbox-envelope-n">2 από 8</span>~', $html);
    }

    /** R5p: the three looks (setting inboxEnvelope); an unknown value falls back to air mail */
    public function test_the_envelope_look_follows_the_setting(): void
    {
        $this->setting('emailNumFetch', 2);
        foreach (['paper' => 'paper', 'plain' => 'plain', 'airmail' => 'airmail', 'nonsense' => 'airmail'] as $value => $class) {
            $this->setting('inboxEnvelope', $value);
            $html = Livewire::actingAs($this->writer())->test(EmailInbox::class)->html();
            $this->assertSame(2, substr_count($html, "class=\"inbox-envelope inbox-envelope-$class\""), $value);
        }
        // the sender and the date on the strip
        $this->assertMatchesRegularExpression('~<span class="inbox-envelope-from text-truncate">[^<]+~', $html);
        $this->assertMatchesRegularExpression('~<span class="inbox-envelope-date text-nowrap">\d{2}/\d{2}/\d{4} \d{2}:\d{2}</span>~', $html);
    }

    /** R5q (owner, 2026-11-25): each user's own look, from the user menu; «none» = R5o's thin line; the setting is the default */
    public function test_each_user_chooses_the_look_and_none_is_the_line(): void
    {
        $this->setting('emailNumFetch', 2);
        $this->setting('inboxEnvelope', 'paper');
        [$a, $b] = [$this->writer(), $this->writer()];
        $look = fn ($u) => Livewire::actingAs($u->fresh())->test(EmailInbox::class)->html();

        $this->assertSame(2, substr_count($look($a), 'class="inbox-envelope inbox-envelope-paper"'), 'not chosen: the setting');
        $this->actingAs($a)->postJson('/preferences', ['key' => 'envelope', 'value' => 'none'])->assertOk();
        $html = $look($a);
        $this->assertStringNotContainsString('inbox-envelope-', $html);
        $this->assertSame(1, substr_count($html, 'class="inbox-sep"'), 'between the two e-mails only');
        $this->assertMatchesRegularExpression('~<span class="inbox-sep-label">.*?2 από 8.*?</span>~s', $html);
        $this->assertSame(2, substr_count($html, 'class="inbox-noenvelope"'));
        $this->assertSame(2, substr_count($look($b), 'class="inbox-envelope inbox-envelope-paper"'), 'another user keeps the default');

        $this->actingAs($a)->postJson('/preferences', ['key' => 'envelope', 'value' => 'plain'])->assertOk();
        $this->assertSame(2, substr_count($look($a), 'class="inbox-envelope inbox-envelope-plain"'));
        $this->actingAs($a)->postJson('/preferences', ['key' => 'envelope', 'value' => 'lace'])->assertStatus(422);
        $this->actingAs($b)->postJson('/preferences', ['key' => 'envelope', 'value' => 'airmail'])->assertOk();
        $this->actingAs($b)->postJson('/preferences', ['key' => 'envelope', 'value' => null])->assertOk();   // back to the setting
        $this->assertArrayNotHasKey('envelope', $b->fresh()->preferences ?? []);
        $this->setting('inboxEnvelope', 'none');   // «Καμία» as the default too
        $this->assertSame(1, substr_count($look($b), 'class="inbox-sep"'));

        // a choice made with the inbox open draws it again
        Livewire::actingAs($a->fresh())->test(EmailInbox::class)->dispatch('envelope-changed')->assertSee('inbox-envelope-plain', false);

        // the menu: the four looks, the chosen one pressed; R5u (owner, 2026-11-25): only on the inbox's page
        $menu = $this->actingAs($a->fresh())->get('/inbox')->getContent();
        $this->assertMatchesRegularExpression('~Αρχική σελίδα.*?Εμφάνιση των e-mail.*?data-value="none".*?Καμία.*?data-value="airmail".*?data-value="paper".*?data-value="plain"~s', $menu);
        $this->assertMatchesRegularExpression('~data-preference="envelope" data-value="plain"[^>]*aria-pressed="true"~', $menu);
        $this->assertStringNotContainsString('Εμφάνιση των e-mail', $this->actingAs($this->reader())->get('/about')->getContent());
        foreach (['/about', '/home/list', '/home'] as $page) {
            $this->assertStringNotContainsString('Εμφάνιση των e-mail', $this->actingAs($a->fresh())->get($page)->getContent(), "not on $page");
        }
    }

    /** R5o: an outgoing document from the inbox: its Εξερχόμενο fields are saved; a missing one opens the section */
    public function test_outgoing_fields_are_registered_and_an_error_opens_their_section(): void
    {
        $this->setting('protocolValidate', 1);   // the protocol's checks: an outgoing document needs its date
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.2.files', [])
            ->set('forms.2.out_to', 'Διεύθυνση Δοκιμών')->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)
            ->assertHasErrors('forms.2.out_date');
        $this->assertMatchesRegularExpression('~id="out-2" x-show="open"\s*>~', $page->html());   // open, with its error
        $this->assertSame(0, \App\Models\Protocol::count());

        $page->set('forms.2.out_date', '20/11/2026')->set('forms.2.out_perilipsi', 'Διαβιβαστικό')->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)
            ->assertHasNoErrors();
        $p = \App\Models\Protocol::sole();
        // R5o (owner): in the list on the left the number, with ✓, under the e-mail's time
        $this->assertMatchesRegularExpression('~inbox-sidebar-when">\s*<small[^>]*>[^<]+</small>(?:(?!</div>).)*?<span class="badge text-bg-success inbox-sidebar-done"><i class="bi bi-check-lg" aria-hidden="true"></i> '
            .preg_quote($p->protocolnum.'/'.$p->etos, '~').'</span>~s', $page->html());
        $this->assertSame('Διεύθυνση Δοκιμών', $p->out_to);
        $this->assertSame('2026-11-20', $p->dateOf('out_date')->format('Y-m-d'));
        $this->assertSame('Διαβιβαστικό', $p->out_perilipsi);
    }

    /**
     * Review of R5o: an outgoing document with «Διεκπεραίωση» needs «Διεκπεραιώθηκε την» (protocol checks); the inbox
     * has the field in Εξερχόμενο, so it can be registered (the error was shown with nowhere to fix it).
     */
    public function test_outgoing_with_a_handler_asks_for_the_completion_date_in_its_section(): void
    {
        $this->setting('protocolValidate', 1);
        $handler = $this->writer();
        $page = Livewire::actingAs($this->admin())->test(EmailInbox::class)->set('forms.2.files', [])
            ->set('forms.2.out_to', 'Διεύθυνση Δοκιμών')->set('forms.2.out_date', '20/11/2026')
            ->set('forms.2.handle', [$handler->id])
            ->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasErrors('forms.2.diekp_date');
        $html = $page->html();
        $this->assertStringContainsString('id="f-2-diekp_date"', $html);
        $this->assertMatchesRegularExpression('~id="out-2" x-show="open"\s*>~', $html);   // open, with its error
        // review of R5r: the date field itself is marked, with its message (the error is the e-mail's, forms.2.diekp_date),
        // so «Μετάβαση στο πεδίο» finds it
        $this->assertMatchesRegularExpression('~class="form-control text-center\s+is-invalid\s*" id="f-2-diekp_date"~', $html);
        $this->assertMatchesRegularExpression('~id="f-2-diekp_date".*?<div class="invalid-feedback">(?!Μη έγκυρη)[^<]+</div>~s', $html);
        $this->assertDoesNotMatchRegularExpression('~is-invalid\s*" id="f-2-(in_date|out_date)"~', $html, 'only the date that is wrong');
        $this->assertSame(2, substr_count($html, $page->errors()->first('forms.2.diekp_date')), 'at the field and above the buttons (R5r), no more');
        $this->assertSame(0, \App\Models\Protocol::count());

        $page->set('forms.2.diekp_date', '21/11/2026')->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasNoErrors();
        $this->assertSame('2026-11-21', \App\Models\Protocol::sole()->dateOf('diekp_date')->format('Y-m-d'));
    }

    /** R5d: the same document registered before: a notice with links, and a second explicit click registers it. */
    public function test_similar_protocol_is_shown_and_needs_a_second_click(): void
    {
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $old = \App\Models\Protocol::factory()->number(7)->create(['thema' => $page->get('forms.2.thema')]);
        $page->call('refresh')->assertSee('Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα')->assertSeeHtml('/home/'.$old->id);

        $page->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false)->assertHasErrors('forms.2.similar')->assertSeeHtml('data-action="register-anyway"');
        // R5k: the yellow button too is disabled during a save and turns while its own save runs
        $this->assertMatchesRegularExpression('~data-action="register-anyway"\s+wire:click="register\(2, false, true\)" wire:loading.attr="disabled" wire:target="register,markRead">\s*<span class="spinner-border[^"]*" wire:loading wire:target="register\(2, false, true\)"~', $page->html());
        $this->assertStringContainsString('wire:target="register(2, false), register(2, true), register(2, false, true), register(2, true, true)"', $page->html(), 'the notice of this e-mail');
        $this->assertSame(1, \App\Models\Protocol::count(), 'not registered yet');

        $page->set('forms.2.fakelos', 'Φ.1')->call('register', 2, false, true)->assertHasNoErrors();
        $this->assertSame(2, \App\Models\Protocol::count());
    }

    /** R5d: keywords as tags with suggestions from the subject; they are registered with the protocol. */
    public function test_keywords_as_tags_with_suggestions_from_the_subject(): void
    {
        \App\Models\Protocol::factory()->create(['keywords' => 'ημερίδα, Επιμόρφωση']);
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);   // 01: «Πρόσκληση σε επιμορφωτική ημερίδα εκπαιδευτικών»

        $page->assertSeeHtml('data-suggest="ημερίδα"')->assertSeeHtml('data-suggest="επιμορφωτική"');
        $page->call('addTag', 'keywords', 'ημερίδα', 1)->call('addTag', 'keywords', 'σεμινάριο, ΕΚΔΡΟΜΗ', 1)
            ->call('removeTag', 'keywords', 2, 1);
        $this->assertSame('ημερίδα, σεμινάριο', $page->get('forms.1.keywords'));
        $page->assertDontSeeHtml('data-suggest="ημερίδα"');   // chosen: no longer offered

        $page->set('forms.1.files.0', false)->set('forms.1.fakelos', 'Φ.1')->call('register', 1, false)->assertHasNoErrors();
        $this->assertSame('ημερίδα, σεμινάριο', \App\Models\Protocol::latest('id')->first()->keywords);
        $page->call('addTag', 'thema', 'x', 2)->assertStatus(400);   // only keywords
    }

    /** R5d: an e-mail without a subject offers its attachment's name, and takes it if left empty. */
    public function test_email_without_subject_takes_its_attachments_name(): void
    {
        $this->mailbox->messages = [5 => str_replace("Subject: Αίτηση\r\n", '', $this->attachmentEml('Βεβαίωση_σπουδών 2026.pdf'))];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->assertSeeHtml('placeholder="Βεβαίωση σπουδών 2026"')->assertSee('Το email δεν έχει θέμα');
        $page->set('forms.5.files.0', false)->set('forms.5.fakelos', 'Φ.1')->call('register', 5, false)->assertHasNoErrors();
        $this->assertSame('Βεβαίωση σπουδών 2026', \App\Models\Protocol::sole()->thema);
    }

    /** Review of R5d: the duplicate check uses the subject the protocol will get from the attachment. */
    public function test_duplicate_check_uses_the_subject_taken_from_the_attachment(): void
    {
        \App\Models\Protocol::factory()->number(3)->create(['thema' => 'Βεβαίωση σπουδών 2026']);
        $this->mailbox->messages = [5 => str_replace("Subject: Αίτηση\r\n", '', $this->attachmentEml('Βεβαίωση_σπουδών 2026.pdf'))];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->assertSee('Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα');
        $page->set('forms.5.files.0', false)->set('forms.5.fakelos', 'Φ.1')->call('register', 5, false)->assertHasErrors('forms.5.similar');
        $this->assertSame(1, \App\Models\Protocol::count());
    }

    /** R5e (owner, 2026-11-19): keyword suggestions from the attachments' names too, with accents from earlier subjects. */
    public function test_keyword_suggestions_from_the_file_names(): void
    {
        \App\Models\Protocol::factory()->number(1)->create(['thema' => 'Ιατρική γνωμάτευση για αναρρωτική άδεια']);
        $this->mailbox->messages = [6 => $this->attachmentEml('ΙΑΤΡΙΚΗ ΓΝΩΜΑΤΕΥΣΗ.pdf')];

        $suggested = Livewire::actingAs($this->writer())->test(EmailInbox::class)->viewData('extra')[6]['suggested'];

        $this->assertContains(['ιατρική', false], $suggested);
        $this->assertContains(['γνωμάτευση', false], $suggested);
    }

    /** R5e (owner, 2026-11-19): Ημ/νία εισερχ. is the app's date field ("9" → the 9th of this month, ◀ ▶, calendar). */
    public function test_the_incoming_date_is_the_apps_date_field(): void
    {
        $this->mailbox->messages = [6 => $this->attachmentEml('έγγραφο.pdf')];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $html = $page->html();
        $field = substr($html, strpos($html, 'for="f-6-in_date"'), 2500);
        $this->assertStringContainsString('data-date-input', $field);
        $this->assertStringContainsString('data-date-action="today"', $field);
        $this->assertStringContainsString('Ημ/νία εισερχ.', $field);

        $page->set('forms.6.fakelos', 'Φ.1')->set('forms.6.in_date', '9')->set('forms.6.fakelos', 'Φ.1')->call('register', 6, false)->assertHasNoErrors();
        $this->assertSame(now()->setDay(9)->toDateString(), \App\Models\Protocol::where('thema', 'Αίτηση')->firstOrFail()->dateOf('in_date')->toDateString());
    }

    private function attachmentEml(string $name): string
    {
        return implode("\r\n", ['From: grafeio@example.org', 'To: protocol@example.org', 'Subject: Αίτηση',
            'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0', 'Content-Type: multipart/mixed; boundary="b"', '',
            '--b', 'Content-Type: text/plain; charset=utf-8', '', 'Καλημέρα', '--b', 'Content-Type: application/pdf',
            "Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name), 'Content-Transfer-Encoding: base64', '',
            base64_encode("%PDF-1.4\nfake\n%%EOF\n"), '--b--', '']);
    }

    /** R5b: a winmail.dat is opened: its files are shown (type, size), registered instead of it, its text used. */
    public function test_winmail_files_are_shown_and_registered_instead_of_it(): void
    {
        $tnef = (new \Tests\Support\TnefBuilder(1253))->messageClass('IPM.Note')->body('Σας στέλνω την αίτηση.')
            ->attachment('AITHSH~1.PDF', "%PDF-1.4\nfake\n%%EOF\n", 'Αίτηση άδειας.pdf', 'application/pdf')
            ->attachment('PINAKAS.XLS', 'xls bytes', 'Πίνακας μαθητών.xls')->bytes();
        $this->mailbox->messages = [6 => $this->winmailEml($tnef)];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->assertSee('Αίτηση άδειας.pdf')->assertSee('Πίνακας μαθητών.xls')->assertDontSee('winmail.dat</a>', false)
            ->assertSee('Το email ήρθε ως')->assertSeeHtml('/viewEmailAttachment/6/0-1');
        $this->assertSame(['0-0' => true, '0-1' => true], $page->get('forms.6.files'));
        $this->assertSame('Σας στέλνω την αίτηση.', $page->get('forms.6.in_perilipsi'), 'the text inside, for Περίληψη');

        $file = $this->get('/viewEmailAttachment/6/0-1')->assertOk();
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Πίνακας μαθητών.xls'), $file->headers->get('Content-Disposition'));
        $this->assertSame('xls bytes', $file->getContent());
        $page->set('forms.6.fakelos', 'Φ.1')->set('forms.6.files.0-1', false)->set('forms.6.fakelos', 'Φ.1')->call('register', 6, false)->assertHasNoErrors();
        $names = \App\Models\Attachment::orderBy('id')->pluck('name')->all();
        $this->assertSame('Αίτηση άδειας.pdf', $names[1]);
        $this->assertCount(2, $names, 'the e-mail + the ticked file (not winmail.dat)');
        $pdf = \App\Models\Attachment::where('name', 'Αίτηση άδειας.pdf')->sole();
        $this->assertStringEndsWith('_0-0_Αίτηση άδειας.pdf', $pdf->savedPath);
        $this->assertSame("%PDF-1.4\nfake\n%%EOF\n", Storage::get($pdf->savedPath));
    }

    /** R5b: a read receipt (winmail.dat only): no file to store, its details are the text. A broken one: a plain file. */
    public function test_read_receipt_and_broken_winmail(): void
    {
        $receipt = (new \Tests\Support\TnefBuilder())->messageClass('IPM.Microsoft Mail.Read Receipt')
            ->properties([[\Tests\Support\TnefBuilder::STRING8, 0x1001, 'Ανάγνωση μηνύματος:'], [\Tests\Support\TnefBuilder::STRING8, 0x0049, 'Ημερίδα']])->bytes();
        $this->mailbox->messages = [6 => $this->winmailEml($receipt), 7 => $this->winmailEml('not tnef at all')];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);

        $page->assertSee('Αναφορά ανάγνωσης')->assertSee('Θέμα: Ημερίδα');
        $this->assertSame([], $page->get('forms.6.files'));
        $this->assertSame(['0' => true], $page->get('forms.7.files'), 'unreadable: winmail.dat itself, as before');
        $page->set('forms.6.fakelos', 'Φ.1')->call('register', 6, false)->assertHasNoErrors();
        $this->assertStringStartsWith('Αναφορά: Ανάγνωση μηνύματος:', \App\Models\Protocol::sole()->in_perilipsi);
    }

    /** Review of R5b: the e-mail kept as a page (saveEmailAs empty) has a winmail.dat's text, which is otherwise lost. */
    public function test_email_kept_as_a_page_has_the_winmail_text(): void
    {
        $this->setting('saveEmailAs', '');
        $tnef = (new \Tests\Support\TnefBuilder(1253))->messageClass('IPM.Note')->body('Το πλήρες κείμενο του μηνύματος από το Outlook.')->bytes();
        $this->mailbox->messages = [6 => $this->winmailEml($tnef)];

        Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.6.fakelos', 'Φ.1')->call('register', 6, false)->assertHasNoErrors();

        $page = \App\Models\Attachment::where('mimeType', 'text/html')->sole();
        $this->assertStringContainsString('Το πλήρες κείμενο του μηνύματος από το Outlook.', Storage::get($page->savedPath));
    }

    /** Second review of R5b: the page kept the inbox's text, cut at 100 000 characters; it keeps the whole text. */
    public function test_email_kept_as_a_page_has_its_whole_text(): void
    {
        $this->setting('saveEmailAs', '');
        $text = str_repeat("Γραμμή κειμένου της αναφοράς.\n", 4000).'ΤΕΛΟΣ ΚΕΙΜΕΝΟΥ';   // over MailContent::TEXT_MAX
        $this->assertGreaterThan(\App\Support\Mail\MailContent::TEXT_MAX, mb_strlen($text));
        $this->mailbox->messages = [6 => implode("\r\n", ['From: grafeio@example.org', 'To: protocol@example.org',
            'Subject: =?UTF-8?B?'.base64_encode('Μεγάλη αναφορά').'?=', 'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8', 'Content-Transfer-Encoding: base64', '', chunk_split(base64_encode($text)), ''])];

        Livewire::actingAs($this->writer())->test(EmailInbox::class)->set('forms.6.fakelos', 'Φ.1')->call('register', 6, false)->assertHasNoErrors();

        $page = \App\Models\Attachment::where('mimeType', 'text/html')->sole();
        $this->assertStringContainsString('ΤΕΛΟΣ ΚΕΙΜΕΝΟΥ', Storage::get($page->savedPath));
    }

    private function winmailEml(string $tnef): string
    {
        return implode("\r\n", ['From: =?UTF-8?B?'.base64_encode('Γραφείο Δοκιμής').'?= <grafeio@example.test>', 'To: protocol@example.org',
            'Subject: =?UTF-8?B?'.base64_encode('Αίτηση (Outlook)').'?=', 'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0',
            'Content-Type: application/ms-tnef; name="winmail.dat"', 'Content-Disposition: attachment; filename="winmail.dat"',
            'Content-Transfer-Encoding: base64', '', chunk_split(base64_encode($tnef)), '']);
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

    /**
     * R5f (owner, 2026-11-20): the red badge next to Εισερχόμενα counts every e-mail in the inbox. One count for all
     * users, asked from the mail server at most once a minute; an e-mail leaving the inbox (any user) refreshes it.
     */
    public function test_the_top_bar_badge_counts_the_inbox_for_everybody(): void
    {
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Mail\InboxCount::KEY);
        [$a, $b] = [$this->writer(), $this->writer()];

        $this->actingAs($a)->getJson('/inbox/count')->assertOk()->assertExactJson(['count' => 8]);
        $this->mailbox->messages[50] = $this->mailbox->messages[1];
        $this->actingAs($b)->getJson('/inbox/count')->assertExactJson(['count' => 8], 'the same minute: the shared count, no new question');

        // the badge, drawn on any page from the shared count
        $this->assertMatchesRegularExpression('~data-inbox-count="'.preg_quote(url('/inbox/count'), '~').'"\s+title="8 e-mail στα Εισερχόμενα"\s*>8</span>~',
            $this->actingAs($b)->get('/about')->assertOk()->getContent());

        // user A opens the inbox (counted again: 9) and marks one as read; B's next check says 8
        $page = Livewire::actingAs($a)->test(EmailInbox::class)->assertDispatched('inbox-count', count: 9);
        $page->call('markRead', 2)->assertDispatched('inbox-count', count: 8);
        $this->actingAs($b)->getJson('/inbox/count')->assertExactJson(['count' => 8]);

        // empty: no badge
        $this->mailbox->messages = [];
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Mail\InboxCount::KEY);
        $this->actingAs($b)->getJson('/inbox/count')->assertExactJson(['count' => 0]);
        $this->assertMatchesRegularExpression('~data-inbox-count="[^"]+"\s+hidden\s*>\s*</span>~', $this->actingAs($b)->get('/about')->getContent());

        // only for who uses the inbox
        $this->actingAs($this->reader())->get('/inbox/count')->assertRedirect();
        $this->actingAs($this->reader())->get('/about')->assertDontSee('data-inbox-count', false);
    }

    /** R5g (owner, 2026-11-20): every e-mail can be reached: the list pager above and below, and in the list on the left. */
    public function test_every_page_of_the_inbox_can_be_reached(): void
    {
        $user = $this->writer();
        $html = $this->actingAs($user)->get('/inbox')->assertOk()->getContent();
        $this->assertStringContainsString('id="pager-top"', $html);
        $this->assertStringContainsString('id="pager-bottom"', $html);
        $this->assertMatchesRegularExpression('~inbox-sidebar-pager.*?Σελίδα 1 από 3.*?1–3 από 8~s', $html);
        $this->assertSame(2, substr_count($html, 'data-pager="select"'), 'a page choice in both pagers');
        $this->assertMatchesRegularExpression('~data-pager="select".*?<option value="3"\s*>3</option>~s', $html, 'every page offered');

        $page = Livewire::actingAs($user)->test(EmailInbox::class);
        $page->call('nextPage')->assertSet('page', 2)->assertSet('uids', [4, 5, 6]);
        $page->call('gotoPage', 3, 'page')->assertSet('uids', [7, 8])->assertSee('Σελίδα 3 από 3');
        $page->call('previousPage')->assertSet('page', 2);
        $page->call('gotoPage', 99)->assertSet('page', 3)->assertSet('uids', [7, 8]);
        $page->call('gotoPage', 1)->assertSet('uids', [1, 2, 3]);

        // one page only: no pagers
        $this->setting('emailNumFetch', 20);
        $html = $this->actingAs($user)->get('/inbox')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="pager-top"', $html);
        $this->assertStringNotContainsString('inbox-sidebar-pager', $html);
    }

    /** R5i (owner, 2026-11-20): a long Προς list (dozens of schools) shows three and "και άλλοι N"; short ones in full. */
    public function test_a_long_list_of_recipients_is_folded(): void
    {
        $this->mailbox->messages = [
            12 => file_get_contents(base_path('tests/fixtures/emails/synthetic/12-many-recipients.eml')),
            7 => file_get_contents(base_path('tests/fixtures/emails/synthetic/07-long-subject-plain-only.eml')),
        ];
        $html = preg_replace('~<!--\[if (END)?BLOCK\]><!\[endif\]-->~', '', $this->actingAs($this->writer())->get('/inbox')->assertOk()->getContent());

        $this->assertMatchesRegularExpression('~data-addresses="to"\s+x-data="\{ all: false \}"\s*>\s*<span>school01@example.test, school02@example.test, school03@example.test</span><span x-show="all" x-cloak>, school04@example.test~', $html);
        $this->assertStringContainsString('και άλλοι 38 ▾', $html);
        $this->assertMatchesRegularExpression('~data-addresses="cc"\s*>\s*Τμήμα Δοκιμής &lt;dept@example.test&gt;, Γραφείο Δοκιμής &lt;office@example.test&gt;\s*</dd>~', $html, 'two: in full');
        $this->assertMatchesRegularExpression('~data-addresses="to"\s*>\s*Γραμματεία Σχολείου &lt;protocol@school.test&gt;\s*</dd>~', $html, 'one: in full');
    }

    /** R5j (owner, 2026-11-20): files above the buttons; the e-mail shows Από, Προς, Θέμα as an e-mail program does. */
    public function test_files_above_the_buttons_and_the_subject_in_the_mail(): void
    {
        $this->mailbox->messages = [1 => file_get_contents(base_path('tests/fixtures/emails/synthetic/04-multiple-attachments.eml'))];
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);
        $html = preg_replace('~<!--\[if (END)?BLOCK\]><!\[endif\]-->~', '', $page->html());
        $article = substr($html, strpos($html, 'id="msg-1"'));

        $files = strpos($article, 'class="inbox-attachments');
        $this->assertNotFalse($files);
        $this->assertLessThan(strpos($article, 'data-action="register"'), $files, 'the files above Καταχώριση');
        $this->assertSame(1, substr_count($article, 'class="inbox-attachments'), 'once');
        $this->assertMatchesRegularExpression('~<dt class="col-sm-2">Προς</dt>.*?<dt class="col-sm-2">Θέμα</dt><dd[^>]*inbox-mail-subject">[^<]+</dd>~s', $article);

        // read: no buttons, the files stay in the e-mail
        $page->call('markRead', 1);
        $article = substr($html = $page->html(), strpos($html, 'id="msg-1"'));
        $this->assertGreaterThan(strpos($article, 'inbox-mail small'), strpos($article, 'class="inbox-attachments'));
    }

    /** R5l (owner, 2026-11-20): a registered / read e-mail folds; «Εμφάνιση email» opens it, «Επόμενο email» goes on. */
    public function test_a_done_email_folds_with_a_way_to_open_it_or_go_on(): void
    {
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);   // UIDs 1, 2, 3 on the page
        $page->call('markRead', 1);
        $html = $page->html();
        $first = substr($html, strpos($html, 'id="msg-1"'));
        $first = substr($first, 0, strpos($first, '</article>'));

        $this->assertMatchesRegularExpression('~class="card shadow-sm inbox-message\s+is-done\s*"\s+id="msg-1"~', $html);
        $this->assertStringContainsString('data-action="show-mail"', $first);
        $this->assertStringContainsString('x-on:click="go(2)" data-action="next-mail"', $first, 'the next one still waiting');
        $this->assertStringContainsString('id="mail-1"', $first, 'the e-mail is still there, folded by CSS');

        $page->call('markRead', 3);
        $last = substr($html = $page->html(), strpos($html, 'id="msg-3"'));
        $this->assertStringNotContainsString('data-action="next-mail"', substr($last, 0, strpos($last, '</article>')), 'none left after it');
    }

    /** Review of R5f: while one request counts (holds the lock), the others do not ask the mail server too. */
    public function test_the_badge_count_is_asked_once_at_a_time(): void
    {
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Mail\InboxCount::KEY);
        \App\Services\Mail\InboxCount::$lockWait = 1;
        $lock = \Illuminate\Support\Facades\Cache::lock(\App\Services\Mail\InboxCount::KEY.':lock', 10);
        $this->assertTrue($lock->get(), 'another request is counting');
        try {
            $this->actingAs($this->writer())->getJson('/inbox/count')->assertOk()->assertExactJson(['count' => null]);
            $this->assertSame(0, $this->mailbox->searches, 'it waited, then did not ask the server itself');

            \App\Services\Mail\InboxCount::put(8);   // the other request finished
            $this->actingAs($this->writer())->getJson('/inbox/count')->assertExactJson(['count' => 8]);
            $this->assertSame(0, $this->mailbox->searches);
        } finally {
            $lock->release();
            \App\Services\Mail\InboxCount::$lockWait = 10;
        }
        \Illuminate\Support\Facades\Cache::forget(\App\Services\Mail\InboxCount::KEY);
        $this->actingAs($this->writer())->getJson('/inbox/count')->assertExactJson(['count' => 8]);
        $this->assertSame(1, $this->mailbox->searches, 'free: one request asks');
    }

    /** Review of R5f: after an action the badge is the inbox's real count (others' work, a move that failed). */
    public function test_the_badge_after_an_action_is_counted_not_worked_out(): void
    {
        $page = Livewire::actingAs($this->writer())->test(EmailInbox::class);   // 8 when the page was loaded
        unset($this->mailbox->messages[4], $this->mailbox->messages[5], $this->mailbox->messages[6], $this->mailbox->messages[7], $this->mailbox->messages[8]);   // another user
        $page->call('markRead', 1)->assertDispatched('inbox-count', count: 2);
    }
}
