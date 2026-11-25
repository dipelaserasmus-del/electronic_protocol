<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use App\Livewire\EmailInbox;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\FakeMailbox;

/**
 * Registering an inbox e-mail (R5c: the inbox page EmailInbox → EmailRegistration → ProtocolWriter; before:
 * ProtocolController::storeFromEmail). Started as the characterization written before upgrading mail-mime-parser
 * (N01); every rule found since (D01, D03, D05, F08, R5a) stays here. The page reads the e-mails from an in-memory
 * mailbox (FakeMailbox) through the mail cache.
 */
class EmailRegistrationTest extends FeatureTestCase
{
    private const UID = 4711;

    private FakeMailbox $mailbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('defaultImapEmail', 'default');
        $this->mailbox = FakeMailbox::install($this->app);
        // R5m: the inbox page is deferred (placeholder first); these tests are about the loaded page (InboxLoadingTest: the wait)
        config(['imap.deferred_inbox' => false]);
    }

    public function test_email_is_registered_with_decoded_headers_body_and_attachment(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->register($user, ['fakelos' => 'Φ.1'], ['0'])->assertHasNoErrors();

        $p = Protocol::firstOrFail();
        $this->assertSame('Πρόσκληση σε σύσκεψη', $p->thema, 'RFC 2047 subject (ISO-8859-7) decoded');
        $this->assertSame('Γραφείο Σχολικών Δραστηριοτήτων <grafeio@example.org>', $p->in_arxi_ekdosis);
        $this->assertSame('20260305', (string) $p->in_date, 'date of the e-mail');
        $this->assertSame('10:15:00', $p->in_num, 'time of the e-mail as incoming number');
        $this->assertSame('Καλημέρα σας, σας προσκαλούμε στη σύσκεψη της Πέμπτης.', $p->in_perilipsi,
            'text part (quoted-printable ISO-8859-7) decoded and trimmed');
        $this->assertSame('παρελήφθη με email', $p->paratiriseis);

        $attachments = Attachment::where('protocol_id', $p->id)->orderBy('id')->get();
        $this->assertCount(2, $attachments, 'the e-mail itself + the ticked attachment');

        [$eml, $pdf] = $attachments;
        $this->assertSame('email_20260305_101500.eml', $eml->name);
        $this->assertSame('message/rfc822', $eml->mimeType);
        $this->assertStringContainsString('Subject: =?ISO-8859-7?Q?', Storage::get($eml->savedPath),
            'the stored .eml is the original message');

        $this->assertSame('application/pdf', $pdf->mimeType);
        $this->assertSame('Πρόσκληση.pdf', $pdf->name, 'RFC 2231 file name decoded');
        $this->assertSame("%PDF-1.4\nfake pdf for tests\n%%EOF\n", Storage::get($pdf->savedPath));
        $this->assertSame([[self::UID, 'INBOX.inProtocol']], $this->mailbox->moves, 'the e-mail moves to the registered ones');

        // D03: size and hash of each stored file
        foreach ([$eml, $pdf] as $a) {
            $this->assertSame(strlen(Storage::get($a->savedPath)), $a->size);
            $this->assertSame(hash('sha256', Storage::get($a->savedPath)), $a->sha256);
        }
    }

    public function test_email_can_be_stored_as_html_page(): void
    {
        $this->setting('saveEmailAs', '');
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->register($user);

        $p = Protocol::firstOrFail();
        $html = Attachment::where('protocol_id', $p->id)->sole();
        $this->assertSame('email_20260305_101500.html', $html->name);
        $this->assertSame('text/html', $html->mimeType);
        $page = Storage::get($html->savedPath);
        $this->assertStringContainsString('Πρόσκληση σε σύσκεψη', $page);
        $this->assertStringContainsString('<b>σύσκεψη</b>', $page, 'HTML part rendered');
    }

    public function test_missing_saved_email_is_reported_and_nothing_is_created(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());
        $page = Livewire::actingAs($user)->test(EmailInbox::class);
        Storage::disk('local')->deleteDirectory('mail-cache');   // e.g. registered meanwhile by another user

        $page->call('register', self::UID, false)->assertHasErrors('forms.'.self::UID.'.form');

        $this->assertSame(0, Protocol::count());
    }

    /**
     * D01 (was: known_bug_email_with_subject_over_255_characters_is_not_registered). Owner (2026-11-16): a subject
     * longer than 255 characters (here 280, invented) was refused by MySQL ("Data too long for column 'thema'") and
     * the inbox just reloaded. The subject is now TEXT.
     */
    public function test_email_with_subject_over_255_characters_is_registered_in_full(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml($this->encodedWords(self::longSubject())));

        $this->register($user);

        $this->assertSame(self::longSubject(), Protocol::firstOrFail()->thema);
        $this->assertSame(280, mb_strlen(Protocol::firstOrFail()->thema));
    }

    /** D01 (was: known_bug_subject_over_255_characters_posted_by_the_inbox_form_is_not_registered), the owner's case. */
    public function test_subject_over_255_characters_posted_by_the_inbox_form_is_registered(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->register($user, ['thema' => self::longSubject()]);

        $this->assertSame(self::longSubject(), Protocol::firstOrFail()->thema);
    }

    /** D01: a sender whose name + address is longer than 255 characters (column in_arxi_ekdosis, now TEXT). */
    public function test_long_sender_name_is_registered_in_full(): void
    {
        $name = trim(str_repeat('Σύλλογος Γονέων και Κηδεμόνων Δημοτικού Σχολείου ', 6));
        $user = $this->writer();
        $this->putEml($user->id, str_replace('From: grafeio@example.org', 'From: '.$this->encodedWords($name).' <grafeio@example.org>',
            $this->emlWithAttachmentName('a.pdf')));

        $this->register($user, [], []);

        $this->assertSame($name.' <grafeio@example.org>', Protocol::firstOrFail()->in_arxi_ekdosis);
        $this->assertGreaterThan(255, mb_strlen(Protocol::firstOrFail()->in_arxi_ekdosis));
    }

    /**
     * D01: an incoming number longer than its column was refused by the database, and the message showed the raw
     * database error (the whole SQL statement with the e-mail's data). R5c: ProtocolWriter checks it first and the
     * page says so at the field; nothing is registered and the e-mail stays.
     */
    public function test_too_long_value_is_shown_at_its_field_never_the_sql(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $page = $this->register($user, ['in_num' => str_repeat('1', 300)], [])->assertHasErrors('forms.'.self::UID.'.in_num');

        $this->assertSame(0, Protocol::count());
        $this->assertSame([], $this->mailbox->moves, 'the e-mail stays in the inbox');
        $html = $page->html();
        $this->assertStringContainsString('Έως 255 χαρακτήρες.', $html);
        foreach (['SQLSTATE', 'insert into', 'Data too long'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $html);
        }
    }

    /**
     * R5a (was: known_bug_sender_name_split_inside_a_greek_letter_becomes_question_marks). Owner (2026-11-16): the
     * sender's name showed "??". Some mail programs (here: Horde webmail) split a long name into encoded words in the
     * middle of a 2-byte Greek letter; decoding each word on its own gave two invalid halves. Both decoders
     * (mail-mime-parser for the form and the saved protocol, HeaderDecoder for the inbox list) now decode adjacent
     * words together. The stored .eml stays exactly as received.
     */
    public function test_sender_name_split_inside_a_greek_letter_is_decoded(): void
    {
        $name = 'Γραφείο Σχολικών Δραστηριοτήτων';
        $cut = strpos($name, 'ί') + 1;   // inside the 2-byte letter ί
        $from = '=?utf-8?b?'.base64_encode(substr($name, 0, $cut)).'?='."\r\n ".'=?utf-8?b?'.base64_encode(substr($name, $cut)).'?=';
        $user = $this->writer();
        $this->putEml($user->id, str_replace("From: =?UTF-8?B?".base64_encode('Γραφείο Σχολικών Δραστηριοτήτων').'?=', 'From: '.$from, $this->sampleEml()));

        $this->register($user, [], []);

        $p = Protocol::firstOrFail();
        $this->assertSame('Γραφείο Σχολικών Δραστηριοτήτων <grafeio@example.org>', $p->in_arxi_ekdosis);
        $this->assertSame('Γραφείο Σχολικών Δραστηριοτήτων', \App\Support\Imap\HeaderDecoder::toUtf8($from), 'inbox list too');
        $eml = Attachment::where('protocol_id', $p->id)->where('mimeType', 'message/rfc822')->sole();
        $this->assertStringContainsString('From: '.$from, Storage::get($eml->savedPath), 'stored e-mail unchanged');
    }

    /**
     * R5a, owner's e-mail (2026-11-16): an attachment name written as RFC 2231 continuations with a Greek letter cut
     * between two of them (Horde webmail) was saved as "ΑΔΕ??ΑΣ". Synthetic e-mail 10 has the same structure.
     */
    public function test_attachment_name_cut_inside_a_letter_is_saved_correctly(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, file_get_contents(base_path('tests/fixtures/emails/synthetic/10-attachment-name-split-inside-letter.eml')));

        $this->register($user, ['fakelos' => 'Φ.1'], ['0']);

        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        // decoded name 'ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ (1).pdf'; the file-name filter turns ( ) into - as always
        $this->assertSame('ΑΙΤΗΣΗ ΑΔΕΙΑΣ ΕΚΠΑΙΔΕΥΤΙΚΟΥ ΔΟΚΙΜΗ -1-.pdf', $pdf->name);
        $this->assertTrue(Storage::exists($pdf->savedPath));
    }

    /**
     * R5a: registration is all-or-nothing. Here the attachment can't be written (a folder is in the way of its file
     * name), after the e-mail itself was written: before, the protocol stayed without its attachment while the e-mail
     * stayed in the inbox, so registering it again made a second protocol. Now nothing is left behind.
     */
    public function test_attachment_that_cannot_be_saved_leaves_nothing_behind(): void
    {
        $next = app(\App\Services\ProtocolNumbering::class)->next((int) now()->year);
        $blocked = 'arxeio/Φ.1/'.$next.'-'.now()->format('Ymd').'_0_Πρόσκληση.pdf';
        Storage::makeDirectory($blocked);
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $page = $this->register($user, ['fakelos' => 'Φ.1'], ['0'])->assertHasErrors('forms.'.self::UID.'.form');

        $this->assertSame(0, Protocol::count(), 'no protocol');
        $this->assertSame(0, Attachment::withTrashed()->count(), 'no attachment rows, not even in the trash (review finding)');
        $this->assertSame([], Storage::allFiles('arxeio'), 'the e-mail file written first is removed again');
        $this->assertSame([], $this->mailbox->moves, 'the e-mail stays in the inbox');
        $message = $page->errors()->first('forms.'.self::UID.'.form');
        $this->assertStringContainsString('δεν καταχωρίστηκε', $message);
        $this->assertStringContainsString('παραμένει στα εισερχόμενα', $message);
        $this->assertMatchesRegularExpression('/E-[A-Z0-9]{6}/', $message);

        // the e-mail can be registered once the problem is gone (the form kept what was chosen): exactly one protocol
        Storage::deleteDirectory($blocked);
        $page->call('register', self::UID, false)->assertHasNoErrors();
        $this->assertSame(1, Protocol::count());
        $this->assertSame(2, Attachment::count());
    }

    /**
     * D05 (was: known_bug_emoji_in_attachment_name_leaves_a_protocol_without_its_attachment). The utf8 (3-byte)
     * tables refused the name: the protocol was created without the attachment. utf8mb4 stores it.
     */
    public function test_emoji_in_attachment_name_is_stored_with_the_attachment(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName('Αίτηση 📎 άδειας.pdf'));

        $this->register($user, ['fakelos' => 'Φ.1'], ['0'])->assertHasNoErrors();

        $this->assertSame(1, Protocol::count());
        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        $this->assertSame('Αίτηση 📎 άδειας.pdf', $pdf->name);
        $this->assertSame("%PDF-1.4\nfake\n%%EOF\n", Storage::get($pdf->savedPath));
    }

    /** D05: owner's case, an e-mail with an emoji in the subject, the sender's name and the text could not be registered. */
    public function test_email_with_emoji_in_subject_sender_and_text_is_registered(): void
    {
        $eml = str_replace(
            ['Subject: Αίτηση', 'From: grafeio@example.org', 'Καλημέρα'],
            ['Subject: =?UTF-8?B?'.base64_encode('Συγχαρητήρια 🎉 στους μαθητές').'?=',
                'From: =?UTF-8?B?'.base64_encode('Σύλλογος Γονέων 🌻').'?= <grafeio@example.org>', 'Καλημέρα 😊'],
            $this->emlWithAttachmentName('a.pdf'));
        $user = $this->writer();
        $this->putEml($user->id, $eml);

        $this->register($user, [], []);

        $p = Protocol::firstOrFail();
        $this->assertSame('Συγχαρητήρια 🎉 στους μαθητές', $p->thema);
        $this->assertSame('Σύλλογος Γονέων 🌻 <grafeio@example.org>', $p->in_arxi_ekdosis);
        $this->assertSame('Καλημέρα 😊', $p->in_perilipsi);
    }

    /**
     * F08 (was: known_bug_long_greek_attachment_name_can_not_be_registered_on_linux; before R5a: "…is recorded but not
     * saved"). Linux allows 255 *bytes* per file name and a Greek letter takes 2: a long Greek name without spaces +
     * the "num-date_key_" prefix didn't fit. The stored name is now cut by bytes, keeping the extension; the name
     * shown in the app stays whole.
     */
    public function test_long_greek_attachment_name_is_registered_with_a_shortened_file_name(): void
    {
        $name = str_repeat('Αίτηση', 21).'.pdf';   // 130 characters, 256 bytes
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName($name));

        $this->register($user, ['fakelos' => 'Φ.1'], ['0']);

        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        $this->assertSame($name, $pdf->name, 'shown with its whole name');
        $file = basename($pdf->savedPath);
        $this->assertLessThanOrEqual(255, strlen($file));
        $this->assertTrue(mb_check_encoding($file, 'UTF-8'), 'no letter cut in half');
        $this->assertStringEndsWith('.pdf', $file);
        $this->assertSame("%PDF-1.4\nfake\n%%EOF\n", Storage::get($pdf->savedPath));
    }

    /**
     * F08 (was: known_bug_attachment_names_without_usable_characters): a name of only illegal characters lost its
     * extension ("pdf"), an attachment without a name got an empty one. Now both are "συνημμένο.pdf".
     */
    public function test_attachment_names_without_usable_characters(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName('???.pdf'));
        $this->register($user, ['fakelos' => 'Φ.1'], ['0']);
        $this->assertSame('συνημμένο.pdf', Attachment::where('mimeType', 'application/pdf')->sole()->name);

        Attachment::query()->delete();
        $this->putEml($user->id, $this->emlWithAttachmentName(null));
        // another subject and incoming number: the same ones would wait for "register anyway" (R5d)
        $this->register($user, ['fakelos' => 'Φ.1', 'thema' => 'Αίτηση χωρίς όνομα αρχείου', 'in_num' => '2'], ['0'])->assertHasNoErrors();
        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        $this->assertSame('συνημμένο.pdf', $pdf->name, 'extension from the attachment type');
        $this->assertStringEndsWith('_0_συνημμένο.pdf', $pdf->savedPath);
    }

    /** Invented plain message with one small PDF; null = attachment without a file name. */
    private function emlWithAttachmentName(?string $name): string
    {
        $disposition = $name === null ? 'attachment' : "attachment; filename*=UTF-8''".rawurlencode($name);

        return implode("\r\n", ['From: grafeio@example.org', 'To: protocol@example.org', 'Subject: Αίτηση',
            'Date: Thu, 05 Mar 2026 10:15:00 +0200', 'MIME-Version: 1.0', 'Content-Type: multipart/mixed; boundary="b"', '',
            '--b', 'Content-Type: text/plain; charset=utf-8', '', 'Καλημέρα', '--b', 'Content-Type: application/pdf',
            'Content-Disposition: '.$disposition, 'Content-Transfer-Encoding: base64', '',
            base64_encode("%PDF-1.4\nfake\n%%EOF\n"), '--b--', '']);
    }

    /** Invented Greek subject of 280 characters. */
    private static function longSubject(): string
    {
        $s = trim(str_repeat('Πρόσκληση εκδήλωσης ενδιαφέροντος για επιμορφωτικό πρόγραμμα ', 5));
        return mb_substr($s, 0, 280);
    }

    /** RFC 2047 UTF-8 B-encoded words (each ≤ 75 characters), as mail programs split a long subject. */
    private function encodedWords(string $text): string
    {
        $words = [];
        foreach (mb_str_split($text, 20) as $chunk) {
            $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?=';
        }
        return implode("\r\n ", $words);
    }

    /** The e-mail is the one message of the inbox (UID self::UID). */
    private function putEml(int $userId, string $eml): void
    {
        // another message under the same UID: a real server changes UIDVALIDITY then (UIDs are never reused within one;
        // R5m remembers per UID what was done with an e-mail)
        if ($this->lastEml !== null && $this->lastEml !== $eml) {
            $this->mailbox->validity = (string) ((int) $this->mailbox->validity + 1);
        }
        $this->lastEml = $eml;
        $this->mailbox->messages = [self::UID => $eml];
    }

    private ?string $lastEml = null;

    /**
     * Opens the inbox as $user, changes the fields given, keeps ticked only the attachments $keys (none by default, as
     * the old tests posted them; null: as the page offers them, all ticked) and presses «Καταχώριση».
     *
     * @param list<string>|null $keys
     */
    private function register(User $user, array $fields = [], ?array $keys = [], bool $receipt = false): Testable
    {
        $page = Livewire::actingAs($user)->test(EmailInbox::class);
        $fields += ['fakelos' => 'Φ.1'];   // R5s: every e-mail needs a folder (a test about the folder gives its own)
        foreach ($fields as $field => $value) {
            $page->set('forms.'.self::UID.'.'.$field, $value);
        }
        if ($keys !== null) {
            foreach (array_keys($page->get('forms.'.self::UID.'.files')) as $key) {
                $page->set('forms.'.self::UID.'.files.'.$key, in_array((string) $key, $keys, true));
            }
        }

        return $page->call('register', self::UID, $receipt);
    }

    /** Invented message in the legacy Greek encodings that trip the current inbox. */
    private function sampleEml(?string $subjectHeader = null): string
    {
        $iso = fn (string $s) => mb_convert_encoding($s, 'ISO-8859-7', 'UTF-8');
        $subject = $subjectHeader ?? '=?ISO-8859-7?Q?'.str_replace(' ', '_', quoted_printable_encode($iso('Πρόσκληση σε σύσκεψη'))).'?=';
        $from = '=?UTF-8?B?'.base64_encode('Γραφείο Σχολικών Δραστηριοτήτων').'?=';
        $text = quoted_printable_encode($iso("  Καλημέρα σας, σας προσκαλούμε στη σύσκεψη της Πέμπτης.\r\n\r\n"));
        $html = base64_encode('<p>Καλημέρα σας, σας προσκαλούμε στη <b>σύσκεψη</b> της Πέμπτης.</p>');
        $pdf = chunk_split(base64_encode("%PDF-1.4\nfake pdf for tests\n%%EOF\n"));
        $pdfName = "UTF-8''".rawurlencode('Πρόσκληση.pdf');

        return implode("\r\n", [
            'From: '.$from.' <grafeio@example.org>',
            'To: protocol@example.org',
            'Subject: '.$subject,
            'Date: Thu, 05 Mar 2026 10:15:00 +0200',
            'Message-ID: <n01-test@example.org>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="mixed"',
            '',
            '--mixed',
            'Content-Type: multipart/alternative; boundary="alt"',
            '',
            '--alt',
            'Content-Type: text/plain; charset=ISO-8859-7',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            $text,
            '--alt',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $html,
            '--alt--',
            '',
            '--mixed',
            'Content-Type: application/pdf; name*='.$pdfName,
            'Content-Disposition: attachment; filename*='.$pdfName,
            'Content-Transfer-Encoding: base64',
            '',
            $pdf,
            '--mixed--',
            '',
        ]);
    }
}
