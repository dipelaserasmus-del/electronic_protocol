<?php

namespace Tests\Unit;

use App\Support\Mail\EncodedWords;
use App\Support\Mail\MailParser;
use PHPUnit\Framework\TestCase;
use ZBateson\MailMimeParser\Header\HeaderConsts;

/** R5a: encoded words split inside a character ("??" in sender names). */
class EncodedWordsTest extends TestCase
{
    private const NAME = 'Γραφείο Σχολικών Δραστηριοτήτων';

    /** The bytes of $text in $charset, cut at byte $cut, as two encoded words. */
    private static function split(string $text, int $cut, string $charset = 'UTF-8', string $enc = 'B'): string
    {
        $bytes = $charset === 'UTF-8' ? $text : iconv('UTF-8', $charset, $text);
        $q = fn (string $b) => preg_replace_callback('/[^A-Za-z0-9 ]/', fn ($m) => sprintf('=%02X', ord($m[0])), $b);
        $word = fn (string $b) => "=?$charset?$enc?".($enc === 'B' ? base64_encode($b) : str_replace(' ', '_', $q($b))).'?=';

        return $word(substr($bytes, 0, $cut))."\r\n ".$word(substr($bytes, $cut));
    }

    public function test_utf8_b_words_split_inside_a_letter(): void
    {
        $this->assertSame(self::NAME, EncodedWords::decode(self::split(self::NAME, strpos(self::NAME, 'ί') + 1)));
    }

    public function test_utf8_q_words_split_inside_a_letter(): void
    {
        $this->assertSame(self::NAME, EncodedWords::decode(self::split(self::NAME, strpos(self::NAME, 'ώ') + 1, 'UTF-8', 'Q')));
    }

    public function test_single_byte_greek_charsets_decode_as_before(): void
    {
        foreach (['ISO-8859-7', 'WINDOWS-1253'] as $charset) {
            $this->assertSame(self::NAME, EncodedWords::decode(self::split(self::NAME, 9, $charset)), $charset);
        }
    }

    public function test_words_in_different_charsets_are_decoded_each_in_its_own(): void
    {
        $iso = '=?ISO-8859-7?B?'.base64_encode(mb_convert_encoding('Θέμα: ', 'ISO-8859-7', 'UTF-8')).'?=';
        $utf = '=?UTF-8?B?'.base64_encode('σύσκεψη 📎').'?=';
        $this->assertSame('Θέμα: σύσκεψη 📎', EncodedWords::decode("$iso $utf"));
    }

    public function test_plain_text_around_and_between_runs_is_kept(): void
    {
        $this->assertSame('Fwd: '.self::NAME.' <a@example.org>',
            EncodedWords::decode('Fwd: '.self::split(self::NAME, 5).' <a@example.org>'));
        $this->assertSame('no encoded words', EncodedWords::decode('no encoded words'));
    }

    public function test_rejoin_leaves_single_words_alone_and_rewrites_runs_in_whole_characters(): void
    {
        $single = 'From: =?ISO-8859-7?B?'.base64_encode(mb_convert_encoding('Γραφείο', 'ISO-8859-7', 'UTF-8')).'?= <a@example.org>';
        $this->assertSame($single, EncodedWords::rejoin($single));

        $rejoined = EncodedWords::rejoin('From: '.self::split(self::NAME, strpos(self::NAME, 'ί') + 1).' <a@example.org>');
        preg_match_all('/=\?UTF-8\?B\?([^?]*)\?=/', $rejoined, $m);
        foreach ($m[1] as $data) {
            $this->assertTrue(mb_check_encoding(base64_decode($data), 'UTF-8'), 'each word holds whole characters');
            $this->assertLessThanOrEqual(75, strlen("=?UTF-8?B?$data?="), 'RFC 2047 word length');
        }
        $this->assertSame('From: '.self::NAME.' <a@example.org>', EncodedWords::decode($rejoined));
    }

    public function test_parser_reads_the_split_name_and_keeps_the_body_unchanged(): void
    {
        $body = "Κείμενο με =?UTF-8?B?ζzz?= που δεν είναι κεφαλίδα.\r\n";
        $raw = 'From: '.self::split(self::NAME, strpos(self::NAME, 'ί') + 1)." <grafeio@example.org>\r\n"
            ."Subject: =?UTF-8?B?".base64_encode('Θέμα')."?=\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n".$body;

        $message = MailParser::parse($raw);

        $this->assertSame(self::NAME, $message->getHeader(HeaderConsts::FROM)->getAddresses()[0]->getName());
        $this->assertSame('Θέμα', $message->getHeaderValue(HeaderConsts::SUBJECT));
        $this->assertSame($body, $message->getTextContent());
    }

    public function test_normalize_returns_an_email_without_runs_byte_for_byte(): void
    {
        foreach (glob(__DIR__.'/../fixtures/emails/synthetic/*.eml') as $file) {
            $raw = file_get_contents($file);
            $headers = substr($raw, 0, (int) strpos($raw, "\r\n\r\n"));
            if (! preg_match('/\?=\s+=\?/', $headers)) {
                $this->assertSame($raw, MailParser::normalize($raw), basename($file));
            }
        }
        $this->assertSame("Subject: x\n\n=?UTF-8?B?eA==?= =?UTF-8?B?eA==?=", MailParser::normalize("Subject: x\n\n=?UTF-8?B?eA==?= =?UTF-8?B?eA==?="), 'body untouched');
    }
}
