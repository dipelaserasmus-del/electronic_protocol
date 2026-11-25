<?php

namespace Tests\Feature;

use App\Livewire\StampDesigner;
use App\Models\Protocol;
use App\Services\Protocols\Stamp;
use App\Services\Stamp\Pdf\PdfException;
use App\Services\Stamp\Pdf\PdfReader;
use App\Services\Stamp\Pdf\PdfStamper;
use App\Services\Stamp\Pdf\PdfStream;
use App\Services\Stamp\StampRenderer;
use App\Services\Stamp\StampTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfBuilder;

/** R4e: the stamp template, its drawing, the PDF stamper and the designer page. */
class StampTest extends FeatureTestCase
{
    private function template(array $over = []): StampTemplate
    {
        return StampTemplate::fromArray($over + ['width' => 50, 'color' => '#204080', 'lines' => [
            ['text' => 'ΔΗΜΟΤΙΚΟ ΣΧΟΛΕΙΟ ΔΟΚΙΜΩΝ', 'size' => 8, 'align' => 'center'],
            ['text' => 'ΑΡΙΘΜ. ΠΡΩΤ. {αριθμός}', 'size' => 8, 'bold' => true, 'dots' => true],
        ]]);
    }

    private function emblem(): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledellipse($img, 150, 150, 280, 280, imagecolorallocate($img, 0, 0, 0));
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    /** the drawing operator of the stamp on a page, or null */
    private function stampOn(PdfReader $r, array $page): ?string
    {
        foreach ((array) $r->resolve($page['Contents'] ?? []) as $ref) {
            $s = $r->resolve($ref);
            if ($s instanceof PdfStream && str_contains($s->data, '/EPStamp Do')) {
                return $s->data;
            }
        }
        return null;
    }

    /** [a b c d e f] of the stamp's placement */
    private function matrix(string $draw): array
    {
        preg_match('/q ([-\d. ]+) cm \/EPStamp Do/', $draw, $m);
        return array_map('floatval', explode(' ', $m[1]));
    }

    // ---- the template ----

    public function test_template_values_are_checked_and_kept_within_limits(): void
    {
        $t = StampTemplate::fromArray(['width' => 500, 'color' => 'red', 'corner' => 'middle', 'x' => -4, 'lines' => [
            ['text' => "a\x07b", 'size' => 100, 'align' => 'diagonal', 'condense' => 5], 'not a line',
        ]]);
        $this->assertSame([120.0, '#1f3f99', 'top-right', 0.0], [$t->width, $t->color, $t->corner, $t->x]);
        $this->assertSame(['text' => 'a b', 'size' => 24.0, 'bold' => false, 'align' => 'center', 'spacing' => 0.0, 'condense' => 50.0, 'dots' => false], $t->lines[0]);
        $this->assertCount(1, $t->lines);

        $this->assertSame('Αρ. 12 / 24/11/2026 — {άγνωστο}', StampTemplate::fill('Αρ. {ΑΡΙΘΜΟΣ} / {ημερομηνια} — {άγνωστο}', ['number' => '12', 'date' => '24/11/2026']));

        // saved and read back; the default (before any change) is the old stamp, top right of the first page
        $default = StampTemplate::current();
        $this->assertSame(['{υπηρεσία}', 'Αρ.Πρ.: {αριθμός} / {ημερομηνία}'], array_column($default->lines, 'text'));
        $this->assertSame(['top-right', true], [$default->corner, $default->firstPageOnly]);
        $this->template()->save();
        $this->assertEquals($this->template()->toArray(), StampTemplate::current()->toArray());
        $this->assertNotSame($this->template()->hash(), $this->template(['color' => '#000000'])->hash());
    }

    // ---- the drawing ----

