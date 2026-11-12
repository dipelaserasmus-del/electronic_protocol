<?php

namespace Tests\Unit;

use App\Support\Imap\HeaderDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImapHeaderDecoderTest extends TestCase
{
    public static function headers(): array
    {
        return [
            'utf-8 base64' => ['=?UTF-8?B?zpHOr8+Ezrfgz4POtw==?=', null],
            'utf-8 folded words' => ["=?UTF-8?B?zqDPgc+Mz4POus67zrfPg863IM+DzrU=?=\r\n =?UTF-8?B?IM61z4DOuc68zr/Pgc+Gz4nPhM65zrrOrg==?=", 'Πρόσκληση σε επιμορφωτική'],
            'iso-8859-7 quoted-printable' => ['=?ISO-8859-7?Q?=C5=E3=EA=FD=EA=EB=E9=EF=F2_=E3=E9=E1?=', 'Εγκύκλιος για'],
            'windows-1253 base64' => ['=?Windows-1253?B?xOnh4uni4fP06er8IOXj4/Hc9vntIOzh6Of03g==?=', 'Διαβιβαστικό εγγράφων μαθητή'],
            'raw utf-8 (non-standard)' => ['Αίτηση γονέα', 'Αίτηση γονέα'],
            'raw windows-1253 bytes (non-standard)' => [iconv('UTF-8', 'WINDOWS-1253', 'Βεβαίωση φοίτησης'), 'Βεβαίωση φοίτησης'],
            'plain ascii' => ['Fwd: report -- 50% & "quotes"', 'Fwd: report -- 50% & "quotes"'],
            'mixed text and encoded word' => ['Fwd: =?UTF-8?B?zpHOr8+Ezrfgz4POtw==?= (2)', null],
            'unknown charset does not throw (review finding)' => ['=?X-UNKNOWN?B?SGVsbG8=?=', 'Hello'],
            'unknown charset, legacy greek bytes' => ['=?x-unknown?Q?=C1=E8=DE=ED=E1?=', 'Αθήνα'],
        ];
    }

    #[DataProvider('headers')]
    public function test_decodes_header_to_utf8_without_question_marks(string $raw, ?string $expected): void
    {
        $decoded = HeaderDecoder::toUtf8($raw);

        $this->assertTrue(mb_check_encoding($decoded, 'UTF-8'), 'result must be valid UTF-8');
        $this->assertStringNotContainsString('=?', $decoded, 'no encoded words may remain');
        $this->assertStringNotContainsString('??', $decoded, 'no "??" replacement characters');
        if ($expected !== null) {
            $this->assertSame($expected, $decoded);
        }
    }

    public function test_every_synthetic_fixture_subject_decodes_to_greek(): void
    {
        $files = glob(__DIR__ . '/../fixtures/emails/synthetic/*.eml');
        $this->assertNotEmpty($files, 'synthetic fixtures missing: run ./dev.sh mail');

        foreach ($files as $file) {
            $headers = explode("\r\n\r\n", file_get_contents($file), 2)[0];
            $headers = preg_replace("/\r\n[ \t]+/", ' ', $headers);   // unfold
            preg_match('/^Subject: (.*)$/mi', $headers, $m);
            $subject = HeaderDecoder::toUtf8($m[1]);

            $this->assertMatchesRegularExpression('/\p{Greek}{3,}/u', $subject, basename($file) . ": $subject");
            $this->assertStringNotContainsString('=?', $subject, basename($file));
        }
    }
}
