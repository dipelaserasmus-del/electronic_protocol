<?php

namespace Tests\Feature;

use App\Livewire\SettingsPage;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Protocols\RelatedNumbers;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * «Σχετικοί αριθμοί και στα δύο πρωτόκολλα» (setting saveCycleSxetiko): on save (legacy rule), and R6l (owner,
 * 2026-11-26) for the protocols saved before. docs/RELATED-NUMBERS.md describes both.
 */
class RelatedNumbersTest extends FeatureTestCase
{
    private function related(int $num, int $year = 2025): ?string
    {
        return Protocol::where('protocolnum', $num)->where('etos', $year)->value('sxetiko');
    }

    public function test_the_numbers_in_a_text(): void
    {
        $this->assertSame(['12/2025', '40/2024', '7/2026'], RelatedNumbers::numbers('012/2025, 40/2024; φ.3 12 7/2026  12/2025'));
        $this->assertSame([], RelatedNumbers::numbers(null));
        $this->assertSame([], RelatedNumbers::numbers('Α120, 12/25, 5/20255'));
    }

    public function test_on_save_the_other_protocol_gets_this_one_and_the_others(): void
    {
        $this->setting('protocolValidate', 0);
        $year = (int) date('Y');
        Protocol::factory()->number(1, $year)->create(['sxetiko' => 'κάτι']);
        Protocol::factory()->number(2, $year)->create(['sxetiko' => null]);
        $admin = $this->admin();
        $writer = app(ProtocolWriter::class);

        $this->setting('saveCycleSxetiko', 0);
        $p = $writer->create($admin, $this->protocolForm(['thema' => 'Χωρίς', 'sxetiko' => "1/$year"]))->protocol;
        $this->assertSame('κάτι', $this->related(1, $year), 'setting off: nothing written');

        $this->setting('saveCycleSxetiko', 1);
        $q = $writer->create($admin, $this->protocolForm(['thema' => 'Με', 'sxetiko' => "1/$year, 2/$year, 99/$year"]))->protocol;
        $self = "{$q->protocolnum}/$year";
        $this->assertSame("κάτι, 2/$year, 99/$year, $self", $this->related(1, $year));
        $this->assertSame("1/$year, 99/$year, $self", $this->related(2, $year));
        $this->assertNotNull($p);
    }

    public function test_existing_references_are_made_mutual_once(): void
    {
        $long = implode(', ', array_map(fn ($n) => "$n/2020", range(100, 124)));   // 248 characters: «, 4/2025» does not fit
        $this->assertSame(248, mb_strlen($long));
        Protocol::factory()->number(1, 2025)->create(['sxetiko' => '2/2025, 3/2025, φ.3, 777/2025, 1/2025']);   // 777: no such protocol; 1: itself
        Protocol::factory()->number(2, 2025)->create(['sxetiko' => '1/2025']);           // already mutual
        Protocol::factory()->number(3, 2025)->create(['sxetiko' => 'Σημείωση']);        // gets 1/2025 after its own text
        Protocol::factory()->number(4, 2025)->create(['sxetiko' => '3/2025; 0005/2025']);
        Protocol::factory()->number(5, 2025)->create(['sxetiko' => $long]);              // no room for 4/2025
        Protocol::factory()->number(6, 2025)->create(['sxetiko' => null]);               // named by no one
        Protocol::factory()->number(7, 2024)->create(['sxetiko' => '3/2025']);
        $before = DB::table('protocols')->where('protocolnum', 3)->value('updated_at');
        $this->travel(2)->days();

        $service = new RelatedNumbers;
        $this->assertSame(['protocols' => 1, 'numbers' => 3, 'left_out' => 1], $service->plan());
        $this->assertSame(['protocols' => 1, 'numbers' => 3, 'left_out' => 1], $service->applyToExisting());

        $this->assertSame('2/2025, 3/2025, φ.3, 777/2025, 1/2025', $this->related(1));
        $this->assertSame('1/2025', $this->related(2));
        $this->assertSame('Σημείωση, 1/2025, 4/2025, 7/2024', $this->related(3));
        $this->assertSame('3/2025; 0005/2025', $this->related(4));
        $this->assertSame($long, $this->related(5));
        $this->assertNull($this->related(6));
        $this->assertSame($before, DB::table('protocols')->where('protocolnum', 3)->value('updated_at'), 'a data fix, not an edit');

        // nothing more to do the second time (the one that does not fit is still counted)
        $this->assertSame(['protocols' => 0, 'numbers' => 0, 'left_out' => 1], $service->plan());
        $this->assertSame(['protocols' => 0, 'numbers' => 0, 'left_out' => 1], $service->applyToExisting());
    }

    public function test_administrators_apply_it_from_the_settings_while_the_setting_is_on(): void
    {
        Protocol::factory()->number(1, 2025)->create(['sxetiko' => '2/2025']);
        Protocol::factory()->number(2, 2025)->create(['sxetiko' => null]);
        $admin = $this->admin();

        $this->setting('saveCycleSxetiko', 0);
        $this->actingAs($admin)->get('/settings?section=registration')->assertOk()->assertDontSee('id="related-existing"', false);
        Livewire::actingAs($admin)->test(SettingsPage::class)->set('relatedPlan', ['protocols' => 1, 'numbers' => 1, 'left_out' => 0])
            ->call('applyRelated');
        $this->assertNull($this->related(2), 'setting off: not applied');

        $this->setting('saveCycleSxetiko', 1);
        $this->actingAs($admin)->get('/settings?section=registration')->assertSee('id="related-existing-open"', false);
        Livewire::actingAs($admin)->withQueryParams(['section' => 'registration'])->test(SettingsPage::class)
            ->call('openRelated')
            ->assertSet('relatedPlan', ['protocols' => 1, 'numbers' => 1, 'left_out' => 0])
            ->assertSee('1 πρωτόκολλο παίρνει 1 αριθμό')->assertSee('Εφαρμογή σε 1')
            ->call('applyRelated')
            ->assertSet('relatedPlan', null)
            ->assertDispatched('toast', message: 'Οι σχετικοί αριθμοί γράφτηκαν και στο άλλο πρωτόκολλο (1).', type: 'success')
            ->call('openRelated')->assertSee('Κανένα πρωτόκολλο δεν χρειάζεται αλλαγή');
        $this->assertSame('1/2025', $this->related(2));

        foreach ([$this->assigner(), $this->writer(), $this->reader()] as $u) {
            Livewire::actingAs($u)->test(SettingsPage::class)->assertForbidden();
        }
    }
}