    public function test_the_drawing_has_its_size_in_mm_and_its_colour(): void
    {
        $r = new StampRenderer();
        $img = $r->render($this->template(), ['number' => '1234'], 600);
        [$w, $h] = StampRenderer::sizeMm($img, 600);
        $this->assertEqualsWithDelta(50, $w, 0.05);
        $this->assertGreaterThan(5, $h);
        $this->assertEqualsWithDelta($w, StampRenderer::sizeMm($r->render($this->template(), [], 200), 200)[0], 0.15, 'the same at any resolution');

        // the ink is the template's colour, the rest transparent, except the white under the number (R4k)
        $colours = [];
        for ($y = 0; $y < imagesy($img); $y += 3) {
            for ($x = 0; $x < imagesx($img); $x += 3) {
                $c = imagecolorat($img, $x, $y);
                if ((($c >> 24) & 0x7F) === 0) {
                    $colours[sprintf('#%06x', $c & 0xFFFFFF)] = true;
                }
            }
        }
        // opaque: the ink, white, and (the letters' soft edges over the white) blends of the two, nothing else
        $this->assertArrayHasKey('#204080', $colours);
        $this->assertArrayHasKey('#ffffff', $colours);
        foreach (array_keys($colours) as $hex) {
            [$cr, $cg, $cb] = sscanf($hex, '#%02x%02x%02x');
            $t = ($cr - 0x20) / (0xff - 0x20);   // 0 = ink, 1 = white
            $this->assertEqualsWithDelta(0x40 + $t * (0xff - 0x40), $cg, 3, "$hex is between the ink and white");
            $this->assertEqualsWithDelta(0x80 + $t * (0xff - 0x80), $cb, 3, "$hex is between the ink and white");
        }

        // an emblem makes it taller, in the same colour
        Storage::put(StampTemplate::EMBLEM, $this->emblem());
        $withEmblem = $r->render($this->template(['emblem' => true, 'emblemHeight' => 10]), [], 600);
        $this->assertEqualsWithDelta($h + 10 + 0.4, StampRenderer::sizeMm($withEmblem, 600)[1], 0.3);
        $c = imagecolorat($withEmblem, intdiv(imagesx($withEmblem), 2), 60);
        $this->assertSame(['#204080', 0], [sprintf('#%06x', $c & 0xFFFFFF), ($c >> 24) & 0x7F]);
    }

    /**
     * R4k (owner, 2026-11-25): text or pictures under the stamp must never hide the number and the date: their lines are
     * drawn on opaque white; the other lines stay transparent (what is under them shows through)
     */
    public function test_the_number_and_date_lines_are_on_opaque_white(): void
    {
        $r = new StampRenderer();
        $values = ['number' => '1234', 'date' => '25/11/2026', 'year' => '2026', 'office' => 'ΣΧΟΛΕΙΟ'];
        $opaqueWhite = fn ($img, $x, $y) => (imagecolorat($img, $x, $y) & 0x7FFFFFFF) === 0xFFFFFF;
        $transparent = fn ($img, $x, $y) => ((imagecolorat($img, $x, $y) >> 24) & 0x7F) === 127;
        $count = function ($img) use ($opaqueWhite) {
            $n = 0;
            for ($y = 0; $y < imagesy($img); $y++) {
                for ($x = 0; $x < imagesx($img); $x++) {
                    $n += $opaqueWhite($img, $x, $y) ? 1 : 0;
                }
            }
            return $n;
        };
        $plain = fn (array $lines) => StampTemplate::fromArray(['width' => 50, 'color' => '#204080', 'border' => false, 'lines' => $lines]);

        // a line without number or date: no white at all, the paper shows through
        $img = $r->render($plain([['text' => 'ΔΗΜΟΤΙΚΟ ΣΧΟΛΕΙΟ ΔΟΚΙΜΩΝ', 'size' => 8]]), $values, 300);
        $this->assertSame(0, $count($img));
        $this->assertTrue($transparent($img, 2, 2));

        foreach (['Αρ.Πρ.: {αριθμός}', '{ημερομηνία}', 'Έτος {έτος}'] as $text) {
            foreach ([false, true] as $dots) {
                $img = $r->render($plain([['text' => $text, 'size' => 8, 'dots' => $dots, 'align' => 'left']]), $values, 300);
                $w = imagesx($img);
                $h = imagesy($img);
                // around and between the letters: white, top to bottom of the line, from its start
                $pad = (int) round(0.5 * 300 / 25.4);   // the stamp's own margin (no border)
                $this->assertTrue($opaqueWhite($img, intdiv($w, 20), $pad), "$text: top of the line");
                $this->assertTrue($opaqueWhite($img, intdiv($w, 20), $h - 1 - $pad), "$text: bottom of the line");
                $opaque = 0;
                for ($yy = 0; $yy < $h; $yy++) {
                    for ($xx = 0; $xx < $w; $xx++) {
                        $opaque += ((imagecolorat($img, $xx, $yy) >> 24) & 0x7F) === 0 ? 1 : 0;
                    }
                }
                $this->assertGreaterThan(0.6 * $opaque, $count($img), "$text: a solid white area under the letters, not their edges");
                if ($dots) {
                    $this->assertTrue($opaqueWhite($img, $w - 3, intdiv($h, 2)) || ((imagecolorat($img, $w - 3, intdiv($h, 2)) >> 24) & 0x7F) === 0, "$text: the whole width with the dots");
                }
            }
        }

        // in a stamp with both: only the number's line is white; the office's line above it stays transparent
        $img = $r->render($plain([['text' => '{υπηρεσία}', 'size' => 8], ['text' => 'Αρ.Πρ.: {αριθμός} / {ημερομηνία}', 'size' => 8, 'align' => 'left']]), $values, 300);
        $this->assertTrue($transparent($img, imagesx($img) - 2, 2), 'the office line');
        $this->assertTrue($opaqueWhite($img, 3, imagesy($img) - 1 - (int) round(0.5 * 300 / 25.4)), 'the number line');
    }

