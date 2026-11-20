<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Services\Folders\FolderCatalog;
use App\Services\Folders\FolderGlossary;
use App\Services\Folders\FolderMerge;
use App\Support\FolderCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** D07: the owner's folder glossary (database/data/folders.json), `folders:glossary`, and the folder merge Φ.43 → Φ.46. */
class FolderGlossaryTest extends FeatureTestCase
{
    // ---- the glossary file itself ----

    public function test_the_glossary_is_well_formed(): void
    {
        $g = FolderGlossary::load();
        $this->assertCount(88, $g['folders']);
        $keys = [];
        foreach ($g['folders'] as $f) {
            $this->assertMatchesRegularExpression(FolderCatalog::CODE_PATTERN, $f['code']);
            $this->assertNotSame('', trim($f['describe']), $f['code']);
            $this->assertLessThanOrEqual(Keepvalue::TEXT_MAX, mb_strlen($f['describe']));
            $this->assertSame(trim($f['describe']), $f['describe']);
            $this->assertArrayNotHasKey(FolderCode::key($f['code']), $keys, "duplicate {$f['code']}");
            $keys[FolderCode::key($f['code'])] = true;
        }
        $codes = array_column($g['folders'], 'describe', 'code');
        // owner's decisions (2026-11-19)
        $this->assertStringEndsWith('(Καταργήθηκε)', $codes['Φ.13']);
        $this->assertStringStartsWith('ΣΧΟΛΙΚΗ ΕΦΟΡΙΑ - ΣΧΟΛΙΚΟ ΤΑΜΕΙΟ: Σχετική νομοθεσία', $codes['Φ.6']);   // title + text together
        $this->assertArrayNotHasKey('Φ.43', $codes);
        foreach (['Φ.37', 'Φ.38', 'Φ.39', 'Φ.40', 'Φ.41', 'Φ.42'] as $school) {
            $this->assertArrayNotHasKey($school, $codes, 'school units stay as they are in the database');
        }
        $this->assertSame([['from' => 'Φ.43', 'into' => 'Φ.46']], array_map(fn ($m) => ['from' => $m['from'], 'into' => $m['into']], $g['merges']));
        $this->assertArrayHasKey('Φ.46', $codes);
    }

    public function test_docs_folders_md_is_generated_from_the_glossary(): void
    {
        $this->assertSame(FolderGlossary::markdown(FolderGlossary::load()), file_get_contents(base_path('docs/FOLDERS.md')),
            'docs/FOLDERS.md is out of date: run php artisan folders:glossary --docs');
    }

    // ---- the command ----

    public function test_preview_changes_nothing(): void
    {
        Keepvalue::where('fakelos', 'Φ.12.1')->update(['describe' => 'Άδειες']);
        $before = Keepvalue::orderBy('id')->get()->toArray();

        $this->artisan('folders:glossary')->expectsOutputToContain('Nothing was changed')->assertSuccessful();

        $this->assertSame($before, Keepvalue::orderBy('id')->get()->toArray());
    }

    public function test_apply_writes_the_descriptions_and_keeps_retention_and_remarks(): void
    {
        Keepvalue::where('fakelos', 'Φ.12.1')->delete();
        Keepvalue::create(['fakelos' => 'Φ.12.1', 'keep' => 7, 'keep_alt' => null, 'describe' => 'Άδειες', 'remarks' => '§ 5']);
        Keepvalue::where('fakelos', 'Φ.38')->update(['describe' => 'Σχολική μονάδα (δεν αλλάζει)']);
        Keepvalue::where('fakelos', 'Φ.48')->delete();

        $this->artisan('folders:glossary', ['--apply' => true])->assertSuccessful();

        $leave = Keepvalue::where('fakelos', 'Φ.12.1')->first();
        $this->assertStringStartsWith('Άδειες κανονικές, εκπαιδευτικές, αναρρωτικές', $leave->describe);
        $this->assertSame([7, null, '§ 5'], [$leave->keep, $leave->keep_alt, $leave->remarks]);
        $this->assertSame('Σχολική μονάδα (δεν αλλάζει)', Keepvalue::where('fakelos', 'Φ.38')->value('describe'));
        $this->assertSame('Γνησιότητες (Αιτήσεις και Βεβαιώσεις)', Keepvalue::where('fakelos', 'Φ.48')->value('describe'));
        $this->assertGreaterThan(255, mb_strlen(Keepvalue::where('fakelos', 'Φ.2.1')->value('describe')));

        // a second run has nothing left to do
        $this->artisan('folders:glossary')->expectsOutputToContain('Changed:   —')->expectsOutputToContain('already done')->assertSuccessful();
    }

