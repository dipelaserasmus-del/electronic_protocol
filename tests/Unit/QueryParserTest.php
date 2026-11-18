<?php

namespace Tests\Unit;

use App\Services\Search\QueryParser;
use App\Services\Search\SearchCriteria;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/** R3b: the Gmail-style search text (App\Services\Search\QueryParser). */
class QueryParserTest extends TestCase
{
    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = CarbonImmutable::create(2026, 11, 18);
    }

    private function parse(string $text, ?QueryParser &$parser = null): SearchCriteria
    {
        $parser = new QueryParser();
        return $parser->parse($text, $this->today);
    }

    public function test_free_words_and_quoted_phrases(): void
    {
        $c = $this->parse('  αίτηση  "άδεια μητρότητας"  125/2026 ');
        $this->assertSame(['αίτηση', 'άδεια μητρότητας', '125/2026'], $c->words);
        $this->assertFalse($c->isEmpty());
        $this->assertTrue($this->parse('   ')->isEmpty());
    }

    public function test_operators_ignore_case_and_accents_and_have_short_and_english_names(): void
    {
        foreach (['φάκελος:Φ.1', 'ΦΑΚΕΛΟΣ:Φ.1', 'φακελος:Φ.1', 'φακ:Φ.1', 'folder:Φ.1'] as $text) {
            $this->assertSame('Φ.1', $this->parse($text)->folder, $text);
        }
        $c = $this->parse('από:δήμος προς:"Διεύθυνση Π.Ε." θέμα:μετάθεση εισ:12345 σχετ:12/2026 λέξη:διαύγεια αδα:ΩΡΖ συνημμένο:απόφαση');
        $this->assertSame([
            'in_arxi_ekdosis' => 'δήμος', 'out_to' => 'Διεύθυνση Π.Ε.', 'thema' => 'μετάθεση', 'in_num' => '12345',
            'sxetiko' => '12/2026', 'keywords' => 'διαύγεια', 'ada' => 'ΩΡΖ', 'name' => 'απόφαση',
        ], $c->text);
        $this->assertSame([], $c->words);
    }

    public function test_number_year_and_dates(): void
    {
        $c = $this->parse('αρ:120-130 έτος:2025');
        $this->assertSame([120, 130, 2025], [$c->number, $c->numberTo, $c->year]);
        $c = $this->parse('αρ:125/2024');
        $this->assertSame([125, null, 2024], [$c->number, $c->numberTo, $c->year]);
        $this->assertNull($this->parse('έτος:όλα')->year);

        [$from, $to] = $this->parse('ημ:9/1')->dates['protocoldate'];
        $this->assertSame(['09/01/2026', '09/01/2026'], [$from->format('d/m/Y'), $to->format('d/m/Y')], 'one day, short form');
        [$from, $to] = $this->parse('ημ:1/1/2026..31/3/2026')->dates['protocoldate'];
        $this->assertSame(['01/01/2026', '31/03/2026'], [$from->format('d/m/Y'), $to->format('d/m/Y')]);
        [$from, $to] = $this->parse('ημεισ:..15/2/2026')->dates['in_date'];
        $this->assertSame([null, '15/02/2026'], [$from, $to->format('d/m/Y')], 'open start');
        [$from, $to] = $this->parse('ημ:31/3/2026-1/1/2026')->dates['protocoldate'];
        $this->assertSame('01/01/2026', $from->format('d/m/Y'), 'a reversed range is turned round');
    }

    public function test_folder_assignee_empty_and_pending(): void
    {
        $this->assertSame(SearchCriteria::NO_FOLDER, $this->parse('φάκελος:κανένας')->folder);
        $this->assertSame(SearchCriteria::NO_FOLDER, $this->parse('φάκελος:χωρίς')->folder);
        $this->assertSame('Παπαδόπουλος', $this->parse('ανάθεση:Παπαδόπουλος')->assigneeName);
        $this->assertSame(['diekperaiosi'], $this->parse('ανάθεση:κανένας')->empty);
        $this->assertSame(['fakelos', 'keywords'], $this->parse('κενό:φάκελος κενό:λέξη')->empty);
        $this->assertTrue($this->parse('είναι:εκκρεμές')->pending);
        $this->assertTrue($this->parse('is:pending')->pending);
    }

    public function test_unknown_operators_are_words_and_wrong_values_are_reported(): void
    {
        $c = $this->parse('ώρα:10 10:30 http://example.test', $p);
        $this->assertSame(['ώρα:10', '10:30', 'http://example.test'], $c->words);
        $this->assertSame([], $p->ignored);

        $c = $this->parse('έτος:abc αρ:x12 ημ:31/2/2026 κενό:κάτι είναι:τίποτα θέμα: θέμα:α θέμα:β', $p);
        $this->assertNull($c->year);
        $this->assertNull($c->number);
        $this->assertSame([], $c->dates);
        $this->assertSame(['thema' => 'α'], $c->text);
        $this->assertCount(7, $p->ignored);
        $this->assertStringContainsString('«έτος:abc»', $p->ignored[0]);
        $this->assertStringContainsString('μία τιμή ανά πεδίο', $p->ignored[6]);
    }

    public function test_an_unclosed_quote_takes_the_rest(): void
    {
        $this->assertSame(['άδεια μητρότητας'], $this->parse('"άδεια μητρότητας')->words);
        $this->assertSame(['thema' => 'άδεια μητρότητας'], $this->parse('θέμα:"άδεια μητρότητας')->text);
    }

    /** format() writes what parse() reads back: the advanced screen shows its search as text (R3b) */
    public function test_format_and_parse_give_the_same_criteria(): void
    {
        foreach ([
            'αίτηση "άδεια μητρότητας" αρ:120-130 έτος:2026 ημ:01/01/2026..31/03/2026 φάκελος:Φ.1 θέμα:"νέο θέμα" προς:δήμος είναι:εκκρεμές',
            'ημεισ:..15/02/2026 ημεξ:03/03/2026 φάκελος:κανένας ανάθεση:κανένας κενό:λέξη',
            'αρ:7 έτος:2025 αδα:ΩΡΖ συνημμένο:απόφαση ανάθεση:"Μαρία Π."',
        ] as $text) {
            $first = $this->parse($text);
            $again = $this->parse(QueryParser::format($first));
            $this->assertEquals($first, $again, $text);
            $this->assertSame($text, QueryParser::format($first), 'written in the usual order');
        }
    }
}