    // ---- the PDF stamper ----

    public function test_a_pdf_is_stamped_by_an_update_at_its_corner_and_size(): void
    {
        foreach ([false, true] as $xrefStream) {
            $pdf = PdfBuilder::make([[], []], $xrefStream);
            $img = (new StampRenderer())->render($this->template(), ['number' => '7'], 600);
            [$w, $h] = StampRenderer::sizeMm($img, 600);
            $out = (new PdfStamper())->stamp($pdf, $img, [$w, $h], 'top-right', [10, 8], true);

            $this->assertStringStartsWith($pdf, $out, 'the original bytes stay as they are');
            $this->assertStringContainsString(PdfStamper::MARK, substr($out, strlen($pdf)));
            $r = new PdfReader($out);
            $this->assertSame($xrefStream, $r->xrefStream, 'the update is written like the file');
            $pages = $r->pages();
            $this->assertCount(2, $pages);
            $draw = $this->stampOn($r, $pages[0][1]);
            $this->assertNotNull($draw);
            $this->assertNull($this->stampOn($r, $pages[1][1]), 'only the first page');
            $mm = 72 / 25.4;
            [$a, $b, $c, $d, $e, $f] = $this->matrix($draw);
            $this->assertEqualsWithDelta($w * $mm, $a, 0.01);
            $this->assertEqualsWithDelta($h * $mm, $d, 0.01);
            $this->assertEqualsWithDelta(595 - 10 * $mm - $w * $mm, $e, 0.01, 'from the right edge');
            $this->assertEqualsWithDelta(842 - 8 * $mm - $h * $mm, $f, 0.01, 'from the top');
            // the page keeps its own drawing, wrapped in q … Q, and its fonts
            $contents = $r->resolve($pages[0][1]['Contents']);
            $this->assertCount(4, $contents);
            $this->assertSame('q', $r->resolve($contents[0])->data);
            $resources = $pages[0][1]['Resources'];
            $this->assertArrayHasKey('F1', $r->resolve($resources['Font']));
            $image = $r->resolve($resources['XObject']['EPStamp']);
            $this->assertSame([imagesx($img), imagesy($img)], [$image->dict['Width'], $image->dict['Height']], '600 dpi pixels');
            $this->assertSame(imagesx($img) * imagesy($img), strlen($r->decode($r->resolve($image->dict['SMask']))));

            // stamped again (another update on top)
            $again = (new PdfStamper())->stamp($out, $img, [$w, $h], 'bottom-left', [5, 5], false);
            $pages = (new PdfReader($again))->pages();
            $this->assertNotNull($this->stampOn(new PdfReader($again), $pages[1][1]), 'every page');
        }
    }