    public function test_a_code_written_differently_in_the_list_is_updated_not_duplicated(): void
    {
        Keepvalue::where('fakelos', 'Φ.2.1')->delete();
        Keepvalue::create(['fakelos' => 'Φ. 2.1', 'describe' => 'Ίδρυση']);   // as an old list may have it

        $this->artisan('folders:glossary', ['--apply' => true])->expectsOutputToContain('Φ.2.1 is written Φ. 2.1')->assertSuccessful();

        $this->assertSame(['Φ. 2.1'], Keepvalue::pluck('fakelos')->filter(fn ($c) => FolderCode::key($c) === 'φ2.1')->values()->all());
        $this->assertStringStartsWith('Ίδρυση, έδρα, όρια', Keepvalue::where('fakelos', 'Φ. 2.1')->value('describe'));
    }

    // ---- the merge ----

    private function protocolIn(string $folder, int $number): Protocol
    {
        return Protocol::factory()->number($number)->create(['fakelos' => $folder]);
    }

    private function attach(Protocol $p, string $path, bool $file = true): Attachment
    {
        if ($file) {
            Storage::put($path, "%PDF $path");
        }

        return Attachment::create(['protocol_id' => $p->id, 'name' => basename($path), 'savedPath' => $path, 'mimeType' => 'application/pdf']);
    }

