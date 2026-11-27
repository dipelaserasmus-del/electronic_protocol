<?php

namespace Tests\Feature;

use App\Livewire\PrintPage;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use App\Services\Printing\PrintRange;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

/** R7: printing (protocol book, attachments, receipt) and the Excel export, rebuilt on the print layout. */
class PrintTest extends FeatureTestCase
{
    private function book(): void
    {
        $handler = User::factory()->writer()->inactive()->create(['name' => 'Ανενεργή Διεκπεραιώτρια']);
        $p = Protocol::factory()->number(5, 2026)->create([
            'protocoldate' => '2026-03-04', 'thema' => 'Αίτηση μετάθεσης', 'in_num' => 'Φ.12/345', 'in_date' => '2026-03-01',
            'in_topos_ekdosis' => 'Αθήνα', 'in_arxi_ekdosis' => 'Υπουργείο', 'in_paraliptis' => 'Διευθυντής', 'out_date' => '2026-03-10',
            'out_to' => 'Περιφέρεια', 'out_perilipsi' => 'Διαβιβαστικό', 'diekp_date' => '2026-03-11', 'fakelos' => 'Φ.1',
            'sxetiko' => '3/2026', 'paratiriseis' => 'Σημείωση <b>όχι html</b>',
        ]);
        ProtocolAssignment::create(['protocol_id' => $p->id, 'user_id' => $handler->id, 'type' => 'd']);
        Protocol::factory()->number(6, 2026)->create(['protocoldate' => '2026-03-20', 'thema' => 'Έκτο']);
        Protocol::factory()->number(1, 2025)->create(['protocoldate' => '2025-01-02', 'thema' => 'Περσινό']);
    }

    public function test_the_book_prints_the_chosen_year_with_dates_and_names(): void
    {
        $this->book();
        $this->actingAs($this->writer())->get('/printed?etosForMany=2026')->assertOk()
            ->assertSee('Πρωτόκολλο έτους 2026')->assertSee('2 πρωτόκολλα')
            ->assertSeeTextInOrder(['5', '04/03/2026', 'Φ.12/345/01/03/2026', '✧ Αθήνα', '✦ Υπουργείο', 'Αίτηση μετάθεσης', 'Διευθυντής',
                '10/03/2026', '✧ Περιφέρεια', 'Διαβιβαστικό', 'Ανενεργή Διεκπεραιώτρια', '✧ 11/03/2026', 'Φ.1', '✧ 3/2026', '✦ Σημείωση'])
            ->assertSee('Έκτο')->assertDontSee('Περσινό')
            ->assertSee('Σημείωση &lt;b&gt;όχι html&lt;/b&gt;', false)
            ->assertSee('13, 12')->assertDontSee('20260304')
            ->assertSee('Σύνολο: 2 πρωτόκολλα');
    }

    /**
     * R2s (owner, 2026-11-26): the printout restyled for paper: a sheet on screen, page numbers and the institution
     * with the title at each page's foot (CSS text, escaped as CSS: entities mean nothing inside <style>).
     */
    public function test_the_book_is_laid_out_for_paper(): void
    {
        $this->book();
        $this->setting('ipiresiasName', 'Σχολείο "Δοκιμών" </style><b>');
        $html = $this->actingAs($this->writer())->get('/printed?etosForMany=2026')->assertOk()->getContent();
        $this->assertStringContainsString('@bottom-right { content: "Σελίδα " counter(page) " από " counter(pages);', $html);
        $this->assertStringContainsString('@bottom-left { content: "Σχολείο \\"Δοκιμών\\" \\3c /style\\3e \\3c b\\3e  · Πρωτόκολλο έτους 2026";', $html);
        $this->assertStringNotContainsString('</style><b>', $html, 'the name cannot close the styles');
        $this->assertStringContainsString('<main class="sheet landscape">', $html);
        $this->assertStringContainsString('<span class="mk">✧</span> Αθήνα', $html);
        $this->assertSame(8, substr_count(substr($html, strpos($html, '<colgroup>'), 600), '<col '), 'a width for each of the 8 columns');
        $receipt = $this->actingAs($this->admin())->get('/receipt/'.\App\Models\Protocol::where('protocolnum', 5)->value('id'));
        $receipt->assertOk()->assertSee('<main class="sheet portrait">', false);
    }

    public function test_ranges_of_numbers_and_dates_and_several_years(): void
    {
        $this->book();
        $writer = $this->writer();
        $this->actingAs($writer)->get('/printed?etosForMany=2026&aponum=6')->assertSee('Έκτο')->assertDontSee('Αίτηση μετάθεσης');
        $this->actingAs($writer)->get('/printed?apoProtocolDate=1/1/25&eosProtocolDate=05/03/2026')
            ->assertSee('Περσινό')->assertSee('Αίτηση μετάθεσης')->assertDontSee('Έκτο')
            ->assertSee('1/2025');   // several years: the year beside the number
        $this->actingAs($writer)->post('/printed', ['etosForMany' => '2026', 'eosnum' => '5'])->assertOk()->assertDontSee('Έκτο');   // the old form's POST
        $this->actingAs($writer)->get('/printed')->assertRedirect('/print');
    }

    public function test_the_limit_is_said_not_hidden(): void
    {
        $this->book();
        config(['app.print_max_rows' => 1]);   // R6m: PRINT_MAX_ROWS
        $this->actingAs($this->writer())->get('/printed?etosForMany=2026')->assertSee('από 2: τα υπόλοιπα δεν χωρούν')->assertDontSee('Έκτο');
    }