    public function test_corners_rotation_and_crop_box(): void
    {
        $img = (new StampRenderer())->render($this->template(), [], 200);
        [$w, $h] = StampRenderer::sizeMm($img, 200);
        $mm = 72 / 25.4;
        [$sw, $sh, $o] = [$w * $mm, $h * $mm, 10 * $mm];
        $pdf = PdfBuilder::make([['rotate' => 90], ['rotate' => 270], ['crop' => [20, 30, 520, 830]], ['rotate' => 180]]);
        $r = new PdfReader((new PdfStamper())->stamp($pdf, $img, [$w, $h], 'top-right', [10, 10], false));
        $m = array_map(fn ($p) => $this->matrix($this->stampOn($r, $p[1])), $r->pages());

        // rotated 90° (shown 842 wide, 595 high): the image's x axis goes up the page, its top-right corner is near (0, 842)
        $this->assertEqualsWithDelta([0, $sw, -$sh, 0], array_slice($m[0], 0, 4), 0.01);
        $this->assertEqualsWithDelta([595 - (595 - $o - $sh), 842 - $o - $sw], [$m[0][4], $m[0][5]], 0.01);
        // 270°
        $this->assertEqualsWithDelta([0, -$sw, $sh, 0], array_slice($m[1], 0, 4), 0.01);
        $this->assertEqualsWithDelta([595 - $o - $sh, $o + $sw], [$m[1][4], $m[1][5]], 0.01);
        // the crop box decides the corner
        $this->assertEqualsWithDelta([520 - $o - $sw, 830 - $o - $sh], [$m[2][4], $m[2][5]], 0.01);
        // 180°: upside down in the file, upright as shown
        $this->assertEqualsWithDelta([-$sw, 0, 0, -$sh, $o + $sw, $o + $sh], $m[3], 0.01);
    }

