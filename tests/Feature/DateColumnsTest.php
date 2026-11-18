<?php

namespace Tests\Feature;

use App\Casts\LegacyYmdDate;
use App\Models\Attachment;
use App\Models\Protocol;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** D04: dates are DATE columns; the legacy code still sees integers Ymd (transitional cast). */
class DateColumnsTest extends FeatureTestCase
{
    public function test_columns_are_dates(): void
    {
        $type = fn ($t, $c) => DB::selectOne('SELECT DATA_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c])->t;
        foreach (Protocol::DATE_COLUMNS as $c) {
            $this->assertSame('date', $type('protocols', $c), $c);
        }
        $this->assertSame('date', $type('attachments', 'expires'));
    }

    public function test_legacy_code_reads_and_writes_integers_ymd(): void
    {
        $p = Protocol::factory()->number(1)->create(['protocoldate' => 20260105, 'in_date' => '20260103', 'out_date' => '2026-01-07',
            'diekp_eos' => new \DateTime('2026-02-01'), 'diekp_date' => '']);

        $this->assertSame('2026-01-05', DB::table('protocols')->where('id', $p->id)->value('protocoldate'), 'stored as a date');
        $p = $p->fresh();
        $this->assertSame(20260105, $p->protocoldate);
        $this->assertSame(20260103, $p->in_date);
        $this->assertSame(20260107, $p->out_date);
        $this->assertSame(20260201, $p->diekp_eos);
        $this->assertNull($p->diekp_date);
        $this->assertSame('05/01/2026', \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'), 'as the legacy code formats it');
        $this->assertSame('2026-01-05', $p->dateOf('protocoldate')->toDateString(), 'new code gets a date');
        $this->assertNull($p->dateOf('diekp_date'));

        // the legacy lists put display text into the attribute before rendering: shown as it is
        $p->in_date = '03/01/2026';
        $this->assertSame('03/01/2026', $p->in_date);
    }

    public function test_impossible_dates_are_refused(): void
    {
        $this->expectException(QueryException::class);
        Protocol::factory()->number(1)->create(['in_date' => 20260231]);   // before: stored as it was
    }

    public function test_cast_conversions(): void
    {
        foreach ([[null, null], ['', null], [0, null], ['0', null], [20261116, '2026-11-16'], ['20261116', '2026-11-16'],
                  ['2026-11-16', '2026-11-16'], [new \DateTimeImmutable('2026-11-16 13:00'), '2026-11-16'], ['16/11/2026', '16/11/2026']] as [$in, $out]) {
            $this->assertSame($out, LegacyYmdDate::toDateString($in), var_export($in, true));
        }
    }

    public function test_saving_the_form_stores_the_dates(): void
    {
        $this->setting('protocolValidate', 0);   // an administrator who may set the number and the date (R1a)
        $this->actingAs($this->admin())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'protocolnum_proposed' => 2, 'protocoldate' => '05/01/2026', 'etos' => 2026, 'in_num' => 'Φ.1/2', 'in_date' => '03/01/2026',
            'out_date' => '07/01/2026', 'diekp_eos' => '01/02/2026',
            'in_topos_ekdosis' => 'Δοκιμή', 'in_arxi_ekdosis' => 'Διεύθυνση Δοκιμής', 'in_paraliptis' => 'Διευθυντής', 'out_to' => 'Γραφείο Δοκιμής',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $row = DB::table('protocols')->first();
        $this->assertSame(['2026-01-05', '2026-01-03', '2026-01-07', '2026-02-01', null],
            [$row->protocoldate, $row->in_date, $row->out_date, $row->diekp_eos, $row->diekp_date]);

        $p = Protocol::first();
        $this->actingAs($this->admin())->get("/home/{$p->id}")->assertOk()
            ->assertSee('05/01/2026')->assertSee('03/01/2026')->assertSee('07/01/2026')->assertSee('01/02/2026');
    }

    public function test_list_search_and_date_filters_work_on_dates(): void
    {
        Protocol::factory()->number(1)->onDate('20260110')->create(['thema' => 'Δέκα Ιανουαρίου', 'in_date' => 20260108]);
        Protocol::factory()->number(2)->onDate('20260220')->create(['thema' => 'Είκοσι Φεβρουαρίου', 'in_date' => 20260219]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/home/list')->assertOk()->assertSee('10/01/2026')->assertSee('20/02/2026');

        $q = ['s' => 1, 'year' => ''];   // R3a search screen
        $this->actingAs($admin)->get('/find?' . http_build_query($q + ['from' => '01/01/2026', 'to' => '31/01/2026']))
            ->assertOk()->assertSee('Δέκα Ιανουαρίου')->assertDontSee('Είκοσι Φεβρουαρίου');
        $this->actingAs($admin)->get('/find?' . http_build_query($q + ['in_from' => '15/02/2026']))
            ->assertOk()->assertSee('Είκοσι Φεβρουαρίου')->assertDontSee('Δέκα Ιανουαρίου');
    }

    public function test_expired_attachments_are_found_by_date(): void
    {
        $old = Attachment::factory()->withFile()->create(['expires' => 20200101]);
        $current = Attachment::factory()->withFile()->create(['expires' => 29990101]);

        \Livewire\Livewire::actingAs($this->admin())->test(\App\Livewire\ArchivePage::class)->call('trashExpired');   // R6c (was GET /delExpired)

        $this->assertTrue(Attachment::withTrashed()->find($old->id)->trashed());
        $this->assertFalse(Attachment::withTrashed()->find($current->id)->trashed());
        $this->assertSame(29990101, Attachment::find($current->id)->expires);
    }

    public function test_same_incoming_number_check_compares_dates(): void
    {
        Protocol::factory()->number(1)->create(['in_num' => 'Φ.7/123', 'in_date' => 20260305]);

        // R1b: a notice next to the field (was the checkInNum call + YES/NO dialog)
        \Livewire\Livewire::actingAs($this->writer())->test(\App\Livewire\ProtocolForm::class)
            ->set('form.in_num', 'Φ.7/123')->set('form.in_date', '05/03/2026')->assertSee('id="notice-in_num"', false)
            ->set('form.in_date', '06/03/2026')->assertDontSee('id="notice-in_num"', false);
    }
}
