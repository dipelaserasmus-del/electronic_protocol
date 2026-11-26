<?php

namespace Tests\Feature;

use App\Console\Commands\OldStamps;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Stamp\Pdf\OldStampRemover;
use App\Services\Stamp\Pdf\PdfReader;
use App\Services\Stamp\Pdf\PdfStream;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfBuilder;

/** R4g: the old tool's stamp (markpdf, before R4e/R4f) recognised strictly and taken out of PDFs. */
class OldStampsTest extends FeatureTestCase
{
    /** an old-stamped PDF: image 220 × 40 pixels drawn 220 × 40 points on every page */
    private function oldStamped(int $pages = 2, ?array $only = null, array $drawn = [220, 40], bool $xrefStream = false): string
    {
        return PdfBuilder::make(array_fill(0, $pages, []), $xrefStream, [220, 40, $drawn[0], $drawn[1], $only]);
    }

    /** every page's drawing, decoded */
    private function drawings(string $pdf): array
    {
        $r = new PdfReader($pdf);
        return array_map(function ($p) use ($r) {
            $c = $r->resolve($p[1]['Contents']);
            return implode("\n", array_map(fn ($s) => $r->decode($r->resolve($s)), $c instanceof PdfStream ? [$c] : $c));
        }, $r->pages());
    }

    public function test_the_old_stamp_is_found_and_taken_out_of_every_page(): void
    {
        foreach ([false, true] as $xrefStream) {
            $pdf = $this->oldStamped(3, null, [220, 40], $xrefStream);
            $remover = new OldStampRemover();
            $this->assertSame(['stamped' => true, 'reason' => 'σφραγίδα του παλιού εργαλείου', 'pages' => 3], $remover->analyse($pdf));

            $clean = $remover->remove($pdf);
            $this->assertStringStartsWith($pdf, $clean, 'an update after the bytes as they were');
            $this->assertStringContainsString('%EP-UNSTAMP', $clean);
            foreach ($this->drawings($clean) as $i => $drawing) {
                $this->assertStringNotContainsString('/Img1 Do', $drawing, "page $i");
                $this->assertStringContainsString('BT /F1 24 Tf', $drawing, "page $i keeps its own drawing");
            }
            foreach ((new PdfReader($clean))->pages() as [, $page]) {
                $this->assertArrayNotHasKey('Img1', $page['Resources']['XObject']);
                $this->assertArrayHasKey('F1', $page['Resources']['Font']);
            }
            $this->assertFalse($remover->analyse($clean)['stamped'], 'and it is not found again');
        }
    }

    public function test_only_exactly_that_stamp_counts(): void
    {
        $r = new OldStampRemover();
        $this->assertSame('δεν το έγραψε το παλιό εργαλείο σφραγίδας', $r->analyse(PdfBuilder::make())['reason']);
        $this->assertSame('δεν βρέθηκε η σφραγίδα σε όλες τις σελίδες', $r->analyse($this->oldStamped(2, [0]))['reason']);
        $this->assertSame('η εικόνα στο τέλος δεν είναι η σφραγίδα', $r->analyse($this->oldStamped(1, null, [440, 80]))['reason'], 'drawn at another size');
        $this->assertStringStartsWith('δεν διαβάζεται', $r->analyse('not a pdf')['reason']);
        $this->expectException(\App\Services\Stamp\Pdf\PdfException::class);
        $r->remove(PdfBuilder::make());
    }

