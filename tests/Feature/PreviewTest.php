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
        $this->assertSame(['pdf', 'image', 'image', 'text', 'text', 'word', 'word', 'sheet', 'sheet', 'sheet', null, null, null],
            array_map([Preview::class, 'kind'], ['a.PDF', 'b.jpg', 'c.webp', 'd.txt', 'e.csv', 'f.docx', 'g.odt', 'h.xlsx', 'i.xls', 'j.ods', 'k.doc', 'l.zip', 'χωρίς']));
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
}
