<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Protocol;
use App\Support\SubjectFromFile;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/** R1c: the attachments panel of the protocol form. */
class AttachmentsPanelTest extends FeatureTestCase
{
    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4 $name");
    }

    public function test_panel_is_open_on_new_and_copy(): void
    {
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->create(['user_id' => $writer->id]);
        foreach (['/home', "/home/{$p->id}/1"] as $url) {
            $this->actingAs($writer)->get($url)->assertOk()
                ->assertSee('id="f-uploads"', false)->assertSee('Σύρετε αρχεία οπουδήποτε στο παράθυρο');
        }
    }

    public function test_choosing_or_dropping_again_adds_to_the_files_already_chosen(): void
    {
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('picked', [$this->pdf('Α.pdf')])
            ->set('picked', [$this->pdf('Β.pdf'), $this->pdf('Γ.pdf')])
            ->assertCount('uploads', 3)->assertSet('picked', [])
            ->assertSee('Α.pdf')->assertSee('Γ.pdf')
            ->call('removeUpload', 1)
            ->assertCount('uploads', 2)->assertDontSee('Β.pdf');
    }

    public function test_each_new_file_keeps_its_own_ada(): void
    {
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('form.thema', 'Αποφάσεις')->set('form.fakelos', 'Φ.1')
            ->set('picked', [$this->pdf('Α.pdf'), $this->pdf('Β.pdf'), $this->pdf('Γ.pdf')])
            ->set('uploadAdas.0', 'ΑΔΑ-Α')->set('uploadAdas.2', 'ΑΔΑ-Γ')
            ->call('removeUpload', 1)
            ->call('save')->assertHasNoErrors();

        $this->assertSame(['Α.pdf' => 'ΑΔΑ-Α', 'Γ.pdf' => 'ΑΔΑ-Γ'],
            Protocol::firstOrFail()->attachments()->orderBy('name')->pluck('ada', 'name')->all());
    }

    public function test_empty_subject_is_suggested_from_the_file_name_and_used_on_save(): void
    {
        $component = Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('form.fakelos', 'Φ.1')
            ->set('picked', [$this->pdf('Πρόσκληση_σε  ημερίδα.pdf')])
            ->assertSee('placeholder="Πρόσκληση σε ημερίδα"', false)->assertSee('id="subject-from-file"', false);

        $component->set('form.thema', 'Δικό μου θέμα')->assertDontSee('id="subject-from-file"', false)
            ->set('form.thema', '')->call('save')->assertHasNoErrors();

        $this->assertSame('Πρόσκληση σε ημερίδα', Protocol::firstOrFail()->thema);
    }

    public function test_a_typed_subject_is_never_replaced(): void
    {
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('form.thema', 'Δικό μου θέμα')->set('form.fakelos', 'Φ.1')
            ->set('picked', [$this->pdf('Άλλο.pdf')])->call('save')->assertHasNoErrors();

        $this->assertSame('Δικό μου θέμα', Protocol::firstOrFail()->thema);
    }

    public function test_subject_from_file_name(): void
    {
        $this->assertSame('Πρόσκληση σε ημερίδα', SubjectFromFile::guess('Πρόσκληση_σε  ημερίδα.pdf'));
        $this->assertSame('Αίτηση v2.1 τελική', SubjectFromFile::guess('Αίτηση v2.1 τελική.docx'));
        $this->assertSame('έκθεση.final', SubjectFromFile::guess('έκθεση.final'), 'not an extension: kept');
        $this->assertNull(SubjectFromFile::guess('00012.pdf'), 'a scanner number says nothing');
        $this->assertNull(SubjectFromFile::guess('.pdf'));
        $this->assertNull(SubjectFromFile::guess(null));
    }

    public function test_reader_sees_no_upload_controls(): void
    {
        $p = Protocol::factory()->number(1)->create();
        Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])
            ->assertDontSee('id="f-uploads"', false)->assertSee('Δεν υπάρχουν συνημμένα.');
    }
}