    /** with the real old tool (shipped with the application) when it can run here */
    public function test_a_file_stamped_by_the_old_tool_itself(): void
    {
        $bin = base_path('vendor/yasapurnama/document-watermark/bin/markpdf_linux-amd64');
        $dir = sys_get_temp_dir() . '/r4g-' . uniqid();
        mkdir($dir);
        copy(base_path('e2e/fixtures/preview/prosklisi.pdf'), "$dir/in.pdf");   // a synthetic LibreOffice PDF (that tool cannot read every PDF)
        $img = imagecreate(220, 40);
        imagecolorallocate($img, 255, 255, 255);
        imagerectangle($img, 0, 0, 219, 39, imagecolorallocate($img, 0, 0, 255));
        imagepng($img, "$dir/stamp.png");
        exec(escapeshellarg($bin) . " $dir/in.pdf $dir/stamp.png $dir/out.pdf -x -10 -y 5 -o 1 2>&1", $out, $code);
        if ($code !== 0 || ! is_file("$dir/out.pdf")) {
            array_map('unlink', glob("$dir/*"));
            rmdir($dir);
            $this->markTestSkipped('the old tool does not run here (' . PHP_OS . ' ' . php_uname('m') . ')');
        }
        $stamped = file_get_contents("$dir/out.pdf");
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);

