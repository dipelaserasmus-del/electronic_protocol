<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Attachments\Preview;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * R4c: previewing attachments in the application. PDFs and images use the download (SafeFileTest); text, Word and
 * spreadsheets are converted on the server (PhpWord, PhpSpreadsheet) into a sandboxed page.
 */
class PreviewTest extends FeatureTestCase
{
    private ?Protocol $protocol = null;

    private function attach(string $name, string $contents, ?Protocol $protocol = null): Attachment
    {
        $protocol ??= $this->protocol ??= Protocol::factory()->number(1)->create();
        $a = Attachment::factory()->make(['protocol_id' => $protocol->id, 'name' => $name]);
        $a->savedPath = 'arxeio/test/' . uniqid() . '_' . $name;
        Storage::put($a->savedPath, $contents);
        $a->save();
        return $a;
    }

    private function docx(string ...$paragraphs): string
    {
        $word = new \PhpOffice\PhpWord\PhpWord();
        $section = $word->addSection();
        foreach ($paragraphs as $p) {
            $section->addText($p);
        }
        return $this->written(\PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007'));
    }

    private function odt(string $text): string
    {
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->addSection()->addText($text);
        return $this->written(\PhpOffice\PhpWord\IOFactory::createWriter($word, 'ODText'));
    }

    /** @param list<list<mixed>> $rows */
    private function sheet(array $rows, string $type = 'Xlsx', ?string $second = null): string
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->setTitle('Μαθητές')->fromArray($rows);
        if ($second !== null) {
            $book->createSheet()->setTitle('Δεύτερο')->setCellValue('A1', $second);
        }
        return $this->written(\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, $type));
    }

    private function written(object $writer): string
    {
        $file = tempnam(sys_get_temp_dir(), 'doc');
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);   // a valid document with "<script>" as its text
        $writer->save($file);
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(false);
        $bytes = file_get_contents($file);
        unlink($file);
        return $bytes;
    }

    private function assertSandboxed(\Illuminate\Testing\TestResponse $r): void
    {
        $r->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringStartsWith('sandbox;', $csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString('allow-scripts', $csp);
    }

    public function test_kinds(): void
    {
        $this->assertSame(['pdf', 'image', 'image', 'text', 'text', 'word', 'word', 'sheet', 'sheet', 'sheet', 'mail', null, null, null],
            array_map([Preview::class, 'kind'], ['a.PDF', 'b.jpg', 'c.webp', 'd.txt', 'e.csv', 'f.docx', 'g.odt', 'h.xlsx', 'i.xls', 'j.ods', 'm.EML', 'k.doc', 'l.zip', 'χωρίς']));
        $this->assertTrue(Preview::converted('mail'));
    }

    public function test_word_documents_are_converted_and_their_text_escaped(): void
    {
        $admin = $this->admin();
        $a = $this->attach('Πρόσκληση.docx', $this->docx('Πρόσκληση σε ημερίδα', '<script>alert(1)</script><img src=x onerror=alert(2)>'));
        $r = $this->actingAs($admin)->get('/preview/' . $a->id);
        $this->assertSandboxed($r);
        $r->assertSee('Πρόσκληση σε ημερίδα')->assertSee('alert(1)')   // shown as text …
            ->assertDontSee('<script>alert(1)', false)->assertDontSee('<img src=x', false);   // … never as a tag

        $odt = $this->attach('Πρακτικό.odt', $this->odt('Πρακτικό συνεδρίασης'));
        $this->assertSandboxed($this->actingAs($admin)->get('/preview/' . $odt->id)->assertSee('Πρακτικό συνεδρίασης'));
    }

    public function test_spreadsheets_show_every_sheet_with_the_stored_values_and_at_most_the_first_rows(): void
    {
        $rows = [['Όνομα', 'Τάξη', 'Σύνολο']];
        foreach (range(1, Preview::MAX_ROWS + 20) as $i) {
            $rows[] = ["Μαθητής $i", 'Γ', $i];
        }
        $rows[1][3] = '=1/0';   // a formula: never calculated here
        $rows[2][3] = '=C2+C3';
        $a = $this->attach('Μαθητές.xlsx', $this->sheet($rows, 'Xlsx', 'Φύλλο δύο'));
        $r = $this->actingAs($this->admin())->get('/preview/' . $a->id);
        $this->assertSandboxed($r);
        $r->assertSee('Μαθητής 1<', false)->assertSee('Μαθητής ' . (Preview::MAX_ROWS - 1) . '<', false)
            ->assertDontSee('Μαθητής ' . (Preview::MAX_ROWS + 5) . '<', false)
            ->assertSee('Φύλλο δύο')->assertSee('Δεύτερο')
            ->assertSee('#DIV/0!')->assertDontSee('=C2+C3')->assertDontSee('=1/0');   // the values the file saved

        $ods = $this->attach('Πίνακας.ods', $this->sheet([['Σχολείο', 'Μαθητές'], ['Δημοτικό Δοκιμής', 120]], 'Ods'));
        $this->assertSandboxed($this->actingAs($this->admin())->get('/preview/' . $ods->id)->assertSee('Δημοτικό Δοκιμής'));
        $xls = $this->attach('Παλιό.xls', $this->sheet([['Παλιό αρχείο Excel']], 'Xls'));
        $this->actingAs($this->admin())->get('/preview/' . $xls->id)->assertSee('Παλιό αρχείο Excel');
    }

    public function test_text_is_escaped_and_greek_of_older_programs_is_read(): void
    {
        $utf8 = $this->attach('σημείωμα.txt', "\xEF\xBB\xBFΚαλημέρα <b>σας</b>\nδεύτερη γραμμή");
        $this->assertSandboxed($r = $this->actingAs($this->admin())->get('/preview/' . $utf8->id));
        $r->assertSee('Καλημέρα &lt;b&gt;σας&lt;/b&gt;', false)->assertDontSee("\xEF\xBB\xBF", false);

        $greek = $this->attach('παλιό.csv', iconv('UTF-8', 'WINDOWS-1253', "Επώνυμο;Όνομα\nΠαπαδόπουλος;Γιώργος"));
        $this->actingAs($this->admin())->get('/preview/' . $greek->id)->assertSee('Παπαδόπουλος;Γιώργος');

        // review of R4c: a long UTF-8 file cut inside a character stays UTF-8 (not read as Windows-1253)
        $cut = $this->attach('ελληνικά.txt', 'a' . str_repeat('α', 600000));
        $this->actingAs($this->admin())->get('/preview/' . $cut->id)->assertSee('aααα')->assertDontSee('Ξ±');
        $this->assertSame('aα', Preview::wholeCharacters("a\xCE\xB1\xCE"));
        $this->assertSame("a\xCE\xB1", Preview::wholeCharacters("a\xCE\xB1"));
        $this->assertSame('a€', Preview::wholeCharacters("a\xE2\x82\xAC\xE2\x82"));

        // only the start of a long file
        $long = $this->attach('μεγάλο.txt', str_repeat("γραμμή κειμένου\n", 70000));
        $this->actingAs($this->admin())->get('/preview/' . $long->id)->assertSee('εμφανίζεται μόνο η αρχή του αρχείου');
    }

    public function test_too_big_broken_and_other_files_get_a_message(): void
    {
        $admin = $this->admin();
        config(['attachments.preview_max_kb' => 1]);
        $big = $this->attach('μεγάλο.docx', str_repeat('x', 2048));
        $this->assertSandboxed($this->actingAs($admin)->get('/preview/' . $big->id)->assertSee('πολύ μεγάλο για προεπισκόπηση'));
        config(['attachments.preview_max_kb' => 10240]);

        $broken = $this->attach('χαλασμένο.xlsx', 'not a spreadsheet at all');
        $this->assertSandboxed($this->actingAs($admin)->get('/preview/' . $broken->id)->assertSee('Δεν ήταν δυνατή η προεπισκόπηση'));
        $zip = $this->attach('αρχεία.zip', 'PK');
        $this->actingAs($admin)->get('/preview/' . $zip->id)->assertSee('δεν εμφανίζεται εδώ');

        $gone = $this->attach('χάθηκε.docx', 'x');
        Storage::delete($gone->savedPath);
        $this->actingAs($admin)->get('/preview/' . $gone->id)->assertNotFound();
    }

    /** the same access as the download: readers yes; a limited user only for what they may see */
    public function test_access_is_the_downloads(): void
    {
        $a = $this->attach('σημείωμα.txt', 'Καλημέρα');
        $this->actingAs($this->reader())->get('/preview/' . $a->id)->assertOk()->assertSee('Καλημέρα');

        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        $this->actingAs($writer)->get('/preview/' . $a->id)->assertNotFound();
        $this->actingAs($writer)->get('/download/' . $a->id)->assertNotFound();
        $own = $this->attach('δικό.txt', 'Δικό μου', Protocol::factory()->number(2)->create(['user_id' => $writer->id]));
        $this->actingAs($writer)->get('/preview/' . $own->id)->assertOk()->assertSee('Δικό μου');

        auth()->logout();
        $this->get('/preview/' . $a->id)->assertRedirect('/login');
    }

    public function test_the_form_offers_a_preview_of_each_file(): void
    {
        $p = Protocol::factory()->number(3)->create();
        $pdf = $this->attach('απόφαση.pdf', "%PDF-1.4\n", $p);
        $docx = $this->attach('πρόσκληση.docx', 'x', $p);
        $zip = $this->attach('αρχεία.zip', 'PK', $p);
        Attachment::factory()->create(['protocol_id' => $p->id, 'name' => null, 'ada' => 'ΩΡΖ']);   // only ΑΔΑ: no 👁

        $html = Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])->html();
        $this->assertSame(2, substr_count($html, 'data-preview-group="protocol-' . $p->id . '"'), 'the zip is not in ‹ ›');
        $this->assertStringContainsString('data-preview-kind="pdf" data-preview-src="' . url('/download/' . $pdf->id) . '"', $html);
        $this->assertStringContainsString('data-preview-kind="word" data-preview-src="' . url('/preview/' . $docx->id) . '"', $html);
        // R4j (owner, 2026-11-25): no preview for this type: a grey 👁 that is not a button
        $this->assertStringNotContainsString('data-preview-kind="none"', $html);
        $this->assertSame(1, substr_count($html, 'data-preview-none'));
        $this->assertMatchesRegularExpression('~<span class="preview-none" data-preview-none\s+title="Δεν υπάρχει προεπισκόπηση[^"]*" role="img"\s+aria-label="Χωρίς προεπισκόπηση: «αρχεία\.zip»">~', $html);
        $this->assertSame(2, substr_count($html, 'data-preview-kind='), 'buttons only for the pdf and the docx');
        // R5t: both can be stamped, so the window offers «Με σφραγίδα» for them
        $this->assertSame(2, substr_count($html, 'data-preview-stampable="1"'));

        // the modal is on every page of the new layout, once
        $this->assertSame(1, substr_count($this->actingAs($this->reader())->get('/home/' . $p->id)->getContent(), 'id="preview-modal"'));
    }

    /** an e-mail file: HTML (with what must never run or load) and a file, or text only */
    private function eml(?string $html, string $text = 'Καλημέρα σας.'): string
    {
        $head = "From: =?UTF-8?B?" . base64_encode('Γραμματεία Δοκιμών') . "?= <office@example.test>\r\nTo: school@example.test\r\n"
            . "Cc: dept@example.test\r\nSubject: =?UTF-8?B?" . base64_encode('Πρόσκληση σε ημερίδα') . "?=\r\n"
            . "Date: Mon, 02 Nov 2026 07:15:00 +0000\r\nMIME-Version: 1.0\r\n";
        if ($html === null) {
            return $head . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n";
        }
        return $head . "Content-Type: multipart/mixed; boundary=\"B\"\r\n\r\n"
            . "--B\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $html . "\r\n"
            . "--B\r\nContent-Type: application/pdf; name=\"=?UTF-8?B?" . base64_encode('απόφαση.pdf') . "?=\"\r\n"
            . "Content-Disposition: attachment; filename=\"=?UTF-8?B?" . base64_encode('απόφαση.pdf') . "?=\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n" . base64_encode("%PDF-1.4\n") . "\r\n--B--\r\n";
    }

    /**
     * R4q (owner, 2026-11-27): an .eml file is shown as an e-mail: its headers, the names of its files, its HTML (or its
     * text), in the sandboxed page, with nothing that runs or loads from elsewhere
     */
    public function test_emails_are_shown_with_their_headers_and_their_html_kept_harmless(): void
    {
        $admin = $this->admin();
        $html = '<html><head><meta http-equiv="refresh" content="0;url=https://evil.example/"><base href="https://evil.example/">'
            . '<link rel="stylesheet" href="https://evil.example/x.css"><style>p.greet { color: #c00; }</style><script>alert(1)</script></head>'
            . '<body><p class="greet">Καλημέρα σε όλους</p><img src="https://tracker.example/pixel.gif"><iframe src="https://evil.example/"></iframe>'
            . '<script src="https://evil.example/x.js"></script><object data="x.swf"></object></body></html>';
        $a = $this->attach('email_20261102_091500.eml', $this->eml($html));
        $r = $this->actingAs($admin)->get('/preview/' . $a->id);
        $this->assertSandboxed($r);
        $r->assertSee('Γραμματεία Δοκιμών &lt;office@example.test&gt;', false)->assertSee('school@example.test')->assertSee('dept@example.test')
            ->assertSee('02/11/2026 09:15')->assertSee('Πρόσκληση σε ημερίδα')->assertSee('απόφαση.pdf')
            ->assertSee('<p class="greet">Καλημέρα σε όλους</p>', false)->assertSee('p.greet { color: #c00; }', false);
        foreach (['<script', '<iframe', '<object', '<meta http-equiv', '<base', '<link', 'alert(1)'] as $gone) {
            $r->assertDontSee($gone, false);
        }
        // a remote picture may stay: the page's own rules load nothing from the internet (img-src data: only)
        $this->assertStringContainsString('img-src data:', $r->headers->get('Content-Security-Policy'));

        $text = $this->attach('απλό.eml', $this->eml(null, "Γραμμή 1\n<b>όχι έντονο</b>"));
        $this->actingAs($admin)->get('/preview/' . $text->id)->assertOk()->assertSee('Γραμμή 1')
            ->assertSee('&lt;b&gt;όχι έντονο&lt;/b&gt;', false)->assertSee('Πρόσκληση σε ημερίδα');

        // larger than the converted files' limit, still shown (only its headers and text are read)
        config(['attachments.preview_max_kb' => 1]);
        $big = $this->attach('μεγάλο.eml', $this->eml(null, str_repeat('κείμενο ', 400)));
        $this->actingAs($admin)->get('/preview/' . $big->id)->assertOk()->assertSee('Πρόσκληση σε ημερίδα');
    }

    /** R4q (owner, 2026-11-27): the list has 👁 next to each file too; ‹ › go through that protocol's files */
    public function test_the_list_offers_a_preview_of_each_file(): void
    {
        $p = Protocol::factory()->number(4)->create();
        $pdf = $this->attach('απόφαση.pdf', "%PDF-1.4\n", $p);
        $eml = $this->attach('email_20261102_091500.eml', $this->eml(null), $p);
        $other = Protocol::factory()->number(5)->create();
        $this->attach('πίνακας.xlsx', 'x', $other);
        $this->attach('διαβιβαστικό.pdf', "%PDF-1.4\n", $other);   // more files that take the stamp: one read still
        $this->attach('συνημμένο.pdf', "%PDF-1.4\n", Protocol::factory()->number(6)->create());

        \DB::enableQueryLog();
        $html = Livewire::actingAs($this->reader())->test(\App\Livewire\ProtocolList::class)->html();
        $templateReads = collect(\DB::getQueryLog())->filter(fn ($q) => str_starts_with($q['query'], 'select') && in_array('stampTemplate', $q['bindings'], true))->count();
        \DB::disableQueryLog();
        $this->assertSame(2, substr_count($html, 'data-preview-group="list-' . $p->id . '"'));
        $this->assertSame(2, substr_count($html, 'data-preview-group="list-' . $other->id . '"'));
        $this->assertStringContainsString('data-preview-kind="pdf" data-preview-src="' . url('/download/' . $pdf->id) . '"', $html);
        $this->assertStringContainsString('data-preview-kind="mail" data-preview-src="' . url('/preview/' . $eml->id) . '"', $html);
        $this->assertStringContainsString('data-preview-place="top-right"', $html, 'the stamp\'s place buttons work from the list too');
        $this->assertLessThanOrEqual(1, $templateReads, 'the stamp\'s settings are read once for the page, not per file');
    }
}