    public function test_pdfs_that_cannot_be_read_are_refused(): void
    {
        $img = (new StampRenderer())->render($this->template(), [], 100);
        foreach (['not a pdf', "%PDF-1.4\nno xref here\n%%EOF", str_replace('/Root 1 0 R', '/Root 1 0 R /Encrypt 9 0 R', PdfBuilder::make())] as $bad) {
            try {
                (new PdfStamper())->stamp($bad, $img, [50, 10], 'top-right', [10, 10], true);
                $this->fail('stamped: ' . substr($bad, 0, 20));
            } catch (PdfException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** the service: PDFs on save, encrypted ones left as they are */
    public function test_saved_pdfs_get_the_stamp_and_encrypted_ones_stay_as_they_are(): void
    {
        $this->template()->save();
        $protocol = Protocol::factory()->number(42)->create();
        $plain = PdfBuilder::make();
        Storage::put('arxeio/t/a.pdf', $plain);
        Storage::put('arxeio/t/locked.pdf', $locked = str_replace('/Root 1 0 R', '/Root 1 0 R /Encrypt 9 0 R', $plain));
        $stamp = new Stamp();
        $this->assertTrue($stamp->prepare($protocol));
        $stamp->apply('arxeio/t/a.pdf');
        $stamp->apply('arxeio/t/locked.pdf');
        $this->assertStringStartsWith($plain, Storage::get('arxeio/t/a.pdf'));
        $this->assertStringContainsString(PdfStamper::MARK, Storage::get('arxeio/t/a.pdf'));
        $this->assertSame($locked, Storage::get('arxeio/t/locked.pdf'));
    }

    // ---- the designer ----

    /** R4i (owner, 2026-11-25): administrators only (R4e had assigners and writers too) */
    public function test_the_designer_is_for_administrators_only(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/stamp')->assertOk()->assertSee('Σφραγίδα αρχείων')->assertSee('id="stamp-preview"', false);
        $this->assertStringContainsString('href="'.url('/stamp').'"', $this->actingAs($admin)->get('/about')->getContent());
        foreach ([$this->assigner(), $this->writer(), $this->reader()] as $user) {
            $this->actingAs($user)->get('/stamp')->assertForbidden();
            Livewire::actingAs($user)->test(StampDesigner::class)->assertForbidden();
            $this->assertStringNotContainsString('href="'.url('/stamp').'"', $this->actingAs($user)->get('/about')->getContent(), 'not in the menu');
        }
        auth()->logout();
        $this->get('/stamp')->assertRedirect('/login');
    }

    public function test_the_designer_edits_previews_and_saves(): void
    {
        $page = Livewire::actingAs($this->admin())->test(StampDesigner::class)
            ->call('addLine')->set('tpl.lines.2.text', 'ΑΓ. ΔΟΚΙΜΟΣ {ημερομηνία}')->set('tpl.lines.2.dots', true)
            ->call('moveLine', 2, -1)
            ->set('tpl.width', '70')->set('tpl.color', '#112233')->set('tpl.corner', 'bottom-left')->set('tpl.firstPageOnly', false);
        $this->assertStringContainsString('data:image/png;base64,', $page->html());
        $page->assertSee('Μέγεθος: 70,0 ×');

        $page->set('emblemUpload', UploadedFile::fake()->createWithContent('emblem.png', $this->emblem()))
            ->assertSet('tpl.emblem', true)
            ->call('save')->assertDispatched('toast', type: 'success');
        $saved = StampTemplate::current();
        $this->assertSame([70.0, '#112233', 'bottom-left', false, true], [$saved->width, $saved->color, $saved->corner, $saved->firstPageOnly, $saved->emblem]);
        $this->assertSame('ΑΓ. ΔΟΚΙΜΟΣ {ημερομηνία}', $saved->lines[1]['text']);
        $this->assertNotNull(StampTemplate::emblemPath());
        $this->assertSame("\x89PNG", substr(Storage::get(StampTemplate::EMBLEM), 0, 4));

        // not an image: refused; removing the emblem
        $page->set('emblemUpload', UploadedFile::fake()->createWithContent('x.png', 'not an image'))->assertHasErrors('emblemUpload');
        Livewire::actingAs($this->admin())->test(StampDesigner::class)->call('removeEmblem')->call('save');
        $this->assertNull(StampTemplate::emblemPath());
        $this->assertFalse(StampTemplate::current()->emblem);

        Livewire::actingAs($this->admin())->test(StampDesigner::class)->call('resetDefaults')->call('save');
        $this->assertEquals(StampTemplate::defaults()->toArray(), StampTemplate::current()->toArray());
    }

    /** the owner's own stamp, from a folder outside the repository */
    public function test_stamp_import_loads_a_template_and_its_emblem(): void
    {
        $dir = sys_get_temp_dir() . '/stamp-' . uniqid();
        mkdir($dir);
        file_put_contents("$dir/template.json", json_encode(['width' => 55, 'emblem' => true, 'lines' => [['text' => 'ΔΟΚΙΜΗ {αριθμός}']]]));
        file_put_contents("$dir/emblem.png", $this->emblem());

        $this->artisan('stamp:import', ['dir' => $dir, '--preview' => true])->assertSuccessful();
        $this->assertFileExists("$dir/preview.png");
        $this->assertNull(StampTemplate::emblemPath(), 'a preview saves nothing');

        $this->artisan('stamp:import', ['dir' => $dir])->expectsOutputToContain('1 γραμμές, με έμβλημα')->assertSuccessful();
        $this->assertSame([55.0, true, 'ΔΟΚΙΜΗ {αριθμός}'], [StampTemplate::current()->width, StampTemplate::current()->emblem, StampTemplate::current()->lines[0]['text']]);
        $this->assertSame($this->emblem(), Storage::get(StampTemplate::EMBLEM));

        file_put_contents("$dir/emblem.png", 'not a png');
        $this->artisan('stamp:import', ['dir' => $dir])->assertFailed();
        $this->artisan('stamp:import', ['dir' => "$dir/missing"])->assertFailed();
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    /** docx: the stamp in the first page's header, at its size and distance from the corner */
    public function test_word_files_get_the_stamp_in_the_first_page_header(): void
    {
        $this->template()->save();
        $word = new \PhpOffice\PhpWord\PhpWord();
        $word->addSection()->addText('Έγγραφο δοκιμής');
        $file = tempnam(sys_get_temp_dir(), 'docx');
        \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save($file);
        Storage::put('arxeio/t/doc.docx', file_get_contents($file));
        unlink($file);

        $stamp = new Stamp();
        $stamp->prepare(Protocol::factory()->number(43)->create());
        $stamp->apply('arxeio/t/doc.docx');

        $zip = new \ZipArchive();
        $zip->open(Storage::path('arxeio/t/doc.docx'));
        $this->assertStringContainsString('<w:titlePg/>', $zip->getFromName('word/document.xml'), 'first page only');
        $header = (string) $zip->getFromName('word/header1.xml');
        $zip->close();
        $this->assertMatchesRegularExpression('/width:141\.7\dpt; height:[\d.]+pt; margin-left:425\.2\d?pt; margin-top:22\.68pt; position:absolute/', $header);
        $this->assertStringContainsString('mso-position-horizontal-relative:page', $header);
    }

    /** review of R4e: a hybrid file (table + /XRefStm), resource names that are numbers, a page stamped twice */
    public function test_hybrid_files_numeric_names_and_a_second_stamp(): void
    {
        $img = (new StampRenderer())->render($this->template(), [], 100);
        $size = StampRenderer::sizeMm($img, 100);

        // hybrid: the newest table marks the page (in an object stream) free; its /XRefStm says where it is
        $pdf = PdfBuilder::make([[]], true);
        preg_match('/startxref\s+(\d+)/', $pdf, $m);
        $at = strlen($pdf);
        $pdf .= "xref\n0 1\n0000000000 65535 f \n5 1\n0000000000 00001 f \ntrailer\n<< /Size 9 /Root 1 0 R /XRefStm {$m[1]} >>\nstartxref\n$at\n%%EOF\n";
        $r = new PdfReader($pdf);
        $this->assertCount(1, $r->pages(), 'the page is found through the stream');
        $out = (new PdfStamper())->stamp($pdf, $img, $size, 'top-right', [10, 10], true);
        $this->assertNotNull($this->stampOn(new PdfReader($out), (new PdfReader($out))->pages()[0][1]));

        // a dictionary whose names are numbers stays a dictionary
        $dict = (new \App\Services\Stamp\Pdf\PdfParser('<< /0 5 0 R /1 6 0 R >>'))->value();
        $this->assertSame('<</0 5 0 R /1 6 0 R>>', \App\Services\Stamp\Pdf\PdfParser::write($dict));
        $this->assertSame('<<>>', \App\Services\Stamp\Pdf\PdfParser::write((new \App\Services\Stamp\Pdf\PdfParser('<< >>'))->value()));
        $this->assertSame('[1 2]', \App\Services\Stamp\Pdf\PdfParser::write((new \App\Services\Stamp\Pdf\PdfParser('[1 2]'))->value()));

        // stamped twice: the first stamp keeps its own image
        $once = (new PdfStamper())->stamp(PdfBuilder::make(), $img, $size, 'top-right', [10, 10], true);
        $twice = (new PdfStamper())->stamp($once, $img, $size, 'bottom-left', [10, 10], true);
        $page = (new PdfReader($twice))->pages()[0][1];
        $this->assertSame(['EPStamp', 'EPStamp1'], array_values(array_filter(array_keys($page['Resources']['XObject']), 'is_string')));
        $r = new PdfReader($twice);
        $draws = array_filter(array_map(fn ($ref) => $r->resolve($ref)->data, $r->resolve($page['Contents'])), fn ($d) => str_contains($d, ' Do Q'));
        $this->assertSame(['/EPStamp Do', '/EPStamp1 Do'], array_values(array_map(fn ($d) => preg_match('~/EPStamp\d* Do~', $d, $x) ? $x[0] : '', $draws)));
    }
}