        $r = new OldStampRemover();
        $this->assertTrue($r->analyse($stamped)['stamped']);
        foreach ($this->drawings($r->remove($stamped)) as $drawing) {
            $this->assertDoesNotMatchRegularExpression('~/Img\d+ Do~', $drawing);
        }
    }

    public function test_the_command_reports_and_with_apply_takes_the_old_stamp_out(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $make = function (string $name, ?string $contents) use ($p) {
            $a = Attachment::factory()->make(['protocol_id' => $p->id, 'name' => $name]);
            $a->savedPath = 'arxeio/test/' . $name;
            if ($contents !== null) {
                Storage::put($a->savedPath, $contents);
            }
            $a->stamp_in_file = true;
            $a->save();
            return $a;
        };
        $old = $make('παλιό.pdf', $stampedPdf = $this->oldStamped());
        $plain = $make('απλό.pdf', $plainPdf = PdfBuilder::make());
        $word = $make('έγγραφο.docx', 'docx bytes');
        $make('χαμένο.pdf', null);

        $this->artisan('stamp:old', ['--list' => true])
            ->expectsOutputToContain("#{$old->id}  η παλιά σφραγίδα αφαιρείται (2 σελ.)")
            ->expectsOutputToContain('PDF όπου η παλιά σφραγίδα αφαιρείται: 1')
            ->expectsOutputToContain('PDF χωρίς παλιά σφραγίδα: 1')
            ->expectsOutputToContain('Word (docx, odt), μένουν ως έχουν: 1')
            ->expectsOutputToContain('Χωρίς αρχείο στον δίσκο: 1')
            ->assertSuccessful();
        $this->assertSame($stampedPdf, Storage::get($old->savedPath), 'only a report without --apply');

        $this->artisan('stamp:old', ['--apply' => true])->expectsOutputToContain('(αφαιρέθηκε: 1)')->assertSuccessful();
        $this->assertSame($stampedPdf, Storage::get(OldStamps::ORIGINALS . '/' . $old->savedPath), 'the file as it was is kept');
        $this->assertFalse((new OldStampRemover())->analyse(Storage::get($old->savedPath))['stamped']);
        $this->assertSame([false, false, true], [$old->fresh()->stamp_in_file, $plain->fresh()->stamp_in_file, $word->fresh()->stamp_in_file]);
        $this->assertSame(hash('sha256', Storage::get($old->savedPath)), $old->fresh()->sha256);
        $this->assertSame($plainPdf, Storage::get($plain->savedPath), 'a PDF without the old stamp is not changed');

        // run again: nothing more to do
        $this->artisan('stamp:old')->expectsOutputToContain('PDF όπου η παλιά σφραγίδα αφαιρείται: 0')->assertSuccessful();
    }

    /** review of R4g: a stamp added by this application (R4e) is recognised and cut off exactly; failed writes change nothing */
    /**
     * R4m (owner, 2026-11-26): the old server never stamped. The migration marks nothing when the old setting putStamp
     * is off (or missing), and «--none-stamped» takes every mark away without touching a file.
     */
    public function test_when_the_old_server_never_stamped_no_file_is_marked(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $pdf = Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'α.pdf', 'savedPath' => 'arxeio/test/α.pdf']);
        $word = Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'β.docx', 'savedPath' => 'arxeio/test/β.docx']);
        Storage::put('arxeio/test/α.pdf', $bytes = PdfBuilder::make());
        $migration = require database_path('migrations/2026_11_24_000002_attachments_stamp_in_file.php');
        $marks = fn () => [$pdf->fresh()->stamp_in_file, $word->fresh()->stamp_in_file];

        foreach ([null, '0', '1'] as $putStamp) {
            $migration->down();
            \App\Models\Config::where('key', 'putStamp')->delete();
            if ($putStamp !== null) {
                $this->setting('putStamp', $putStamp);
            }
            $migration->up();
            $this->assertSame($putStamp === '1' ? [true, true] : [false, false], $marks(), "putStamp: ".var_export($putStamp, true));
        }

        $word->delete();   // review of R4m: in the trash (soft-deleted) when the marks are cleared
        $this->artisan('stamp:old', ['--none-stamped' => true])
            ->expectsOutputToContain('2 αρχεία δεν σημειώνονται πια ως σφραγισμένα')->assertSuccessful();
        $word->restore();
        $this->assertSame([false, false], $marks(), 'also the one in the trash, restored later');
        $this->assertSame($bytes, Storage::get('arxeio/test/α.pdf'), 'no file changed');
        $this->assertFalse(Storage::exists(OldStamps::ORIGINALS.'/arxeio/test/α.pdf'));
        $this->artisan('stamp:old', ['--none-stamped' => true])->expectsOutputToContain(': 0 αρχεία')->assertSuccessful();
    }

    public function test_a_stamp_of_this_application_and_failed_writes(): void
    {
        $plain = PdfBuilder::make();
        $img = (new \App\Services\Stamp\StampRenderer())->render(\App\Services\Stamp\StampTemplate::defaults(), [], 100);
        $stamped = (new \App\Services\Stamp\Pdf\PdfStamper())->stamp($plain, $img, [50, 10], 'top-right', [10, 10], true);
        $r = new OldStampRemover();
        $this->assertTrue($r->analyse($stamped)['stamped']);
        $this->assertSame($plain, $r->remove($stamped));

        $p = Protocol::factory()->number(2)->create();
        $a = Attachment::factory()->make(['protocol_id' => $p->id, 'name' => 'νέο.pdf']);
        $a->savedPath = 'arxeio/test/νέο.pdf';
        Storage::put($a->savedPath, $stamped);
        $a->stamp_in_file = true;
        $a->save();
        $this->artisan('stamp:old', ['--list' => true])->expectsOutputToContain('η παλιά σφραγίδα αφαιρείται')->assertSuccessful();
        $this->artisan('stamp:old', ['--apply' => true])->assertSuccessful();
        $this->assertSame($plain, Storage::get($a->savedPath));
        $this->assertFalse($a->fresh()->stamp_in_file);

        // the copy cannot be kept: nothing changes
        $b = Attachment::factory()->make(['protocol_id' => $p->id, 'name' => 'άλλο.pdf']);
        $b->savedPath = 'arxeio/test/άλλο.pdf';
        Storage::put($b->savedPath, $stamped);
        $b->stamp_in_file = true;
        $b->save();
        Storage::deleteDirectory(OldStamps::ORIGINALS);
        Storage::put(OldStamps::ORIGINALS . '/arxeio', 'a file where the folder must go');   // the copy cannot be written
        $this->artisan('stamp:old', ['--apply' => true])->expectsOutputToContain('δεν κρατήθηκε αντίγραφο')->assertSuccessful();
        $this->assertTrue($b->fresh()->stamp_in_file);
        $this->assertSame($stamped, Storage::get($b->savedPath), 'not changed');
    }
}
