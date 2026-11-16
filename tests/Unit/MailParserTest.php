<?php

namespace Tests\Unit;

use App\Support\Mail\MailParser;
use PHPUnit\Framework\TestCase;

/**
 * R5a: attachment names split inside a Greek letter (owner's e-mails, 2026-11-16; structure copied, names invented).
 * Horde webmail writes long attachment names as RFC 2231 continuations (filename*0*=, filename*1*=, …) and may cut
 * a 2-byte letter between two sections, plus RFC 2047 encoded words in name="…". Decoding the pieces one by one gave
 * "??" in the attachment name.
 */
class MailParserTest extends TestCase
{
    private const NAME = 'ΑΙΤΗΣΗ ΑΝΑΡΡΩΤΙΚΗΣ ΑΔΕΙΑΣ ΔΟΚΙΜΗ (1).pdf';

    /** Percent-encoded UTF-8 of $text in sections of $size bytes (so letters are cut between sections). */
    private static function sections(string $text, int $size): array
    {
        return array_map(fn ($chunk) => implode('', array_map(fn ($c) => '%'.strtoupper(bin2hex($c)), str_split($chunk))),
            str_split($text, $size));
    }

    private static function partHeaders(string $name, int $size = 20): string
    {
        $lines = [];
        foreach (self::sections($name, $size) as $i => $s) {
            $lines[] = " filename*$i*=".($i === 0 ? "utf-8''" : '').$s.';';
        }
        $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], ';');
        $words = implode("\r\n ", array_map(fn ($c) => '=?utf-8?b?'.base64_encode($c).'?=', str_split($name, 25)));

        return "Content-Type: application/pdf;\r\n name=\"$words\"\r\n"
            ."Content-Disposition: attachment; size=12;\r\n filename=\"$words\";\r\n".implode("\r\n", $lines)."\r\n"
            ."Content-Transfer-Encoding: base64\r\n";
    }

    private static function email(string $partHeaders, bool $nested = false): string
    {
        $part = "--inner\r\n".$partHeaders."\r\n".base64_encode('%PDF-1.4 fake')."\r\n--inner--\r\n";
        $inner = $nested
            ? "Content-Type: multipart/mixed; boundary=\"inner\"\r\n\r\n".$part
            : '';

        return "From: a@example.org\r\nSubject: test\r\nUser-Agent: Horde Application Framework 5\r\nMIME-Version: 1.0\r\n"
            ."Content-Type: multipart/mixed; boundary=\"=_outer\"\r\n\r\n"
            ."--=_outer\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nΚείμενο filename*0*=x =?utf-8?b?zrE=?= =?utf-8?b?zrE=?=\r\n"
            .'--=_outer'."\r\n".($nested ? $inner : str_replace('--inner', '--=_outer', $part))
            ."--=_outer--\r\n";
    }

    public function test_letter_cut_between_rfc2231_sections_is_decoded(): void
    {
        $cut = false;
        foreach (self::sections(self::NAME, 20) as $s) {
            $cut = $cut || ! mb_check_encoding(rawurldecode($s), 'UTF-8');
        }
        $this->assertTrue($cut, 'the test data really cuts a letter');

        $message = MailParser::parse(self::email(self::partHeaders(self::NAME)));

        $this->assertSame(self::NAME, $message->getAttachmentPart(0)->getFilename());
        $this->assertStringContainsString('=?utf-8?b?zrE=?= =?utf-8?b?zrE=?=', $message->getTextContent(), 'body untouched');
    }

    public function test_parts_inside_nested_multiparts_are_handled_too(): void
    {
        $message = MailParser::parse(self::email(self::partHeaders(self::NAME), nested: true));

        $this->assertSame(self::NAME, $message->getAttachmentPart(0)->getFilename());
    }

    public function test_plain_text_sections_and_other_parameters_are_kept(): void
    {
        $headers = "Content-Type: application/pdf\r\nContent-Disposition: attachment; size=12;\r\n"
            ." filename*0=\"Αίτηση \"; filename*1*=%CE%B3%CE%BF%CE%BD%CE%AD%CE%B1.pdf; creation-date=x\r\n";

        $part = MailParser::parse(self::email($headers))->getAttachmentPart(0);

        $this->assertSame('Αίτηση γονέα.pdf', $part->getFilename());
        $this->assertSame('x', $part->getHeaderParameter('Content-Disposition', 'creation-date'));
    }
}
