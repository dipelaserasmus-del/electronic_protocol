<?php

namespace Tests\Feature;

use App\Models\Protocol;
use App\Services\DateOrder;
use Carbon\Carbon;

/** R2f (owner, 2026-11-19): dates out of order with the numbers, or in the future, are flagged (warnings). */
class DateOrderTest extends FeatureTestCase
{
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = Carbon::create(2026, 11, 30);
    }

    /** @param list<string> $days "d/m" per number 1, 2, …; returns the numbers flagged as out of order */
    private function flagged(array $days, int $year = 2026): array
    {
        foreach ($days as $i => $d) {
            [$day, $month] = array_map('intval', explode('/', $d));
            Protocol::factory()->number($i + 1)->create(['etos' => $year, 'protocoldate' => Carbon::create($year, $month, $day)->toDateString()]);
        }
        $flags = (new DateOrder())->flags([$year], $this->today);
        $numbers = Protocol::whereIn('id', array_keys(array_filter($flags, fn ($f) => in_array(DateOrder::ORDER, $f['kinds'], true))))
            ->orderBy('protocolnum')->pluck('protocolnum')->map(fn ($n) => (int) $n)->all();
        Protocol::query()->delete();

        return $numbers;
    }

    public function test_the_odd_date_is_flagged_not_its_neighbours(): void
    {
        $this->assertSame([3], $this->flagged(['10/11', '10/11', '20/11', '10/11', '10/11']), 'a later date among others');
        $this->assertSame([3], $this->flagged(['10/11', '10/11', '1/11', '10/11', '10/11']), 'an earlier date among others');
    }

    public function test_a_new_day_starting_is_normal(): void
    {
        $this->assertSame([], $this->flagged(['9/11', '9/11', '10/11', '10/11', '11/11', '13/11']));
    }

    public function test_the_first_and_the_last_protocol_are_judged_too(): void
    {
        $this->assertSame([1], $this->flagged(['20/11', '10/11', '10/11', '11/11']), 'first');
        $this->assertSame([4], $this->flagged(['10/11', '10/11', '11/11', '5/11']), 'last');
    }

    public function test_several_wrong_dates_in_a_row(): void
    {
        $this->assertSame([3, 4], $this->flagged(['10/11', '10/11', '20/11', '20/11', '10/11', '10/11', '11/11']));
    }

    public function test_each_year_on_its_own(): void
    {
        Protocol::factory()->number(1)->create(['etos' => 2025, 'protocoldate' => '2025-12-31']);
        $this->assertSame([], $this->flagged(['2/1', '3/1']), 'the new year starts from 1 with earlier dates than December');
    }

    public function test_a_date_after_today_is_flagged(): void
    {
        $p = Protocol::factory()->number(1)->create(['etos' => 2026, 'protocoldate' => '2026-12-05']);
        $flags = (new DateOrder())->flags([2026], $this->today);
        $this->assertSame([DateOrder::FUTURE], $flags[$p->id]['kinds']);
        $this->assertSame('Η ημερομηνία είναι μετά τη σημερινή.', DateOrder::message($flags[$p->id]));
    }

    public function test_the_explanation_names_the_neighbours(): void
    {
        foreach (['2026-11-10', '2026-11-20', '2026-11-10'] as $i => $d) {
            Protocol::factory()->number($i + 1)->create(['etos' => 2026, 'protocoldate' => $d]);
        }
        $flags = (new DateOrder())->flags([2026], $this->today);
        $this->assertCount(1, $flags);
        $this->assertSame('Η ημερομηνία δεν ταιριάζει με τη σειρά των αριθμών: ο προηγούμενος (1) έχει 10/11/2026 και ο '
            .'επόμενος (3) 10/11/2026. Μήπως γράφτηκε λάθος;', DateOrder::message(reset($flags)));
    }

    /** The form's check of one date against the numbers around it. */
    public function test_a_date_typed_in_the_form(): void
    {
        foreach (['2026-11-10', '2026-11-12', '2026-11-14'] as $i => $d) {
            Protocol::factory()->number(($i + 1) * 10)->create(['etos' => 2026, 'protocoldate' => $d]);
        }
        $order = new DateOrder();
        $at = fn (string $d) => Carbon::parse($d);

        $this->assertNull($order->check($at('2026-11-14'), 2026, null, null, $this->today), 'new: the day of the last one');
        $this->assertSame([DateOrder::ORDER], $order->check($at('2026-11-13'), 2026, null, null, $this->today)['kinds'], 'new: before the last one');
        $this->assertSame([DateOrder::FUTURE], $order->check($at('2026-12-01'), 2026, null, null, $this->today)['kinds']);

        // a wrongly dated last protocol neither hides a wrong new date nor makes a right one look wrong
        Protocol::factory()->number(40)->create(['etos' => 2026, 'protocoldate' => '2026-11-01']);
        $this->assertSame([DateOrder::ORDER], $order->check($at('2026-11-01'), 2026, null, null, $this->today)['kinds']);
        $this->assertNull($order->check($at('2026-11-14'), 2026, null, null, $this->today));
        Protocol::where('protocolnum', 40)->delete();

        $edited = Protocol::where('protocolnum', 20)->firstOrFail();
        $this->assertNull($order->check($at('2026-11-11'), 2026, 20, $edited->id, $this->today), 'between its neighbours');
        $flag = $order->check($at('2026-11-20'), 2026, 20, $edited->id, $this->today);
        $this->assertSame([DateOrder::ORDER], $flag['kinds']);
        $this->assertSame([10, 30], [$flag['prev']['num'], $flag['next']['num']]);
    }
}
