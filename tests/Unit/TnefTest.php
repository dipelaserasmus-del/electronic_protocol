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

    /** Third review of R5b: a character beyond U+FFFF (emoji) comes as two \\uN halves (UTF-16), and was dropped. */
    public function test_rtf_unicode_beyond_the_basic_plane(): void
    {
        $rtf = '{\rtf1\ansi\ansicpg1253\deff0 Reference \u-10179?\u-8704? complete, \u55357?alone}';
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();

        $this->assertSame('Reference 😀 complete, alone', Tnef::decode($tnef)['text']);
    }

    /** Fifth review of R5b: punctuation written as RTF words (quotes, dashes, bullet) was dropped. */
    public function test_rtf_punctuation_words(): void
    {
        $rtf = '{\rtf1\ansi\deff0 O\rquote Connor\emdash ref. 10\endash 12, \ldblquote x\rdblquote  \lquote y\rquote \bullet  z\~w}';
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();

        $this->assertSame('O’Connor—ref. 10–12, “x” ‘y’• z w', Tnef::decode($tnef)['text']);
    }

    /** Sixth review of R5b: an RTF body cut short (the sizes promise more) made a winmail.dat look complete. */
    public function test_rtf_body_cut_short_makes_the_file_unreadable(): void
    {
        $rtf = '{\rtf1\ansi\deff0 Το πλήρες κείμενο, ως το τέλος.}';
        foreach ([true, false] as $compress) {
            $whole = TnefBuilder::compressedRtf($rtf, $compress);
            foreach ([substr($whole, 0, -5), substr($whole, 0, 17)] as $cut) {
                $tnef = (new TnefBuilder())->messageClass('IPM.Note')->properties([[TnefBuilder::BINARY, 0x1009, $cut]])->bytes();
                $this->assertNull(Tnef::decode($tnef), $compress ? 'LZFu' : 'MELA');
            }
        }
        // LZFu without its end marker
        $lzfu = TnefBuilder::compressedRtf($rtf);
        $noEnd = substr($lzfu, 0, -3).substr($lzfu, -3, 1);   // drop the final reference (2 bytes) …
        $noEnd = substr_replace($noEnd, pack('V', strlen($noEnd) - 4), 0, 4);   // … with sizes that agree
        $this->expectException(\UnexpectedValueException::class);
        Tnef::decompressRtf($noEnd);
    }

    /** Seventh review of R5b: the bytes of \\binN (a picture's data) were read as RTF, and a "{" in them hid the rest. */
    public function test_rtf_binary_data_is_skipped(): void
    {
        foreach (['{\rtf1\ansi Before {\pict\pngblip\bin1 {}After}', '{\rtf1\ansi Before {\pict\bin4 }{{}} After\bin2 {{ end}'] as $rtf) {
            $tnef = (new TnefBuilder())->messageClass('IPM.Note')
                ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();
            $texts[] = Tnef::decode($tnef)['text'];
        }
        $this->assertSame(['Before After', 'Before  After end'], $texts);   // the spaces around the picture both stay
    }

    /** Eighth review of R5b: MAPI properties ending before their declared count were taken as complete. */
    public function test_missing_declared_properties_make_the_file_unreadable(): void
    {
        $name = mb_convert_encoding('Αίτηση.pdf', 'UTF-16LE', 'UTF-8')."\0\0";
        $one = pack('vv', TnefBuilder::UNICODE, 0x3707).pack('VV', 1, strlen($name)).str_pad($name, (strlen($name) + 3) & ~3, "\0");
        foreach ([2, 3] as $declared) {
            $props = pack('V', $declared).$one;   // declares more properties than it has
            $tnef = pack('Vv', 0x223E9F78, 1).pack('CVV', 2, 0x00069005, strlen($props)).$props
                .pack('v', array_sum(unpack('C*', $props)) % 65536);
            $this->assertNull(Tnef::decode($tnef), "$declared declared, 1 present");
        }
        $props = pack('V', 1).$one;
        $this->assertNotNull(Tnef::decode(pack('Vv', 0x223E9F78, 1).pack('CVV', 2, 0x00069005, strlen($props)).$props
            .pack('v', array_sum(unpack('C*', $props)) % 65536)));
    }

    /**
     * Ninth review of R5b: Greek marked by its font (\\fcharset161) in a document declared \\ansicpg1252, as Outlook
     * writes it, came out as Latin letters. Each font's code page is used, in its group.
     */
    public function test_rtf_text_in_the_code_page_of_its_font(): void
    {
        $rtf = <<<'RTF'
{\rtf1\ansi\ansicpg1252\deff0{\fonttbl{\f0\fswiss\fcharset161 Calibri Greek;}{\f1\fnil\fcharset0 Arial;}{\f2\fnil\cpg1251 Times;}}\f0 \'ca\'e1\'eb\'e7\'ec\'dd\'f1\'e1 {\f1 caf\'e9 }\'f3\'e1\'f2 \f2\'c4\'e0}
RTF;
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();

        $this->assertSame('Καλημέρα café σας Да', Tnef::decode($tnef)['text']);
    }

    /** Tenth review of R5b: an attribute changed without its checksum (same length) was accepted as it was. */
    public function test_a_damaged_attribute_makes_the_file_unreadable(): void
    {
        $whole = (new TnefBuilder())->attachment('NOTE.TXT', 'IMPORTANT: 100 EUR')->bytes();
        $this->assertSame('IMPORTANT: 100 EUR', Tnef::decode($whole)['attachments'][0]['content']);

        $this->assertNull(Tnef::decode(str_replace('100 EUR', '900 EUR', $whole)));
    }

    /** Eleventh review of R5b: in \upr{ANSI}{\*\ud{Unicode}} the Unicode text is the one to keep. */
    public function test_rtf_upr_keeps_the_unicode_text(): void
    {
        $rtf = <<<'RTF'
{\rtf1\ansi Reference {\upr{????}{\*\ud{\u945?\u946?\u947?\u948?}}} complete{\*\ud hidden}, {\*\generator {\upr{x}{\*\ud{y}}}}end}
RTF;
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();

        $this->assertSame('Reference αβγδ complete, end', Tnef::decode($tnef)['text']);
    }

    /** Twelfth review of R5b: deeply nested RTF groups could use up the memory (a fatal error, not caught). */
    public function test_deeply_nested_rtf_is_refused(): void
    {
        $rtf = '{\rtf1\ansi '.str_repeat('{', 200_000).'x'.str_repeat('}', 200_000).'}';
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf, false)]])->bytes();
        $this->assertNull(Tnef::decode($tnef));

        $rtf = '{\rtf1\ansi '.str_repeat('{', 100).'deep'.str_repeat('}', 100).'}';   // ordinary nesting is read
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf, false)]])->bytes();
        $this->assertSame('deep', Tnef::decode($tnef)['text']);
    }

    /** Thirteenth review of R5b: a winmail.dat declaring countless tiny attachments could use up the memory. */
    public function test_too_many_attachments_are_refused(): void
    {
        $many = new TnefBuilder();
        for ($i = 0; $i < 501; $i++) {
            $many->attachment("F$i.TXT", 'x');
        }
        $this->assertNull(Tnef::decode($many->bytes()));

        $enough = new TnefBuilder();
        for ($i = 0; $i < 500; $i++) {
            $enough->attachment("F$i.TXT", 'x');
        }
        $this->assertCount(500, Tnef::decode($enough->bytes())['attachments']);
    }

    /** Fourteenth review of R5b: compressed RTF expands (up to 8 times); its size is bounded, so memory is too. */
    public function test_rtf_expanding_beyond_its_bounds_is_refused(): void
    {
        $rtf = '{\rtf1\ansi text}';
        $lzfu = TnefBuilder::compressedRtf($rtf);
        $declaring = fn (int $raw) => substr_replace($lzfu, pack('V', $raw), 4, 4);
        $error = function (string $data): ?string {
            try {
                Tnef::decompressRtf($data);

                return null;
            } catch (\UnexpectedValueException $e) {
                return $e->getMessage();
            }
        };

        $this->assertSame('TNEF: RTF too large', $error($declaring(10_000_001)));
        $this->assertSame('TNEF: RTF larger than declared', $error($declaring(5)));   // writes more than it declares
        $this->assertSame($rtf, Tnef::decompressRtf($lzfu));

        $tnef = (new TnefBuilder())->messageClass('IPM.Note')->properties([[TnefBuilder::BINARY, 0x1009, $declaring(5)]])->bytes();
        $this->assertNull(Tnef::decode($tnef));
    }

    /** Review of R5b: Greek written in the RTF as code-page bytes (not \'hh) is converted too. */
    public function test_literal_code_page_bytes_in_rtf(): void
    {
        $rtf = '{\rtf1\ansi\ansicpg1253 '.iconv('UTF-8', 'CP1253', 'Καλημέρα σας').'\par}';
        $tnef = (new TnefBuilder())->messageClass('IPM.Note')
            ->properties([[TnefBuilder::BINARY, 0x1009, TnefBuilder::compressedRtf($rtf)]])->bytes();

        $this->assertSame('Καλημέρα σας', Tnef::decode($tnef)['text']);
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
        $tnef = pack('Vv', 0x223E9F78, 1).pack('CVV', 1, 0x00069003, strlen($props)).$props.pack('v', array_sum(unpack('C*', $props)) % 65536);
        $started = microtime(true);
        $this->assertNull(Tnef::decode($tnef));
        $this->assertLessThan(0.1, microtime(true) - $started);

        $props = pack('V', 4_000_000_000);   // a property count beyond the data
        $this->assertNull(Tnef::decode(pack('Vv', 0x223E9F78, 1).pack('CVV', 1, 0x00069003, 4).$props
            .pack('v', array_sum(unpack('C*', $props)) % 65536)));
    }

    public function test_anything_else_is_not_tnef(): void
    {
        $this->assertNull(Tnef::decode(''));
        $this->assertNull(Tnef::decode('%PDF-1.4 not tnef'));
        $whole = (new TnefBuilder())->attachment('A.PDF', str_repeat('x', 100))->bytes();
        $this->assertNull(Tnef::decode(substr($whole, 0, -30)), 'cut short');
    }

    /**
     * Second review of R5b: a file ending inside the next attribute's 9-byte header (level, id, length) was taken as
     * complete, its attachment silently lost, and winmail.dat itself no longer offered.
     */
    public function test_a_file_ending_inside_an_attribute_header_is_not_read(): void
    {
        $whole = (new TnefBuilder())->attachment('A.PDF', str_repeat('x', 100))->bytes();   // attAttachData is last
        $dataAttribute = 9 + 100 + 2;
        for ($kept = 1; $kept <= 8; $kept++) {
            $this->assertNull(Tnef::decode(substr($whole, 0, strlen($whole) - $dataAttribute + $kept)), "$kept header bytes");
        }
        $this->assertNotNull(Tnef::decode($whole));
    }
}
