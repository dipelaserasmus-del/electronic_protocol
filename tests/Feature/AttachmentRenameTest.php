<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Livewire\SearchResults;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** R4d: renaming an attachment changes only the name shown; the file keeps its path and the extension stays. */
class AttachmentRenameTest extends FeatureTestCase
{
    private function attachment(array $attributes = [], ?Protocol $protocol = null): Attachment
    {
        $protocol ??= Protocol::factory()->number(1)->create();
        return Attachment::factory()->withFile()->create(['protocol_id' => $protocol->id] + $attributes);
    }

    private function rename(Attachment $a, string $typed, $user = null): string
    {
        return app(ProtocolWriter::class)->renameAttachment($user ?? $this->admin(), $a, $typed);
    }

    public function test_only_the_name_changes_and_the_extension_stays(): void
    {
        $a = $this->attachment(['name' => 'scan0001.pdf']);
        [$path, $hash] = [$a->savedPath, $a->fresh()->sha256];

        $this->assertSame('Απόφαση Διευθυντή.pdf', $this->rename($a, '  Απόφαση Διευθυντή  '));
        $a->refresh();
        $this->assertSame(['Απόφαση Διευθυντή.pdf', $path, $hash], [$a->name, $a->savedPath, $a->sha256]);
        Storage::assertExists($path);

        // typed with its extension (any case): not doubled; another extension typed is part of the name
        $this->assertSame('Απόφαση.pdf', $this->rename($a, 'Απόφαση.PDF'));
        $this->assertSame('Απόφαση.docx.pdf', $this->rename($a, 'Απόφαση.docx'));
        // a dot inside the name stays; characters not allowed in file names are replaced as on upload
        $this->assertSame('Πράξη 12.3 - 2026.pdf', $this->rename($a, 'Πράξη 12.3 / 2026'));
        $this->assertSame('a-b-.pdf', $this->rename($a, 'a<b>'));
    }

    public function test_the_extension_comes_from_the_stored_file_when_the_name_has_none(): void
    {
        $a = $this->attachment(['name' => 'χωρίς κατάληξη']);
        $a->forceFill(['savedPath' => 'arxeio/test/1-20260101_x.odt'])->saveQuietly();
        $this->assertSame('Πρακτικό.odt', $this->rename($a, 'Πρακτικό'));

        $none = $this->attachment(['name' => 'README'], $a->protocol);
        $none->forceFill(['savedPath' => 'arxeio/test/README'])->saveQuietly();
        $this->assertSame('ΔΙΑΒΑΣΤΕ', $this->rename($none, 'ΔΙΑΒΑΣΤΕ'));
    }

    public function test_an_empty_name_and_attachments_without_a_file_are_refused(): void
    {
        $a = $this->attachment(['name' => 'έγγραφο.pdf']);
        foreach (['', '   ', '.pdf', ' . '] as $typed) {
            try {
                $this->rename($a, $typed);
                $this->fail("«{$typed}» was accepted");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Γράψτε το νέο όνομα.', $e->getMessage());
            }
        }
        $this->assertSame('έγγραφο.pdf', $a->fresh()->name);

        $adaOnly = Attachment::factory()->create(['protocol_id' => $a->protocol_id, 'name' => null, 'ada' => 'ΩΡΖ']);
        $this->expectException(\InvalidArgumentException::class);
        $this->rename($adaOnly, 'όνομα');
    }

    public function test_only_users_who_may_save_the_protocol_rename(): void
    {
        $writer = $this->writer();
        $own = $this->attachment(['name' => 'δικό.pdf'], Protocol::factory()->number(2)->create(['user_id' => $writer->id]));
        $this->assertSame('δικό μου.pdf', $this->rename($own, 'δικό μου', $writer));

        foreach ([$this->reader(), $this->writer()] as $other) {   // a reader; another writer (not theirs)
            try {
                $this->rename($own, 'ξένο', $other);
                $this->fail('renamed by ' . $other->role->role);
            } catch (AuthorizationException) {
                $this->assertSame('δικό μου.pdf', $own->fresh()->name);
            }
        }
    }

    public function test_the_form_renames_and_the_search_finds_the_new_name_at_once(): void
    {
        $p = Protocol::factory()->number(3)->create(['thema' => 'Με συνημμένο']);
        $a = $this->attachment(['name' => 'scan0002.pdf'], $p);
        $admin = $this->admin();

        $form = Livewire::actingAs($admin)->test(ProtocolForm::class, ['protocol' => $p])
            ->assertSeeHtml('data-action="rename"')->assertSeeHtml('<span class="input-group-text">.pdf</span>');
        $form->call('renameAttachment', $a->id, 'Πρόσκληση ημερίδας.pdf')
            ->assertDispatched('toast', message: 'Μετονομάστηκε σε «Πρόσκληση ημερίδας.pdf».', type: 'success')
            ->assertSee('Πρόσκληση ημερίδας.pdf');
        // review of R4d: the field sends what was typed: without the extension, or with it (not doubled)
        $form->call('renameAttachment', $a->id, 'Απόφαση')->assertSee('Απόφαση.pdf');
        $this->assertSame('Απόφαση.pdf', $a->fresh()->name);
        $form->call('renameAttachment', $a->id, 'Απόφαση.pdf');
        $this->assertSame('Απόφαση.pdf', $a->fresh()->name);
        $this->assertStringNotContainsString("base + '.pdf'", $form->html(), 'the field adds nothing');
        $form->call('renameAttachment', $a->id, 'Πρόσκληση ημερίδας');
        $form->call('renameAttachment', $a->id, '')->assertDispatched('toast', message: 'Γράψτε το νέο όνομα.', type: 'error');

        Livewire::actingAs($admin)->withQueryParams(['q' => 'συνημμένο:ημερίδας'])->test(SearchResults::class)->assertSee('Με συνημμένο');
        Livewire::actingAs($admin)->withQueryParams(['q' => 'συνημμένο:scan0002'])->test(SearchResults::class)->assertDontSee('Με συνημμένο');

        // readers see no ✎ and cannot call it
        Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])->assertDontSeeHtml('data-action="rename"')
            ->call('renameAttachment', $a->id, 'κάτι άλλο')->assertDispatched('toast', type: 'error');
        $this->assertSame('Πρόσκληση ημερίδας.pdf', $a->fresh()->name);

        // an attachment of another protocol is not found through this form
        $elsewhere = $this->attachment(['name' => 'άλλο.pdf'], Protocol::factory()->number(4)->create());
        Livewire::actingAs($admin)->test(ProtocolForm::class, ['protocol' => $p])->call('renameAttachment', $elsewhere->id, 'χ')->assertStatus(404);
    }
}
