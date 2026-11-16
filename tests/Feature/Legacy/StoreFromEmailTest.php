<?php

namespace Tests\Feature\Legacy;

use App\Models\Attachment;
use App\Models\Protocol;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * Characterization of "register an e-mail" (ProtocolController::storeFromEmail), the only user of
 * zbateson/mail-mime-parser. Written before upgrading that library (N01) so the upgrade can't silently
 * change what gets stored. The inbox page saves each message as tmp/u<user id>/<uid>.eml (relative to the
 * working directory) and storeFromEmail parses it from there.
 */
class StoreFromEmailTest extends FeatureTestCase
{
    private const UID = 4711;

    private string $emlDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('defaultImapEmail', 'default');
        // the final step moves the message on the IMAP server; point it at a closed local port so it fails at once
        config(['imap.accounts.default.host' => '127.0.0.1', 'imap.accounts.default.port' => 1,
            'imap.accounts.default.encryption' => false, 'imap.options.fetch_timeout' => 1]);
    }

    protected function tearDown(): void
    {
        if (isset($this->emlDir)) {
            @unlink($this->emlDir.self::UID.'.eml');
            @rmdir($this->emlDir);
            @rmdir(dirname($this->emlDir)); // tmp/, only if empty
        }
        parent::tearDown();
    }

    public function test_email_is_registered_with_decoded_headers_body_and_attachment(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->actingAs($user)->post('/storeFromEmail', [
            'uid' => self::UID,
            'chk'.self::UID.'-0' => 'on',
        ]);

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
    }

    public function test_email_can_be_stored_as_html_page(): void
    {
        $this->setting('saveEmailAs', '');
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->actingAs($user)->post('/storeFromEmail', ['uid' => self::UID]);

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
        $this->actingAs($this->writer())->from('/viewEmails')->post('/storeFromEmail', ['uid' => self::UID])
            ->assertRedirect('/viewEmails')
            ->assertSessionHas('notification.alert-type', 'error');

        $this->assertSame(0, Protocol::count());
    }

    /**
     * Reported by the owner (2026-11-16): a subject longer than 255 characters (here 280, invented) is not registered;
     * the inbox just reloads with the e-mail still there. MySQL strict mode refuses the INSERT
     * ("Data too long for column 'thema'"), the controller catches it and redirects back.
     * The error text shown is the raw database message, which contains the whole SQL with the e-mail's data.
     * Fixing task (D01, subject → TEXT) replaces these with tests that the e-mail is registered.
     */
    public function test_known_bug_email_with_subject_over_255_characters_is_not_registered(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml($this->encodedWords(self::longSubject())));

        $this->actingAs($user)->from('/viewEmails')->post('/storeFromEmail', ['uid' => self::UID])
            ->assertRedirect('/viewEmails')
            ->assertSessionHas('notification.alert-type', 'error');

        $this->assertSame(0, Protocol::count(), 'nothing registered');
        $this->assertStringContainsString('Data too long', session('notification.message'),
            'raw database error (with the SQL) shown to the user');
    }

    /** Same failure through the inbox form, which posts the subject in the "thema" field (the owner's case). */
    public function test_known_bug_subject_over_255_characters_posted_by_the_inbox_form_is_not_registered(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->sampleEml());

        $this->actingAs($user)->from('/viewEmails')->post('/storeFromEmail', ['uid' => self::UID, 'thema' => self::longSubject()])
            ->assertRedirect('/viewEmails');

        $this->assertSame(0, Protocol::count());
    }

    /**
     * Owner (2026-11-16): the sender's name shows "??" in the inbox form and the sender line. Some mail programs
     * (here: Horde webmail) split a long name into encoded words in the middle of a 2-byte Greek letter; decoding
     * each word on its own yields two invalid halves → "??". Both decoders (mail-mime-parser for the form and the
     * saved protocol, App\Support\Imap\HeaderDecoder for the inbox list) do it. Fix: R5a (join the words first).
     */
    public function test_known_bug_sender_name_split_inside_a_greek_letter_becomes_question_marks(): void
    {
        $name = 'Γραφείο Σχολικών Δραστηριοτήτων';
        $cut = strpos($name, 'ί') + 1;   // inside the 2-byte letter ί
        $from = '=?utf-8?b?'.base64_encode(substr($name, 0, $cut)).'?='."\r\n ".'=?utf-8?b?'.base64_encode(substr($name, $cut)).'?=';
        $user = $this->writer();
        $this->putEml($user->id, str_replace("From: =?UTF-8?B?".base64_encode('Γραφείο Σχολικών Δραστηριοτήτων').'?=', 'From: '.$from, $this->sampleEml()));

        $this->actingAs($user)->post('/storeFromEmail', ['uid' => self::UID]);

        $this->assertSame('Γραφε??ο Σχολικών Δραστηριοτήτων <grafeio@example.org>', Protocol::firstOrFail()->in_arxi_ekdosis);
        $this->assertSame('Γραφε??ο Σχολικών Δραστηριοτήτων', \App\Support\Imap\HeaderDecoder::toUtf8($from), 'inbox list too');
    }

    /**
     * D05 (was: known_bug_emoji_in_attachment_name_leaves_a_protocol_without_its_attachment). The utf8 (3-byte)
     * tables refused the name: the protocol was created without the attachment. utf8mb4 stores it.
     */
    public function test_emoji_in_attachment_name_is_stored_with_the_attachment(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName('Αίτηση 📎 άδειας.pdf'));

        $this->actingAs($user)->from('/viewEmails')->post('/storeFromEmail', ['uid' => self::UID, 'chk'.self::UID.'-0' => 'on']);

        $this->assertSame(1, Protocol::count());
        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        $this->assertSame('Αίτηση 📎 άδειας.pdf', $pdf->name);
        $this->assertSame("%PDF-1.4\nfake\n%%EOF\n", Storage::get($pdf->savedPath));
        $this->assertStringNotContainsString('συνημμένων', (string) session('notification.message'), 'no error about attachments');
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

        $this->actingAs($user)->from('/viewEmails')->post('/storeFromEmail', ['uid' => self::UID]);

        $p = Protocol::firstOrFail();
        $this->assertSame('Συγχαρητήρια 🎉 στους μαθητές', $p->thema);
        $this->assertSame('Σύλλογος Γονέων 🌻 <grafeio@example.org>', $p->in_arxi_ekdosis);
        $this->assertSame('Καλημέρα 😊', $p->in_perilipsi);
    }

    /**
     * F08: names are cut at 120 characters, but Linux (Docker, also on Windows via WSL 2) allows 255 *bytes* per file
     * name and a Greek letter takes 2. A long Greek name without spaces + the "num-date_key_" prefix doesn't fit: the
     * attachment is recorded, the file is not written, and registration reports success (the e-mail leaves the inbox).
     */
    public function test_known_bug_long_greek_attachment_name_is_recorded_but_not_saved_on_linux(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName(str_repeat('Αίτηση', 21).'.pdf'));

        $this->actingAs($user)->post('/storeFromEmail', ['uid' => self::UID, 'chk'.self::UID.'-0' => 'on']);

        $pdf = Attachment::where('mimeType', 'application/pdf')->sole();
        $this->assertGreaterThan(255, strlen(basename($pdf->savedPath)), 'file name over 255 bytes');
        $this->assertFalse(Storage::exists($pdf->savedPath), 'recorded, but no file');
        $this->assertStringNotContainsString('συνημμένων', (string) session('notification.message'), 'no error about attachments');
    }

    /** F08: a name of only illegal characters loses its extension; an attachment without a name gets an empty one. */
    public function test_known_bug_attachment_names_without_usable_characters(): void
    {
        $user = $this->writer();
        $this->putEml($user->id, $this->emlWithAttachmentName('???.pdf'));
        $this->actingAs($user)->post('/storeFromEmail', ['uid' => self::UID, 'chk'.self::UID.'-0' => 'on']);
        $this->assertSame('pdf', Attachment::where('mimeType', 'application/pdf')->sole()->name);

        Attachment::query()->delete();
        $this->putEml($user->id, $this->emlWithAttachmentName(null));
        $this->actingAs($user)->post('/storeFromEmail', ['uid' => self::UID, 'chk'.self::UID.'-0' => 'on']);
        $this->assertSame('', Attachment::where('mimeType', 'application/pdf')->sole()->name);
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

    private function putEml(int $userId, string $eml): void
    {
        $this->emlDir = 'tmp/u'.$userId.'/';
        @mkdir($this->emlDir, 0777, true);
        file_put_contents($this->emlDir.self::UID.'.eml', $eml);
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
