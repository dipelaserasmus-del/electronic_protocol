<?php

namespace Tests\Feature;

use App\Models\Keepvalue;
use Illuminate\Support\Facades\Schema;

/** D06: a folder's description and remarks hold long text; the folders page keeps the text as typed. */
class FolderDescriptionTest extends FeatureTestCase
{
    private function long(int $chars): string
    {
        return mb_substr(str_repeat('Περιγραφή φακέλου με πλήρες κείμενο. ', (int) ceil($chars / 37) + 1), 0, $chars);
    }

    public function test_description_and_remarks_are_text_columns(): void
    {
        $this->assertSame('text', Schema::getColumnType('keepvalues', 'describe'));
        $this->assertSame('text', Schema::getColumnType('keepvalues', 'remarks'));
        $this->assertFalse(collect(Schema::getIndexes('keepvalues'))->contains(fn ($i) => $i['columns'] === ['describe']));
    }

    public function test_migration_is_safe_to_run_twice(): void
    {
        $migration = require base_path('database/migrations/2026_11_18_000001_folder_description_text.php');
        $migration->up();
        $migration->up();
        $this->assertSame('text', Schema::getColumnType('keepvalues', 'describe'));
    }

    public function test_a_long_description_with_line_breaks_is_saved_as_typed(): void
    {
        $describe = $this->long(3000) . "\nδεύτερη γραμμή";
        $this->actingAs($this->admin())
            ->post('/keep', ['fakelos' => 'Φ.900', 'keep' => '5', 'keep_alt' => '', 'describe' => $describe, 'remarks' => $this->long(600)])
            ->assertSessionHasNoErrors();
        $folder = Keepvalue::where('fakelos', 'Φ.900')->firstOrFail();
        $this->assertSame($describe, $folder->describe);
        $this->assertSame(600, mb_strlen($folder->remarks));
    }

    public function test_editing_does_not_add_line_break_tags(): void
    {
        $folder = Keepvalue::create(['fakelos' => 'Φ.901', 'describe' => "πρώτη <γραμμή>\nδεύτερη"]);
        $admin = $this->admin();
        $this->setting('showRowsInPage', 10000);   // the new folder on the list's first page
        $page = $this->actingAs($admin)->get('/keep/' . $folder->id)->assertOk();
        // the edit box shows the text itself (it used to show "<br />", which was then saved)
        $page->assertSee(">πρώτη &lt;γραμμή&gt;\nδεύτερη</textarea>", false);
        // the list shows the line break and escapes the text
        $page->assertSee('πρώτη &lt;γραμμή&gt;<br>', false);
        $page->assertDontSee('πρώτη <γραμμή>', false);
    }

    public function test_description_longer_than_the_limit_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/keep', ['fakelos' => 'Φ.902', 'keep' => '', 'keep_alt' => '', 'describe' => $this->long(Keepvalue::TEXT_MAX + 1), 'remarks' => ''])
            ->assertSessionHasErrors('describe');
        $this->assertFalse(Keepvalue::where('fakelos', 'Φ.902')->exists());
    }
}
