<?php

namespace Tests\Feature\Legacy;

use App\Attachment;
use App\Protocol;
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

    private function putEml(int $userId, string $eml): void
    {
        $this->emlDir = 'tmp/u'.$userId.'/';
        @mkdir($this->emlDir, 0777, true);
        file_put_contents($this->emlDir.self::UID.'.eml', $eml);
    }

    /** Invented message in the legacy Greek encodings that trip the current inbox. */
    private function sampleEml(): string
    {
        $iso = fn (string $s) => mb_convert_encoding($s, 'ISO-8859-7', 'UTF-8');
        $subject = '=?ISO-8859-7?Q?'.str_replace(' ', '_', quoted_printable_encode($iso('Πρόσκληση σε σύσκεψη'))).'?=';
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
