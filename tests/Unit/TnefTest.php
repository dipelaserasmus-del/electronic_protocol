<?php

namespace Tests\Unit;

use App\Support\Mail\Tnef;
use PHPUnit\Framework\TestCase;
use Tests\Support\TnefBuilder;

/** R5b: winmail.dat (TNEF) built from invented content with the structure Outlook uses. */
class TnefTest extends TestCase
{
    /** Owner's example (2026-11-16), same structure: a read receipt with only MAPI properties, Greek in code page 1253. */
    public function test_read_receipt_becomes_readable_text(): void
    {
        $sent = strtotime('2026-11-12 07:25:52 UTC');
        $read = strtotime('2026-11-12 09:35:56 UTC');
        $tnef = (new TnefBuilder(1253))->messageClass('IPM.Microsoft Mail.Read Receipt')->properties([
            [TnefBuilder::STRING8, 0x1001, 'Ανάγνωση μηνύματος:'],
            [TnefBuilder::STRING8, 0x0049, 'Fwd: Πρόσκληση σε ημερίδα Δοκιμής'],
            [TnefBuilder::STRING8, 0x004D, 'Διεύθυνση Δοκιμών'],
            [TnefBuilder::STRING8, 0x0074, 'grafeio@example.test'],
            [TnefBuilder::SYSTIME, 0x004E, $sent],
            [TnefBuilder::SYSTIME, 0x0032, $read],
            [TnefBuilder::LONG, 0x0E14, 1],
            [TnefBuilder::BINARY, 0x0FF8, str_repeat("\x01", 16)],
        ])->bytes();

        $out = Tnef::decode($tnef, new \DateTimeZone('Europe/Athens'));

        $this->assertTrue($out['report']);
        $this->assertSame([], $out['attachments']);
        $this->assertSame("Αναφορά: Ανάγνωση μηνύματος:\nΘέμα: Fwd: Πρόσκληση σε ημερίδα Δοκιμής\nΑπό: Διεύθυνση Δοκιμών\n"
            ."Προς: grafeio@example.test\nΣτάλθηκε: 12/11/2026 09:25:52\nΑναγνώστηκε: 12/11/2026 11:35:56", $out['text']);
    }

    public function test_attachments_with_long_unicode_names_and_types(): void
    {
        $tnef = (new TnefBuilder(1253))->messageClass('IPM.Note')->subject('Αίτηση άδειας')->body("Καλημέρα σας,\r\nστέλνω την αίτηση.")
            ->attachment('AITHSH~1.PDF', "%PDF-1.4\nfake\n%%EOF\n", 'Αίτηση άδειας εκπαιδευτικού 📎.pdf', 'application/pdf')
            ->attachment('PINAKAS.XLS', 'xls bytes')   // only an 8.3 title, no type
            ->bytes();

        $out = Tnef::decode($tnef);

        $this->assertFalse($out['report']);
        $this->assertSame('Αίτηση άδειας', $out['subject']);
        $this->assertSame("Καλημέρα σας,\r\nστέλνω την αίτηση.", $out['text']);
        $this->assertSame([
            ['name' => 'Αίτηση άδειας εκπαιδευτικού 📎.pdf', 'mime' => 'application/pdf', 'content' => "%PDF-1.4\nfake\n%%EOF\n"],
            ['name' => 'PINAKAS.XLS', 'mime' => 'application/octet-stream', 'content' => 'xls bytes'],
        ], $out['attachments']);
    }

    /** Outlook "Rich Text": the body only as compressed RTF (LZFu, or stored as MELA), Greek as \'hh and \uN. */
    public function test_body_from_compressed_rtf(): void
    {
        $rtf = '{\rtf1\ansi\ansicpg1253\deff0{\fonttbl{\f0 Calibri;}}{\colortbl;\red0\green0\blue0;}'
            ."{\\*\\generator Test;}\\pard\\f0 \\'ca\\'e1\\'eb\\'e7\\'ec\\'dd\\'f1\\'e1 \\b \\u963?\\u945?\\u962?\\b0 ,\\par\r\n"
            .'\tab tab\{x\}\line end}';
        foreach ([true, false] as $compress) {
            $tnef = (new TnefBuilder())->messageClass('IPM.Note')
                ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf, $compress)]])->bytes();

            $this->assertSame($rtf, Tnef::decompressRtf(TnefBuilder::compressedRtf($rtf, $compress)));
            $this->assertSame("Καλημέρα σας,\n\ttab{x}\nend", Tnef::decode($tnef)['text'], $compress ? 'LZFu' : 'MELA');
        }
    }

    public function test_html_body_and_unicode_subject_from_properties(): void
    {
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')->properties([
            [TnefBuilder::UNICODE, 0x0037, 'Θέμα σε Unicode'],
            [TnefBuilder::BINARY, 0x1013, '<p>Γεια <b>σας</b></p>'],
        ])->bytes();

        $out = Tnef::decode($tnef);
        $this->assertSame('Θέμα σε Unicode', $out['subject']);
        $this->assertSame('<p>Γεια <b>σας</b></p>', $out['html']);
    }

    /** Review of R5b: an HTML body stored in Windows-1253 comes out as UTF-8 (declared charset, or the code page). */
    public function test_html_body_in_a_greek_code_page_becomes_utf8(): void
    {
        $page = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1253"></head><body>Καλημέρα</body></html>';
        $plain = '<p>Γεια σας</p>';
        foreach ([[$page, 'Καλημέρα'], [$plain, 'Γεια σας']] as [$html, $greek]) {
            $tnef = (new TnefBuilder(1253))->messageClass('IPM.Note')
                ->properties([[TnefBuilder::BINARY, 0x1013, iconv('UTF-8', 'CP1253', $html)]])->bytes();
            $out = Tnef::decode($tnef)['html'];
            $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
            $this->assertStringContainsString($greek, $out);
        }
    }

    /**
     * Review of R5b: counts are checked against the data left. Before, 29 bytes declaring a million multi-valued
     * CLSIDs made a million loops (or 4 billion): an e-mail could make the inbox time out.
     */
    public function test_huge_counts_in_a_small_file_are_refused_at_once(): void
    {
        $props = pack('V', 1).pack('vv', 0x1048, 0x0001).pack('V', 1_000_000);   // 1 property, MV CLSID, a million values
        $tnef = pack('Vv', 0x223E9F78, 1).pack('CVV', 1, 0x00069003, strlen($props)).$props.pack('v', 0);
        $started = microtime(true);
        $this->assertNull(Tnef::decode($tnef));
        $this->assertLessThan(0.1, microtime(true) - $started);

        $props = pack('V', 4_000_000_000);   // a property count beyond the data
        $this->assertNull(Tnef::decode(pack('Vv', 0x223E9F78, 1).pack('CVV', 1, 0x00069003, 4).$props.pack('v', 0)));
    }

    public function test_anything_else_is_not_tnef(): void
    {
        $this->assertNull(Tnef::decode(''));
        $this->assertNull(Tnef::decode('%PDF-1.4 not tnef'));
        $whole = (new TnefBuilder())->attachment('A.PDF', str_repeat('x', 100))->bytes();
        $this->assertNull(Tnef::decode(substr($whole, 0, -30)), 'cut short');
    }
}
