<?php

namespace Tests\Feature;

use App\Livewire\IntegrityChecks;
use App\Livewire\ProtocolList;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Integrity\Checks;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\Integrity\Rules\DateOrderRule;
use App\Services\Integrity\Rules\DuplicateRule;
use App\Services\Integrity\Rules\MissingAttachmentRule;
use App\Services\Integrity\Rules\NumberGapRule;
use App\Services\Integrity\Rules\PendingRule;
use App\Services\NumberGaps;
use App\Support\Navigation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** A01: the integrity-check engine, the «Έλεγχοι» page and the list's line. */
class IntegrityChecksTest extends FeatureTestCase
{
    private function book(int $year, array $numbers, string $mmdd): void
    {
        foreach ($numbers as $n) {
            $this->complete(Protocol::factory()->number($n, $year)->onDate($year.$mmdd)->create(['fakelos' => 'Φ.1']));
        }
    }

    /** A complete protocol for the other checks: a folder (not pending, A04) and an attachment (an ΑΔΑ row, A06) */
    private function complete(Protocol $p): Protocol
    {
        \App\Models\Attachment::factory()->create(['protocol_id' => $p->id, 'ada' => 'ΨΔ'.$p->id, 'name' => null, 'mimeType' => null]);

        return $p;
    }

    private function between(string $from, string $to, string $today = '2026-11-12'): array
    {
        return app(NumberGaps::class)->between(Carbon::parse($from), Carbon::parse($to), Carbon::parse($today));
    }

    public function test_gaps_between_the_protocols_of_a_range(): void
    {
        $this->book(2026, [1, 3], '0201');
        $this->book(2026, [10, 12, 13, 17], '1101');

        // 4–9 are missing next to 10, the range's first protocol, so November reports them too (and February)
        $this->assertSame([2026 => [4, 5, 6, 7, 8, 9, 11, 14, 15, 16]], $this->between('2026-11-01', '2026-11-12'));
        $this->assertSame([2026 => [2, 4, 5, 6, 7, 8, 9]], $this->between('2026-02-01', '2026-02-28'));
        $this->assertSame([], $this->between('2026-11-02', '2026-11-12'), 'no protocol dated in the range: nothing to check');
    }

    public function test_a_gap_at_the_edge_of_the_range_counts(): void
    {
        // 5 is dated 31/10, 7 on 1/11: the missing 6 sits between the ranges, so both of them report it
        $this->book(2026, [4, 5], '1031');
        $this->book(2026, [7, 8], '1101');

        $this->assertSame([2026 => [6]], $this->between('2026-11-01', '2026-11-12'));
        $this->assertSame([2026 => [6]], $this->between('2026-10-01', '2026-10-31'));
        $this->assertSame([], $this->between('2026-09-01', '2026-09-30'), 'no protocols in the range');
    }

    public function test_ranges_over_new_year_and_the_start_number(): void
    {
        $this->book(2025, [100, 102], '1220');
        $this->book(2026, [1, 3, 50, 52], '0105');
        $this->setting('firstProtocolNum', 50);   // the current year only, as A02

        $this->assertSame([2025 => [101], 2026 => [51]], $this->between('2025-12-01', '2026-01-31', '2026-01-31'));
    }

    public function test_only_gaps_next_to_a_protocol_of_the_range_count_when_dates_are_out_of_order(): void
    {
        // review of A01: 1 and 5 in November, 2 and 4 in February; 3 lies between 2 and 4, both February
        $this->book(2026, [1, 5], '1110');
        $this->book(2026, [2, 4], '0210');

        $this->assertSame([], $this->between('2026-11-01', '2026-11-30'));
        $this->assertSame([2026 => [3]], $this->between('2026-02-01', '2026-02-28'));
    }

    public function test_a_book_year_other_than_the_date_s_year_is_checked(): void
    {
        // review of A01: numbers of 2025 dated January 2026
        $this->book(2025, [1, 3], '0101');
        Protocol::where('etos', 2025)->update(['protocoldate' => '2026-01-15']);

        $this->assertSame([2025 => [2]], $this->between('2026-01-01', '2026-01-31', '2026-01-31'));
    }

