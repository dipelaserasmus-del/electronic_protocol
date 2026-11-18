<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Protocols\ProtocolWriter;
use App\Support\FolderRules;
use Livewire\Livewire;

/** R1d: a folder's automatic assignments (R6b) are applied when a protocol is saved in it. */
class FolderRulesTest extends FeatureTestCase
{
    private function folderWithRules(string $code, array $rules): Keepvalue
    {
        $f = Keepvalue::create(['fakelos' => $code]);
        foreach ($rules as [$user, $type]) {
            FolderAssignment::create(['keepvalue_id' => $f->id, 'user_id' => $user->id, 'type' => $type]);
        }
        return $f;
    }

    private function writer_(): ProtocolWriter
    {
        return app(ProtocolWriter::class);
    }

    private function assigned(Protocol $p): array
    {
        return $p->fresh()->assignments->map(fn ($a) => $a->type.$a->user_id)->sort()->values()->all();
    }

    public function test_a_writer_registering_in_the_folder_assigns_its_users_and_they_get_the_email(): void
    {
        $this->setting('sendEmailOnDiekperaiosiChange', 1);
        [$handler, $informed] = [$this->assigner(), $this->reader()];
        $this->folderWithRules('Φ.800', [[$handler, 'd'], [$informed, 'e']]);

        $result = $this->writer_()->create($this->writer(), $this->protocolForm(['fakelos' => 'Φ.800']));

        $this->assertEqualsCanonicalizing(["d{$handler->id}", "e{$informed->id}"], $this->assigned($result->protocol));
        $this->assertSame("d{$handler->id},e{$informed->id}", $result->protocol->fresh()->diekperaiosi);
        $this->assertContains("Αυτόματη ανάθεση (Φ.800): Διεκπεραίωση: {$handler->name} · Ενημέρωση: {$informed->name}", $result->messages);
        $this->assertEqualsCanonicalizing([$handler->email, $informed->email],
            $this->sentMails('intra-mail')->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress())->all());
    }

    public function test_no_folder_or_a_folder_without_rules_changes_nothing(): void
    {
        Keepvalue::create(['fakelos' => 'Φ.801']);
        foreach (['', 'Φ.801'] as $folder) {
            $result = $this->writer_()->create($this->writer(), $this->protocolForm(['fakelos' => $folder]));
            $this->assertSame([], $this->assigned($result->protocol));
            $this->assertSame([], $result->messages);
        }
    }

    public function test_assignments_given_by_hand_stay_and_nobody_is_added_twice(): void
    {
        [$a, $b, $c] = [$this->writer(), $this->writer(), $this->writer()];
        $this->folderWithRules('Φ.802', [[$a, 'd'], [$b, 'e'], [$c, 'e']]);
        $result = $this->writer_()->create($this->assigner(), $this->protocolForm([
            'fakelos' => 'Φ.802', 'diekperaiosi' => ["e{$a->id}", "d{$b->id}"],   // a: only informed by hand; b: handles
        ]));
        // a: διεκπεραίωση replaces ενημέρωση; b: already handles (includes being informed); c: added
        $this->assertEqualsCanonicalizing(["d{$a->id}", "d{$b->id}", "e{$c->id}"], $this->assigned($result->protocol));
    }

    public function test_inactive_users_in_the_rules_are_skipped(): void
    {
        $gone = User::factory()->writer()->inactive()->create();
        $here = $this->writer();
        $this->folderWithRules('Φ.803', [[$gone, 'd'], [$here, 'e']]);
        $result = $this->writer_()->create($this->writer(), $this->protocolForm(['fakelos' => 'Φ.803']));
        $this->assertSame(["e{$here->id}"], $this->assigned($result->protocol));
    }

    public function test_moving_a_protocol_into_the_folder_adds_its_users_saving_it_again_does_not(): void
    {
        $admin = $this->admin();
        $handler = $this->writer();
        $this->folderWithRules('Φ.804', [[$handler, 'd']]);
        $p = $this->writer_()->create($admin, $this->protocolForm(['fakelos' => 'Φ.1']))->protocol;
        $this->assertSame([], $this->assigned($p));

        $form = fn (array $more) => $this->protocolForm(['protocolnum' => $p->protocolnum, 'etos' => $p->etos, 'fakelos' => 'Φ.804'] + $more);
        $result = $this->writer_()->update($admin, $p->fresh(), $form([]));
        $this->assertSame(["d{$handler->id}"], $this->assigned($p));
        $this->assertStringContainsString('Αυτόματη ανάθεση (Φ.804)', implode(' ', $result->messages));

        // the administrator removes the assignment by hand: saving in the same folder does not bring it back
        $this->writer_()->update($admin, $p->fresh(), $form(['diekperaiosi' => []]));
        $this->assertSame([], $this->assigned($p));
    }

    public function test_the_form_says_who_will_be_assigned(): void
    {
        $handler = $this->writer();
        $this->folderWithRules('Φ.805', [[$handler, 'd']]);
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->assertDontSee('notice-auto-assign')
            ->set('form.fakelos', 'Φ.805')
            ->assertSee('Με την αποθήκευση: Αυτόματη ανάθεση (Φ.805): Διεκπεραίωση: '.$handler->name);

        $p = Protocol::factory()->create(['fakelos' => 'Φ.805']);
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])->assertDontSee('notice-auto-assign');
    }

    public function test_merge_rules(): void
    {
        [$all, $added] = FolderRules::merge([['user_id' => 1, 'type' => 'e']], [['user_id' => 1, 'type' => 'd'], ['user_id' => 2, 'type' => 'e'], ['user_id' => 2, 'type' => 'e']]);
        $this->assertSame([['user_id' => 1, 'type' => 'd'], ['user_id' => 2, 'type' => 'e']], $all);
        $this->assertSame($all, $added);
    }
}