    public function test_merge_moves_protocols_files_and_rules_then_removes_the_old_folder(): void
    {
        Keepvalue::firstOrCreate(['fakelos' => 'Φ.43'], ['describe' => 'Εκπαιδευτικοί Διεύθυνσης']);
        $into = Keepvalue::firstOrCreate(['fakelos' => 'Φ.46'], ['describe' => 'Προσωπικό Μητρώο']);
        $old = Keepvalue::where('fakelos', 'Φ.43')->first();
        [$d, $e, $both] = [$this->writer(), $this->writer(), $this->writer()];
        FolderAssignment::create(['keepvalue_id' => $old->id, 'user_id' => $d->id, 'type' => 'd']);
        FolderAssignment::create(['keepvalue_id' => $old->id, 'user_id' => $both->id, 'type' => 'd']);
        FolderAssignment::create(['keepvalue_id' => $into->id, 'user_id' => $both->id, 'type' => 'e']);
        FolderAssignment::create(['keepvalue_id' => $into->id, 'user_id' => $e->id, 'type' => 'e']);

        $p1 = $this->protocolIn('Φ.43', 901);
        $p2 = $this->protocolIn('φ.43', 902);   // written in small letters: the same folder
        $other = $this->protocolIn('Φ.43.1', 903);
        $stamp = $p1->updated_at;
        $a = $this->attach($p1, 'arxeio/Φ.43/α.pdf');
        $taken = $this->attach($p1, 'arxeio/Φ.43/κοινό.pdf');
        Storage::put('arxeio/Φ.46/κοινό.pdf', 'already there');
        $lower = $this->attach($p2, 'arxeio/φ.43/β.pdf');
        $gone = $this->attach($p2, 'arxeio/Φ.43/χαμένο.pdf', file: false);
        Storage::put('arxeio/Φ.46/χαμένο.pdf', 'another document');   // the missing file's new name is taken
        $trashed = $this->attach($p2, 'trash/Φ.43/γ.pdf');             // trashed after R4b
        DB::table('attachments')->where('id', $trashed->id)->update(['trashed_from' => 'arxeio/Φ.43/γ.pdf', 'deleted_at' => now()]);
        $legacy = $this->attach($p2, 'arxeio/Φ.43/παλιό.pdf', file: false);   // trashed before R4b: row says arxeio/
        Storage::put('trash/Φ.43/παλιό.pdf', '%PDF legacy');
        DB::table('attachments')->where('id', $legacy->id)->update(['deleted_at' => now()]);
        $sub = $this->attach($p1, 'arxeio/Φ.43/παλιά/δ.pdf');   // a sub-directory: left alone, as a folder change does
        $otherFile = $this->attach($other, 'arxeio/Φ.43.1/ε.pdf');

        $merge = app(FolderMerge::class);
        $plan = $merge->plan('Φ.43', 'Φ.46');
        $this->assertSame([2, 6, 2], [$plan['protocols'], $plan['files'], $plan['rules']]);
        $this->assertContains('Φ.43.1', $plan['subfolders'], 'named, not merged');

        $r = $merge->apply('Φ.43', 'Φ.46');

        $this->assertSame(['protocols' => 2, 'moved' => 4, 'renamed' => 1, 'missing' => 1, 'failed' => 0, 'rules' => 2], $r);
        $this->assertSame(['Φ.46', 'Φ.46', 'Φ.43.1'], [$p1->fresh()->fakelos, $p2->fresh()->fakelos, $other->fresh()->fakelos]);
        $this->assertEquals($stamp, $p1->fresh()->updated_at, 'not a change by a user');
        $this->assertSame('arxeio/Φ.46/α.pdf', $a->fresh()->savedPath);
        Storage::assertExists('arxeio/Φ.46/α.pdf');
        Storage::assertMissing('arxeio/Φ.43/α.pdf');
        $this->assertSame('arxeio/Φ.46/κοινό (2).pdf', $taken->fresh()->savedPath, 'nothing is overwritten');
        $this->assertSame('already there', Storage::get('arxeio/Φ.46/κοινό.pdf'));
        $this->assertSame('arxeio/Φ.46/β.pdf', $lower->fresh()->savedPath);
        // review of D07: a file not found keeps its path, never the new one (that is another attachment's document)
        $this->assertSame('arxeio/Φ.43/χαμένο.pdf', $gone->fresh()->savedPath);
        $t = Attachment::withTrashed()->find($trashed->id);
        $this->assertSame(['trash/Φ.46/γ.pdf', 'arxeio/Φ.46/γ.pdf'], [$t->savedPath, $t->trashed_from]);
        Storage::assertExists('trash/Φ.46/γ.pdf');
        // review of D07: trashed before R4b — found where the trash keeps it, moved, and restored under Φ.46
        $l = Attachment::withTrashed()->find($legacy->id);
        $this->assertSame(['trash/Φ.46/παλιό.pdf', 'arxeio/Φ.46/παλιό.pdf'], [$l->savedPath, $l->trashed_from]);
        $this->assertTrue(app(\App\Services\Attachments\Trash::class)->restore($l));
        $this->assertSame('arxeio/Φ.46/παλιό.pdf', $l->fresh()->savedPath);
        $this->assertSame('%PDF legacy', Storage::get('arxeio/Φ.46/παλιό.pdf'));
        $this->assertSame('arxeio/Φ.43/παλιά/δ.pdf', $sub->fresh()->savedPath);
        $this->assertSame('arxeio/Φ.43.1/ε.pdf', $otherFile->fresh()->savedPath);
        Storage::assertMissing('arxeio/φ.43');   // emptied, removed
        Storage::assertMissing('trash/Φ.43');

        $this->assertNull(Keepvalue::where('fakelos', 'Φ.43')->first());
        $this->assertNotNull(Keepvalue::where('fakelos', 'Φ.43.1')->first());
        $rules = FolderAssignment::where('keepvalue_id', $into->id)->pluck('type', 'user_id')->all();
        $this->assertSame(['d', 'd', 'e'], [$rules[$d->id], $rules[$both->id], $rules[$e->id]], 'διεκπεραίωση wins over ενημέρωση');

        // again: nothing left
        $this->assertTrue($merge->plan('Φ.43', 'Φ.46')['done']);
        $this->assertSame(0, $merge->apply('Φ.43', 'Φ.46')['protocols']);
    }

