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
}