    public function test_the_gap_rule_names_the_protocols_around_each_run(): void
    {
        Protocol::factory()->number(87, 2026)->onDate('20261103')->create();
        Protocol::factory()->number(89, 2026)->onDate('20261105')->create();
        Protocol::factory()->number(95, 2026)->onDate('20261106')->create();

        $findings = app(NumberGapRule::class)->findings(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(['88/2026', '90–94/2026'], array_map(fn (Finding $f) => $f->ref, $findings));
        $this->assertSame('Λείπει 1 αριθμός, ανάμεσα στο 87 (03/11/2026) και στο 89 (05/11/2026).', $findings[0]->message);
        $this->assertSame('Λείπουν 5 αριθμοί, ανάμεσα στο 89 (05/11/2026) και στο 95 (06/11/2026).', $findings[1]->message);
        $this->assertNull($findings[0]->protocolId);
        $this->assertSame(['year' => 2026, 'from' => 90, 'to' => 94], $findings[1]->meta);
    }

    /** A03: the dates rule's findings from $from to $to, as [ref => message] */
    private function dates(string $from, string $to, ?\App\Models\User $viewer = null): array
    {
        $found = app(DateOrderRule::class)->findings(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $viewer);

        return array_combine(array_map(fn (Finding $f) => $f->ref, $found), array_map(fn (Finding $f) => $f->message, $found));
    }

    public function test_a_date_going_backwards_is_found(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00'));
        $this->book(2026, [10], '1103');
        $this->book(2026, [11], '1120');   // typed wrong: between 03/11 and 04/11
        $this->book(2026, [12], '1104');
        $this->book(2026, [13], '1105');

        $found = $this->dates('2026-11-01', '2026-11-10');
        $this->assertSame(['11/2026'], array_keys($found), 'dated outside the range, but its neighbours are in it');
        $this->assertStringContainsString('ο προηγούμενος (10) έχει 03/11/2026 και ο επόμενος (12) 04/11/2026', $found['11/2026']);
        $this->assertSame(['11/2026'], array_keys($this->dates('2026-11-20', '2026-11-20')));
        $this->assertSame([], $this->dates('2026-11-21', '2026-11-27'), 'no protocol of the range or next to it');
        $this->assertSame([], $this->dates('2026-10-01', '2026-10-31'), 'no protocols in the range');
    }

    public function test_a_date_after_today_is_found(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:00'));
        $this->book(2026, [20, 21], '1110');
        $this->book(2026, [22], '1201');   // in order, but after today

        $found = $this->dates('2026-10-14', '2026-11-12');
        $this->assertSame(['22/2026'], array_keys($found));
        $this->assertStringContainsString('μετά τη σημερινή', $found['22/2026']);
        $this->assertStringNotContainsString('σειρά', $found['22/2026']);
    }

    public function test_a_date_typed_a_year_wrong_is_found_in_its_book_year(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00'));
        $this->book(2026, [29, 30], '1103');
        Protocol::factory()->create(['protocolnum' => 31, 'etos' => 2026, 'protocoldate' => 20251104]);
        $this->book(2026, [32, 33], '1105');

        $this->assertSame(['31/2026'], array_keys($this->dates('2026-11-01', '2026-11-30')));
    }

    public function test_a_limited_writer_sees_only_their_own_and_no_other_s_numbers(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 10:00'));
        $writer = $this->writer();
        $other = $this->writer();
        Protocol::factory()->number(40, 2026)->onDate('20261103')->create(['user_id' => $other->id]);
        Protocol::factory()->number(41, 2026)->onDate('20261120')->create(['user_id' => $writer->id]);   // wrong, own
        Protocol::factory()->number(42, 2026)->onDate('20261104')->create(['user_id' => $other->id]);
        Protocol::factory()->number(43, 2026)->onDate('20261125')->create(['user_id' => $other->id]);   // wrong, other's
        Protocol::factory()->number(44, 2026)->onDate('20261105')->create(['user_id' => $other->id]);
        $this->setting('limitProtocolAccessList', 1);
        [$from, $to] = [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-28')];

        $all = app(Checks::class)->run($this->admin(), $from, $to)['dates']['findings'];
        $this->assertSame(['41/2026', '43/2026'], array_map(fn (Finding $f) => $f->ref, $all));
        $this->assertStringContainsString('(40)', $all[0]->message);

        $seen = app(Checks::class)->run($writer, $from, $to)['dates']['findings'];
        $this->assertSame(['41/2026'], array_map(fn (Finding $f) => $f->ref, $seen));
        $this->assertStringNotContainsString('(40)', $seen[0]->message, 'another user\'s protocol is not named');
        $this->assertStringContainsString('Μήπως γράφτηκε λάθος', $seen[0]->message);
    }

    public function test_the_dates_rule_is_on_the_page_and_counted_in_the_list(): void
    {
        $today = CarbonImmutable::today();
        $year = $today->year;
        Protocol::factory()->number(1, $year)->onDate($today->subDays(2)->format('Ymd'))->create();
        Protocol::factory()->number(2, $year)->onDate($today->format('Ymd'))->create();   // wrong: after 3
        Protocol::factory()->number(3, $year)->onDate($today->subDay()->format('Ymd'))->create();
        Protocol::factory()->number(4, $year)->onDate($today->format('Ymd'))->create();

        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->assertSeeHtml('id="check-dates" data-check="dates" data-count="1"')->assertSee('Ημερομηνίες εκτός σειράς')
            ->assertSee('2/'.$year);
        Livewire::actingAs($this->admin())->test(ProtocolList::class)
            ->assertSeeHtml('id="checks-notice"')->assertSee('1 εύρημα τις τελευταίες 30 ημέρες');
    }

    /** A05: the duplicates rule's findings from $from to $to, as [ref => message] */
    private function duplicates(string $from, string $to, ?\App\Models\User $viewer = null): array
    {
        $found = app(DuplicateRule::class)->findings(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $viewer);

        return array_combine(array_map(fn (Finding $f) => $f->ref, $found), array_map(fn (Finding $f) => $f->message, $found));
    }

    private function registered(int $num, string $ymd, array $attrs = []): Protocol
    {
        return $this->complete(Protocol::factory()->number($num, (int) substr($ymd, 0, 4))->onDate($ymd)
            ->create($attrs + ['fakelos' => 'Φ.1', 'in_num' => null, 'in_date' => null, 'in_arxi_ekdosis' => null, 'sxetiko' => null]));
    }

    private function file(Protocol $p, string $hash): void
    {
        \App\Models\Attachment::factory()->create(['protocol_id' => $p->id])->forceFill(['sha256' => str_repeat($hash, 64)])->save();
    }

    public function test_the_same_file_twice_is_a_possible_duplicate(): void
    {
        $first = $this->registered(10, '20261020');
        $this->file($first, 'a');
        $this->file($this->registered(11, '20261103'), 'a');
        $logo = array_map(fn ($n) => $this->registered($n, '20261104'), [12, 13, 14, 15]);
        foreach ($logo as $p) {
            $this->file($p, 'b');   // in 4 protocols: a logo or a signature, not a duplicate
        }

        $found = $this->duplicates('2026-11-01', '2026-11-30');
        $this->assertSame(['11/2026' => 'Μοιάζει με το 10/2026 (20/10/2026): ίδιο συνημμένο αρχείο.'], $found);
        $this->assertSame(['11/2026'], array_keys($this->duplicates('2026-10-01', '2026-10-31')), 'the other one dated in the range: about the later');
        \App\Models\Attachment::where('protocol_id', $logo[0]->id)->delete();
        \App\Models\Attachment::where('protocol_id', $logo[1]->id)->delete();
        $this->assertSame(['11/2026', '15/2026'], array_keys($this->duplicates('2026-11-01', '2026-11-30')), 'a deleted attachment does not count');
    }

    public function test_the_same_incoming_number_is_a_possible_duplicate_unless_the_senders_differ(): void
    {
        $this->registered(20, '20261102', ['in_num' => 'Φ.12/345/Δ1', 'in_arxi_ekdosis' => 'Διεύθυνση Α']);
        $this->registered(21, '20261103', ['in_num' => ' φ.12/345/δ1 ', 'in_arxi_ekdosis' => 'ΔΙΕΥΘΥΝΣΗ Α']);
        $this->registered(22, '20261104', ['in_num' => '100', 'in_arxi_ekdosis' => 'Δήμος']);
        $this->registered(23, '20261105', ['in_num' => '100', 'in_arxi_ekdosis' => 'Σχολείο']);   // another sender's 100
        $this->registered(24, '20261106', ['in_num' => '200', 'in_arxi_ekdosis' => 'Δήμος']);
        $this->registered(25, '20261107', ['in_num' => '200']);   // sender not written: alike
        foreach ([26, 27, 28, 29] as $n) {
            $this->registered($n, '20261108', ['in_num' => '1', 'in_arxi_ekdosis' => 'Δήμος']);   // shared by 4: routine
        }

        $this->assertSame([
            '21/2026' => 'Μοιάζει με το 20/2026 (02/11/2026): ίδιος αριθμός εισερχομένου.',
            '25/2026' => 'Μοιάζει με το 24/2026 (06/11/2026): ίδιος αριθμός εισερχομένου.',
        ], $this->duplicates('2026-11-01', '2026-11-30'));
    }

    public function test_the_same_subject_sender_and_incoming_date_is_a_possible_duplicate(): void
    {
        $same = ['thema' => 'Πρόσκληση σε σύσκεψη', 'in_arxi_ekdosis' => 'Περιφέρεια', 'in_date' => 20261101];
        $this->registered(30, '20261102', $same);
        $this->registered(31, '20261103', ['thema' => 'ΠΡΟΣΚΛΗΣΗ ΣΕ ΣΥΣΚΕΨΗ'] + $same);
        $this->registered(36, '20261003', ['thema' => ' Πρόσκληση  σε   σύσκεψη '] + $same);   // spaces (review of A05)
        $this->registered(37, '20261004', ['in_num' => 'Φ.9/12', 'in_arxi_ekdosis' => 'Δήμος']);
        $this->registered(38, '20261105', ['in_num' => ' Φ.9/12', 'in_arxi_ekdosis' => 'Δήμος']);
        $this->registered(32, '20261104', ['in_date' => 20261102] + $same);   // another day's invitation
        $this->registered(33, '20261105', ['in_arxi_ekdosis' => null] + $same);   // sender not written: not enough
        $this->registered(34, '20261106', ['thema' => 'ΕΛΕΥΘΕΡΟ', 'in_arxi_ekdosis' => 'Περιφέρεια', 'in_date' => 20261101]);
        $this->registered(35, '20261107', ['thema' => 'ΕΛΕΥΘΕΡΟ', 'in_arxi_ekdosis' => 'Περιφέρεια', 'in_date' => 20261101]);

        // 36 is dated before the range, its subject has other spaces: still alike, also as a candidate picked by SQL
        $lines = fn (string $from, string $to) => array_map(fn (Finding $f) => $f->ref.' '.$f->message,
            app(DuplicateRule::class)->findings(CarbonImmutable::parse($from), CarbonImmutable::parse($to)));
        $same = 'ίδιο θέμα, αποστολέας και ημερομηνία εισερχομένου.';
        $this->assertSame([
            "31/2026 Μοιάζει με το 30/2026 (02/11/2026): {$same}",
            "36/2026 Μοιάζει με το 30/2026 (02/11/2026): {$same}",
            "36/2026 Μοιάζει με το 31/2026 (03/11/2026): {$same}",
            '38/2026 Μοιάζει με το 37/2026 (04/10/2026): ίδιος αριθμός εισερχομένου.',
        ], $lines('2026-11-01', '2026-11-30'));
        $this->assertSame([
            "36/2026 Μοιάζει με το 30/2026 (02/11/2026): {$same}",
            "36/2026 Μοιάζει με το 31/2026 (03/11/2026): {$same}",
            '38/2026 Μοιάζει με το 37/2026 (04/10/2026): ίδιος αριθμός εισερχομένου.',
        ], $lines('2026-10-01', '2026-10-31'), 'only 36 and 37 in the range: the others are found through them');
    }

    public function test_protocols_that_name_each_other_are_not_duplicates(): void
    {
        $a = $this->registered(40, '20261102', ['in_num' => '500', 'in_arxi_ekdosis' => 'Δήμος']);
        $b = $this->registered(41, '20261103', ['in_num' => '500', 'in_arxi_ekdosis' => 'Δήμος']);
        $this->file($a, 'c');
        $this->file($b, 'c');
        $this->assertSame(['41/2026' => 'Μοιάζει με το 40/2026 (02/11/2026): ίδιο συνημμένο αρχείο, ίδιος αριθμός εισερχομένου.'],
            $this->duplicates('2026-11-01', '2026-11-30'), 'one line per pair, all the reasons');

        $b->update(['sxetiko' => 'Φ.3, 40/2026']);
        $this->assertSame([], $this->duplicates('2026-11-01', '2026-11-30'));
        $b->update(['sxetiko' => null]);
        $a->update(['sxetiko' => '041/2026']);
        $this->assertSame([], $this->duplicates('2026-11-01', '2026-11-30'), 'either one naming the other');
    }

    public function test_a_limited_writer_sees_their_own_duplicate_without_the_other_s_number(): void
    {
        $writer = $this->writer();
        $this->registered(50, '20261102', ['in_num' => '700', 'user_id' => $this->writer()->id]);
        $this->registered(51, '20261103', ['in_num' => '700', 'user_id' => $writer->id]);
        $this->setting('limitProtocolAccessList', 1);
        [$from, $to] = [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')];

        $this->assertSame(['Μοιάζει με το 50/2026 (02/11/2026): ίδιος αριθμός εισερχομένου.'],
            array_map(fn (Finding $f) => $f->message, app(Checks::class)->run($this->admin(), $from, $to)['duplicates']['findings']));
        $this->assertSame(['Μοιάζει με άλλο πρωτόκολλο: ίδιος αριθμός εισερχομένου.'],
            array_map(fn (Finding $f) => $f->message, app(Checks::class)->run($writer, $from, $to)['duplicates']['findings']));
    }

    public function test_duplicates_are_on_the_page_and_counted_in_the_list(): void
    {
        $today = CarbonImmutable::today();
        $this->registered(1, $today->format('Ymd'), ['in_num' => '900']);
        $this->registered(2, $today->format('Ymd'), ['in_num' => '900']);

        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->assertSeeHtml('id="check-duplicates" data-check="duplicates" data-count="1"')->assertSee('Πιθανές διπλές καταχωρίσεις');
        Livewire::actingAs($this->admin())->test(ProtocolList::class)->assertSee('1 εύρημα τις τελευταίες 30 ημέρες');
    }

    public function test_a_protocol_without_an_attachment_and_no_reason_is_found(): void
    {
        $at = fn (int $n, array $attrs = []) => Protocol::factory()->number($n, 2026)->onDate('20261105')
            ->create($attrs + ['fakelos' => 'Φ.1', 'sxetiko' => null, 'thema' => 'Βεβαίωση']);
        $at(1);                                              // nothing: found
        $this->complete($at(2));                             // an ΑΔΑ row
        \App\Models\Attachment::factory()->create(['protocol_id' => $at(3)->id]);   // a file
        \App\Models\Attachment::factory()->create(['protocol_id' => $at(4)->id])->delete();   // deleted: found
        $at(5, ['sxetiko' => '12/2026']);                    // the document is in the related protocol
        $at(6, ['thema' => 'Εκκρεμότητα: αναμένεται']);      // waiting for it (A04)
        $at(7, ['thema' => 'ΕΛΕΥΘΕΡΟ']);                     // a number kept free
        $at(8, ['fakelos' => null]);                         // no folder: on hold (owner, 2026-11-18)
        $at(9, ['fakelos' => ' ']);
        $at(10, ['sxetiko' => ' ']);                         // blank: found
        Protocol::factory()->number(11, 2026)->onDate('20261020')->create(['fakelos' => 'Φ.1', 'sxetiko' => null]);

        $found = app(MissingAttachmentRule::class)->findings(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));
        $this->assertSame(['1/2026', '4/2026', '10/2026'], array_map(fn (Finding $f) => $f->ref, $found));
        $this->assertSame('Δεν έχει συνημμένο αρχείο ούτε ΑΔΑ.', $found[0]->message);
    }

    public function test_missing_attachments_are_on_the_page_and_counted_in_the_list(): void
    {
        $today = CarbonImmutable::today();
        Protocol::factory()->number(1, $today->year)->onDate($today->format('Ymd'))->create(['fakelos' => 'Φ.1', 'sxetiko' => null]);

        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->assertSeeHtml('id="check-attachments" data-check="attachments" data-count="1"')->assertSee('Χωρίς συνημμένο');
        Livewire::actingAs($this->admin())->test(ProtocolList::class)->assertSee('1 εύρημα τις τελευταίες 30 ημέρες');
    }

    /** A04: the pending rule's findings from $from to $to, as [ref => message] */
    private function pending(string $from, string $to): array
    {
        $found = app(PendingRule::class)->findings(CarbonImmutable::parse($from), CarbonImmutable::parse($to));

        return array_combine(array_map(fn (Finding $f) => $f->ref, $found), array_map(fn (Finding $f) => $f->message, $found));
    }

    public function test_pending_protocols_of_the_range_are_listed(): void
    {
        $at = fn (int $n, array $attrs) => Protocol::factory()->number($n, 2026)->onDate('20261105')->create($attrs + ['fakelos' => 'Φ.1']);
        $at(1, ['thema' => 'Αίτηση γονέα']);
        $at(2, ['thema' => 'Εκκρεμότητα: αναμένεται το έγγραφο']);
        $at(3, ['thema' => 'ΕΚΚΡΕΜΟΤΗΤΕΣ ΣΧΟΛΙΚΟΥ ΕΤΟΥΣ']);
        $at(4, ['thema' => 'εκκρεμοτητα χωρίς τόνο']);
        $at(5, ['thema' => 'Υπηρεσιακή βεβαίωση', 'fakelos' => null]);
        $at(6, ['thema' => 'ΕΚΚΡΕΜΌΤΗΤΑ', 'fakelos' => '']);
        $at(7, ['thema' => 'ΕΛΕΥΘΕΡΟ', 'fakelos' => null]);   // a placeholder waiting for its content (owner, 2026-11-18)
        Protocol::factory()->number(8, 2026)->onDate('20261020')->create(['thema' => 'Εκκρεμότητα', 'fakelos' => 'Φ.1']);

        $found = $this->pending('2026-11-01', '2026-11-30');
        $this->assertSame(['2/2026', '3/2026', '4/2026', '5/2026', '6/2026', '7/2026'], array_keys($found));
        $this->assertSame('Εκκρεμότητα στο θέμα.', $found['2/2026']);
        $this->assertSame('Χωρίς φάκελο.', $found['5/2026']);
        $this->assertSame('Χωρίς φάκελο. Εκκρεμότητα στο θέμα.', $found['6/2026']);
        $this->assertSame(['8/2026'], array_keys($this->pending('2026-10-01', '2026-10-31')), 'only the protocols dated in the range');
    }

    public function test_pending_protocols_are_on_the_page_not_in_the_list_s_line(): void
    {
        $today = CarbonImmutable::today();
        $writer = $this->writer();
        $own = Protocol::factory()->number(1, $today->year)->onDate($today->format('Ymd'))->create(['thema' => 'Εκκρεμότητα', 'fakelos' => 'Φ.1', 'user_id' => $writer->id]);
        Protocol::factory()->number(2, $today->year)->onDate($today->format('Ymd'))->create(['thema' => 'Εκκρεμότητα', 'fakelos' => 'Φ.1', 'user_id' => $this->writer()->id]);

        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->assertSeeHtml('id="check-pending" data-check="pending" data-count="2"')->assertSee('Εκκρεμότητες');
        Livewire::actingAs($this->admin())->test(ProtocolList::class)->assertDontSeeHtml('id="checks-notice"');

        $this->setting('limitProtocolAccessList', 1);
        [$from, $to] = Checks::defaultRange();
        $seen = app(Checks::class)->run($writer, $from, $to)['pending']['findings'];
        $this->assertSame([$own->id], array_map(fn (Finding $f) => $f->protocolId, $seen));
    }

    public function test_the_page_lists_the_findings_of_the_last_30_days(): void
    {
        $today = CarbonImmutable::today();
        $this->book($today->year, [1, 3], $today->format('md'));
        $this->setting('yearInUse', $today->year);
        $this->setting('protocolValidate', 0);   // the administrator may type a number (A02)

        $html = $this->actingAs($this->admin())->get('/checks')->assertOk()->getContent();
        $this->assertStringContainsString('Έλεγχοι πρωτοκόλλου', $html);
        $this->assertStringContainsString('value="'.$today->subDays(29)->format('d/m/Y').'"', $html, 'from: 30 days ago, today included');
        $this->assertMatchesRegularExpression('~id="check-gaps"[^>]*data-count="1"~', $html);
        $this->assertStringContainsString('2/'.$today->year, $html);
        $this->assertStringContainsString('?num=2', $html, 'the administrator may register the missing number');
        $this->assertStringContainsString('1 εύρημα', $html);
    }

    public function test_a_range_without_problems_says_so(): void
    {
        $this->book(2026, [1, 2, 3], '0301');

        Livewire::actingAs($this->writer())->test(IntegrityChecks::class)
            ->set('from', '01/03/2026')->set('to', '31/3/2026')->call('check')
            ->assertSee('Κανένα πρόβλημα από 01/03/2026 έως 31/03/2026')
            ->assertDontSee('Καταχώριση με το');
    }

    public function test_a_wrong_range_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->set('from', '31/03/2026')->set('to', '01/03/2026')->call('check')->assertHasErrors('to')
            ->set('from', 'χθες')->set('to', '01/03/2026')->call('check')->assertHasErrors('from')
            ->set('from', '01/01/2020')->set('to', '01/03/2026')->call('check')->assertHasErrors('to');
    }

    public function test_quick_ranges(): void
    {
        $today = CarbonImmutable::today();
        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->call('range', 'year')->assertSet('from', $today->startOfYear()->format('d/m/Y'))->assertSet('to', $today->format('d/m/Y'))
            ->call('range', 'days')->assertSet('from', $today->subDays(29)->format('d/m/Y'));
    }

    public function test_who_may_open_the_checks(): void
    {
        foreach ([$this->admin(), $this->assigner(), $this->writer()] as $user) {
            $this->actingAs($user)->get('/checks')->assertOk();
            $labels = array_column(array_merge(...array_values(Navigation::manage($user))), 'label');
            $this->assertContains('Έλεγχοι πρωτοκόλλου', $labels);
        }
        $reader = $this->reader();
        $this->actingAs($reader)->get('/checks')->assertForbidden();
        $this->assertNotContains('Έλεγχοι πρωτοκόλλου', array_column(array_merge(...array_values(Navigation::manage($reader))), 'label'));
        auth()->logout();
        $this->get('/checks')->assertRedirect();
    }

    public function test_findings_about_protocols_a_limited_user_may_not_see_are_left_out(): void
    {
        $writer = $this->writer();
        $own = Protocol::factory()->create(['user_id' => $writer->id]);
        $other = Protocol::factory()->create(['user_id' => $this->writer()->id]);
        FakeRule::$found = [Finding::about($own, 'ένα'), Finding::about($other, 'άλλο'), new Finding('κενό', '5/2026')];
        $checks = new Checks([FakeRule::class]);
        [$from, $to] = Checks::defaultRange();

        $this->assertCount(3, $checks->run($writer, $from, $to)['fake']['findings'], 'without «Περιορισμός λίστας»: all');
        $this->setting('limitProtocolAccessList', 1);
        $seen = $checks->run($writer, $from, $to)['fake']['findings'];
        $this->assertSame(['ένα', 'κενό'], array_map(fn (Finding $f) => $f->message, $seen));
        $this->assertCount(3, $checks->run($this->admin(), $from, $to)['fake']['findings']);
        $this->assertSame(2, $checks->listCount($writer));
    }

    public function test_the_list_shows_a_line_when_checks_find_something(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(ProtocolList::class)->assertDontSeeHtml('id="checks-notice"');

        FakeRule::$found = [new Finding('κάτι', '1/2026'), new Finding('κάτι ακόμη', '2/2026')];
        $this->app->bind(Checks::class, fn () => new Checks([FakeRule::class]));
        Livewire::actingAs($admin)->test(ProtocolList::class)
            ->assertSeeHtml('id="checks-notice"')->assertSee('2 ευρήματα τις τελευταίες 30 ημέρες')
            ->assertSeeHtml('href="'.url('/checks').'"');
        Livewire::actingAs($this->reader())->test(ProtocolList::class)->assertDontSeeHtml('id="checks-notice"');
    }

    public function test_the_gaps_are_not_counted_twice_in_the_list(): void
    {
        $today = CarbonImmutable::today();
        $this->book($today->year, [1, 3], $today->format('md'));

        Livewire::actingAs($this->admin())->test(ProtocolList::class)
            ->assertSeeHtml('id="numberGaps"')->assertDontSeeHtml('id="checks-notice"');
    }
}

/** A rule whose findings a test sets */
class FakeRule implements Rule
{
    /** @var list<Finding> */
    public static array $found = [];

    public function key(): string { return 'fake'; }

    public function title(): string { return 'Δοκιμή'; }

    public function explanation(): string { return 'Δοκιμαστικός κανόνας.'; }

    public function inList(): bool { return true; }

    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array { return self::$found; }
}