    public function test_excel_export(): void
    {
        $this->book();
        Excel::fake();
        $this->actingAs($this->writer())->get('/printed?etosForMany=2026&target=xls');
        Excel::matchByRegex();
        Excel::assertDownloaded('/πρωτόκολλο έτους 2026 - \d{8}-\d{6}\.xlsx$/u', function (\App\Exports\ProtocolExport $export) {
            $html = $export->view()->render();
            return str_contains($html, '04/03/2026') && str_contains($html, 'Ανενεργή Διεκπεραιώτρια') && ! str_contains($html, 'Περσινό');
        });
    }

    public function test_attachments_print_lists_every_file_with_its_own_keep_and_expiry(): void
    {
        $p = Protocol::factory()->number(9, 2026)->create(['thema' => 'Με δύο αρχεία', 'fakelos' => 'Φ.1']);
        Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'πρώτο.pdf', 'keep' => 5, 'expires' => 20310101]);
        Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'δεύτερο.pdf', 'keep' => 'Μόνιμα', 'expires' => null]);
        Protocol::factory()->number(10, 2026)->create(['thema' => 'Χωρίς αρχεία']);

        $this->actingAs($this->writer())->get('/printedAttachments?etosForMany=2026')->assertOk()
            ->assertSeeInOrder(['Με δύο αρχεία', 'πρώτο.pdf', '5 έτη', '01/01/2031', 'δεύτερο.pdf', 'Μόνιμα'])
            ->assertDontSee('Χωρίς αρχεία');
        $this->actingAs($this->reader())->get('/printedAttachments?etosForMany=2026')->assertRedirect();   // writers only, as before
    }

    public function test_the_print_page_counts_before_printing(): void
    {
        $this->book();
        Livewire::actingAs($this->writer())->test(PrintPage::class)
            ->set('year', '2026')->assertSee('2 πρωτόκολλα')->assertSee('/printed?etosForMany=2026', false)
            ->set('from', 'abc')->assertSee('Από αριθμό «abc»')
            ->set('from', '')->set('year', '')->assertSee('Συμπληρώστε έτος')
            ->set('year', '2030')->assertSee('Κανένα πρωτόκολλο');
        Livewire::actingAs($this->reader())->test(PrintPage::class, ['mode' => 'attachments'])->assertSet('mode', 'book');
        $this->actingAs($this->admin())->get('/print')->assertOk()->assertSee('Βιβλίο πρωτοκόλλου');
        $this->actingAs($this->admin())->get('/printAttachments')->assertOk()->assertSee('Συνημμένα αρχεία');
    }

    public function test_the_receipt(): void
    {
        $p = Protocol::factory()->number(12, 2026)->create(['protocoldate' => '2026-05-06', 'thema' => 'Θέμα απόδειξης']);
        $this->actingAs($this->writer())->get('/receipt/'.$p->id)->assertOk()
            ->assertSee('Απόδειξη καταχώρισης')->assertSee('12/2026')->assertSee('06/05/2026')->assertSee('Θέμα απόδειξης');

        // R2v (owner, 2026-11-27): restyled: the number and the date in the box, the details filled in (only those), a
        // place to sign and stamp; text from the protocol stays text
        $q = Protocol::factory()->number(13, 2026)->create(['protocoldate' => '2026-05-07', 'thema' => '<b>Αίτηση</b> & δικαιολογητικά',
            'in_arxi_ekdosis' => 'Γιάννης Παπαδόπουλος', 'in_num' => '45', 'in_date' => '2026-05-05', 'fakelos' => 'Φ.1', 'in_topos_ekdosis' => '']);
        $html = $this->actingAs($this->writer())->get('/receipt/'.$q->id)->assertOk()->getContent();
        $this->assertStringContainsString('<main class="sheet portrait">', $html);
        $this->assertMatchesRegularExpression('~id="receipt-number">13/2026</span>~', $html);
        $this->assertMatchesRegularExpression('~id="receipt-date">07/05/2026</span>~', $html);
        $this->assertStringContainsString('&lt;b&gt;Αίτηση&lt;/b&gt; &amp; δικαιολογητικά', $html);
        $this->assertStringNotContainsString('<b>Αίτηση</b>', $html);
        foreach (['Αρχή έκδοσης</th><td>Γιάννης Παπαδόπουλος', 'Αρ./Ημ. εισερχομένου</th><td>45 / 05/05/2026', 'Φάκελος</th><td>Φ.1'] as $row) {
            $this->assertStringContainsString($row, $html);
        }
        $this->assertStringNotContainsString('Τόπος έκδοσης', $html, 'an empty detail is left out');
        $this->assertStringContainsString('Ο/Η παραλαβών/ούσα', $html);
        $this->assertStringContainsString('class="stamp">Σφραγίδα', $html);
    }

    public function test_range_rules(): void
    {
        $r = PrintRange::fromInput(['etosForMany' => '2026', 'aponum' => '1x', 'apoProtocolDate' => '31/02/2026', 'eosProtocolDate' => '9/4/26']);
        $this->assertSame(2026, $r->year);
        $this->assertNull($r->from);
        $this->assertSame(['Από αριθμό «1x»', 'Από ημ/νία «31/02/2026»'], $r->ignored);
        $this->assertSame('2026-04-09', $r->toDate->toDateString());
        $this->assertTrue(PrintRange::fromInput([])->isEmpty());
    }
}