    public function test_merge_refuses_a_target_that_is_not_in_the_list(): void
    {
        Keepvalue::firstOrCreate(['fakelos' => 'Φ.43']);
        Keepvalue::where('fakelos', 'Φ.999')->delete();
        $this->protocolIn('Φ.43', 904);

        $this->assertSame('Ο φάκελος Φ.999 δεν υπάρχει στη λίστα.', app(FolderMerge::class)->plan('Φ.43', 'Φ.999')['error']);
        $this->expectException(\RuntimeException::class);
        app(FolderMerge::class)->apply('Φ.43', 'Φ.999');
    }

    public function test_apply_runs_the_glossary_merge(): void
    {
        Keepvalue::firstOrCreate(['fakelos' => 'Φ.43']);
        Keepvalue::where('fakelos', 'Φ.46')->delete();   // the import adds it first
        $p = $this->protocolIn('Φ.43', 905);

        $this->artisan('folders:glossary')->expectsOutputToContain('Φ.46 is added first')->assertSuccessful();
        $this->artisan('folders:glossary', ['--apply' => true])->expectsOutputToContain('Merge Φ.43 → Φ.46: 1 protocols')->assertSuccessful();

        $this->assertSame('Φ.46', $p->fresh()->fakelos);
        $this->assertSame('Προσωπικό Μητρώο (ΠΜ)', Keepvalue::where('fakelos', 'Φ.46')->value('describe'));
        $this->assertSame(0, DB::table('keepvalues')->where('fakelos', 'Φ.43')->count());
    }

    public function test_merge_takes_every_spelling_of_the_code(): void
    {
        // D08 (owner, 2026-11-20): the old app wrote both "Φ.2" and "Φ12": "Φ43" is the same folder as "Φ.43"
        Keepvalue::where('fakelos', 'Φ.43')->delete();
        Keepvalue::where('fakelos', 'Φ.46')->delete();
        Keepvalue::create(['fakelos' => 'Φ43', 'describe' => 'Παλιά γραφή']);
        $into = Keepvalue::create(['fakelos' => 'Φ46', 'describe' => 'Προσωπικό Μητρώο']);
        $a = $this->protocolIn('Φ43', 906);
        $b = $this->protocolIn('Φ.43', 907);
        $c = $this->protocolIn('Φ.43.1', 908);
        $file = $this->attach($a, 'arxeio/Φ43/α.pdf');

        $plan = app(FolderMerge::class)->plan('Φ.43', 'Φ.46');
        $this->assertSame([2, 1, true, true], [$plan['protocols'], $plan['files'], $plan['fromListed'], $plan['intoListed']]);
        $this->assertContains('Φ.43.1', $plan['subfolders']);

        app(FolderMerge::class)->apply('Φ.43', 'Φ.46');

        $this->assertSame(['Φ46', 'Φ46', 'Φ.43.1'], [$a->fresh()->fakelos, $b->fresh()->fakelos, $c->fresh()->fakelos], 'written as the list writes it');
        $this->assertSame('arxeio/Φ46/α.pdf', $file->fresh()->savedPath);
        $this->assertNull(Keepvalue::where('fakelos', 'Φ43')->first());
        $this->assertNotNull($into->fresh());
        $this->assertSame('Ο φάκελος δεν συγχωνεύεται με τον εαυτό του.', app(FolderMerge::class)->plan('Φ46', 'φ.46')['error']);
    }
}
