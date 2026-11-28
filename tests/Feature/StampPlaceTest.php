<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Stamp\Pdf\PdfReader;
use App\Services\Stamp\Pdf\PdfStream;
use App\Services\Stamp\StampedCopy;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfBuilder;

/**
 * R4n (owner, 2026-11-26): the stamp's place on one file (top left, centre, right), chosen in the preview by any user
 * who may see the protocol; the stored file never changes, the stamped copy is made again with the new place.
 * R4p (owner, 2026-11-27): in the same way, the stamp opaque on one file.
 */
class StampPlaceTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('putStamp', 1);
    }

    private function attachment(?Protocol $protocol = null, array $attributes = []): Attachment
    {
        $protocol ??= Protocol::factory()->number(1)->create();
        $a = Attachment::factory()->make(['protocol_id' => $protocol->id, 'name' => 'έγγραφο.pdf']);
        $a->forceFill($attributes);
        $a->savedPath = 'arxeio/test/' . uniqid() . '_έγγραφο.pdf';
        Storage::put($a->savedPath, PdfBuilder::make());
        $a->save();
        return $a;
    }

    /** the stamp's x on the first page of what the user downloads */
    private function stampX(Attachment $a, $user): float
    {
        $r = new PdfReader($this->actingAs($user)->get('/download/' . $a->id)->streamedContent());
        foreach ((array) $r->resolve($r->pages()[0][1]['Contents']) as $ref) {
            $s = $r->resolve($ref);
            if ($s instanceof PdfStream && preg_match('/q ([-\d. ]+) cm \/EPStamp Do/', $s->data, $m)) {
                return (float) explode(' ', $m[1])[4];
            }
        }
        $this->fail('no stamp');
    }

    public function test_any_user_who_sees_the_protocol_moves_the_stamp_on_one_file(): void
    {
        $a = $this->attachment();
        $other = $this->attachment($a->protocol);
        $original = Storage::get($a->savedPath);
        $reader = $this->reader();
        $mm = 72 / 25.4;

        $this->assertSame('top-right', StampedCopy::place($a), 'the stamp\'s own place first');
        $right = $this->stampX($a, $reader);
        $this->assertGreaterThan(300, $right);

        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-left'])
            ->assertOk()->assertJson(['place' => 'top-left', 'label' => 'πάνω αριστερά']);
        $this->assertSame('top-left', $a->fresh()->stamp_place);
        // X01 review: saved quietly, still in the audit log, by the reader who moved it
        $e = \App\Models\AuditEntry::where('action', 'updated')->where('entity_type', 'attachment')->where('entity_id', $a->id)->sole();
        $this->assertSame([$reader->id, ['stamp_place' => [null, 'top-left']]], [$e->user_id, $e->changes]);
        $this->assertEqualsWithDelta(10 * $mm, $this->stampX($a->fresh(), $this->writer()), 0.01, 'for every user');
        $this->assertSame($original, Storage::get($a->savedPath), 'the stored file is not changed');
        $this->assertEqualsWithDelta($right, $this->stampX($other, $reader), 0.01, 'the other file keeps the usual place');

        $this->actingAs($this->writer())->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-center'])->assertOk();
        $this->assertGreaterThan(10 * $mm, $this->stampX($a->fresh(), $reader));
        $this->assertLessThan($right, $this->stampX($a->fresh(), $reader));
        $this->assertCount(2, Storage::files(StampedCopy::DIR), 'one copy per file: the old copy of the moved one is gone');

        // refused: an unknown place, a file that takes no stamp, a protocol the user may not see, a guest
        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'bottom-left'])->assertStatus(422);
        $inFile = $this->attachment($a->protocol, ['stamp_in_file' => true]);
        $this->actingAs($reader)->postJson('/attachments/' . $inFile->id . '/stamp-place', ['place' => 'top-left'])->assertStatus(422);
        $this->setting('limitProtocolAccessList', 1);
        $this->actingAs($this->writer())->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-right'])->assertNotFound();
        $this->assertSame('top-center', $a->fresh()->stamp_place);
        $this->app['auth']->forgetGuards();
        $this->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-right'])->assertUnauthorized();
    }

    /** review of R4n: a stamped copy being made (its lock held) is not deleted half-way; the change waits, then says so */
    public function test_a_copy_being_made_is_not_deleted_under_it(): void
    {
        $a = $this->attachment();
        $work = StampedCopy::DIR . '/' . $a->id . '-0123456789abcdef.part.pdf';
        Storage::put($work, 'being made');
        $lock = \Illuminate\Support\Facades\Cache::lock('stamped-copy-' . $a->id, 30);
        $this->assertTrue($lock->get());
        \App\Http\Controllers\StampPlaceController::$wait = 0;
        try {
            $this->actingAs($this->reader())->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-left'])->assertStatus(423);
            $this->assertTrue(Storage::exists($work), 'the work file of the copy being made stays');
            $this->assertNull($a->fresh()->stamp_place);
            $lock->release();
            $this->actingAs($this->reader())->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-left'])->assertOk();
            $this->assertFalse(Storage::exists($work), 'once free: the old copies go');
        } finally {
            \App\Http\Controllers\StampPlaceController::$wait = 20;
            $lock->release();
        }
    }

    public function test_the_form_and_the_window_offer_the_three_places(): void
    {
        $a = $this->attachment();
        $html = $this->actingAs($this->writer())->get('/home/' . $a->protocol_id)->assertOk()->getContent();
        $this->assertStringContainsString('data-preview-place="top-right" data-preview-place-url="' . url('/attachments/' . $a->id . '/stamp-place') . '"', $html);
        foreach (['top-left', 'top-center', 'top-right'] as $place) {
            $this->assertStringContainsString('data-stamp-place="' . $place . '"', $html);
        }
        $a->stamp_place = 'top-center';
        $a->save();
        $this->assertStringContainsString('data-preview-place="top-center"', $this->actingAs($this->reader())->get('/home/' . $a->protocol_id)->getContent());
    }
    /** the share of dark pixels (the black picture showing through) in the stamp's box, top right */
    private function showingThrough(string $png, float $wMm, float $hMm): float
    {
        $img = imagecreatefromstring($png);
        [$dark, $all] = [0, 0];
        // an A4 picture at 10 px a mm: the stamp 10 mm from the right, 8 mm from the top; its inner part sampled
        for ($y = (int) (80 + 2 * 10); $y < (int) (80 + ($hMm - 2) * 10); $y += 3) {
            for ($x = (int) (2100 - 100 - ($wMm - 2) * 10); $x < 2100 - 100 - 20; $x += 3) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $dark += (int) ($c['red'] + $c['green'] + $c['blue'] < 60);
                $all++;
            }
        }
        return $dark / max(1, $all);
    }

    /**
     * R4p (owner, 2026-11-27): any user who sees the protocol makes the stamp on one file opaque (all of it on white) or
     * see-through again; see-through keeps the number and date on white (R4k). The stored file never changes.
     */
    public function test_the_stamp_is_made_opaque_on_one_file(): void
    {
        $protocol = Protocol::factory()->number(5)->create();
        $img = imagecreatetruecolor(2100, 2970);
        imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 0));   // black: whatever shows through is seen
        ob_start();
        imagepng($img);
        $black = (string) ob_get_clean();
        $a = Attachment::factory()->make(['protocol_id' => $protocol->id, 'name' => 'σάρωση.png']);
        $a->savedPath = 'arxeio/test/' . uniqid() . '_σάρωση.png';
        Storage::put($a->savedPath, $black);
        $a->save();
        $stamp = (new \App\Services\Stamp\StampRenderer())->render(\App\Services\Stamp\StampTemplate::current(),
            \App\Services\Stamp\StampTemplate::valuesFor($protocol), \App\Services\Stamp\StampRenderer::PRINT_DPI);
        [$wMm, $hMm] = \App\Services\Stamp\StampRenderer::sizeMm($stamp, \App\Services\Stamp\StampRenderer::PRINT_DPI);
        $reader = $this->reader();
        $download = fn () => $this->actingAs($reader)->get('/download/' . $a->id)->streamedContent();

        $this->assertGreaterThan(0.3, $this->showingThrough($download(), $wMm, $hMm), 'as it is: see-through');
        $html = $this->actingAs($reader)->get('/home/' . $protocol->id)->getContent();
        $this->assertStringContainsString('data-preview-opaque="0"', $html);
        $this->assertStringContainsString('data-stamp-opaque="1"', $html);

        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', ['opaque' => true])
            ->assertOk()->assertJson(['opaque' => true, 'place' => 'top-right']);
        $this->assertTrue($a->fresh()->stamp_opaque);
        $this->assertNull($a->fresh()->stamp_place, 'the place is not changed with it');
        $this->assertSame(0.0, $this->showingThrough($download(), $wMm, $hMm), 'opaque: nothing shows through');
        $this->assertSame($black, Storage::get($a->savedPath), 'the stored file is not changed');
        $this->assertStringContainsString('data-preview-opaque="1"', $this->actingAs($this->writer())->get('/home/' . $protocol->id)->getContent());

        // with a place, both at once; then see-through again
        $this->actingAs($this->writer())->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-left', 'opaque' => false])
            ->assertOk()->assertJson(['opaque' => false, 'place' => 'top-left']);
        $this->assertFalse($a->fresh()->stamp_opaque);
        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', ['place' => 'top-right'])->assertOk();
        $this->assertFalse($a->fresh()->stamp_opaque, 'a place alone keeps the opacity');
        $this->assertGreaterThan(0.3, $this->showingThrough($download(), $wMm, $hMm));
        $this->assertCount(1, Storage::files(\App\Services\Stamp\StampedCopy::DIR), 'one copy: the old ones are gone');

        // refused: not a yes / no, nothing to change
        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', ['opaque' => 'maybe'])->assertStatus(422);
        $this->actingAs($reader)->postJson('/attachments/' . $a->id . '/stamp-place', [])->assertStatus(422);
        $this->assertFalse($a->fresh()->stamp_opaque);
    }
}
