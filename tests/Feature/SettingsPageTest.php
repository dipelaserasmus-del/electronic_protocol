<?php

namespace Tests\Feature;

use App\Livewire\ArchivePage;
use App\Livewire\SettingsPage;
use App\Models\Attachment;
use App\Models\Config;
use App\Models\Protocol;
use App\Support\InboxAccess;
use App\Support\SettingsCatalog;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** R6c: the settings page (App\Livewire\SettingsPage + App\Support\SettingsCatalog) and "Εκκαθάριση" (ArchivePage). */
class SettingsPageTest extends FeatureTestCase
{
    public function test_only_administrators_open_the_pages(): void
    {
        $this->actingAs($this->admin())->get('/settings')->assertOk()->assertSee('Λίστες και αναζήτηση')->assertSee('Ειδοποιήσεις')->assertDontSee('Συντήρηση');
        $this->actingAs($this->admin())->get('/arxeio')->assertOk()->assertSee('Εκκαθάριση αρχείου');
        $this->actingAs($this->admin())->get('/backups')->assertOk()->assertSee('Αντίγραφα ασφαλείας');
        foreach ([$this->assigner(), $this->writer(), $this->reader()] as $u) {
            foreach (['/settings', '/arxeio', '/backups', '/expired'] as $url) {
                $this->actingAs($u)->get($url)->assertRedirect('/home');
            }
            Livewire::actingAs($u)->test(SettingsPage::class)->assertForbidden();
            Livewire::actingAs($u)->test(ArchivePage::class)->assertForbidden();
        }
    }

    public function test_old_routes_that_changed_things_are_gone(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings', ['ipiresiasName' => 'x'])->assertStatus(405);
        foreach (['/delExpired', '/delDeleted', '/backup', '/deleteBackup/x_back_1.sql.gz'] as $url) {
            $this->actingAs($admin)->get($url)->assertNotFound();
        }
        $this->actingAs($admin)->get('/expired/1')->assertRedirect('/trash');
    }

    public function test_every_setting_shown_is_saved_and_only_known_keys(): void
    {
        // R6f: every setting is in exactly one section, and each section shows its own settings only
        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        $placed = array_merge(...array_map(fn ($sec) => $sec[4], array_values(SettingsCatalog::SECTIONS)));
        $this->assertEqualsCanonicalizing(array_keys(SettingsCatalog::all()), $placed);
        $this->assertSame(count($placed), count(array_unique($placed)));
        foreach (SettingsCatalog::SECTIONS as $id => [, $title, , , $keys]) {
            $page->call('show', $id)->assertSet('section', $id)->assertSee($title);
            foreach (array_keys(SettingsCatalog::all()) as $key) {
                in_array($key, $keys, true) ? $page->assertSee('id="s-'.$key.'"', false) : $page->assertDontSee('id="s-'.$key.'"', false);
            }
        }

        $page->call('show', 'office')->set('values.ipiresiasName', '  Γυμνάσιο Δοκιμών  ')->set('values.titleColor', '#1f4e79')
            ->set('values.madeUpKey', 'x')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', type: 'success');
        $page->call('show', 'rights')->set('values.limitProtocolAccessList', '1')->set('values.allowWriterUpdateProtocol', '1')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('Γυμνάσιο Δοκιμών', Config::getConfigValueOf('ipiresiasName'));
        $this->assertSame('1', Config::getConfigValueOf('limitProtocolAccessList'));
        $this->assertSame('1', Config::getConfigValueOf('allowWriterUpdateProtocol'));
        $this->assertSame('#1f4e79', Config::getConfigValueOf('titleColor'));
        $this->assertFalse(Config::where('key', 'madeUpKey')->exists());

        $page->set('values.limitProtocolAccessList', '0')->call('save');
        $this->assertSame('0', Config::getConfigValueOf('limitProtocolAccessList'));
    }

    /** R6f (owner, 2026-11-25): one section at a time, saved on its own; ?section= and ?setting= open one */
    public function test_each_section_is_shown_and_saved_on_its_own(): void
    {
        $admin = $this->admin();
        $this->setting('showRowsInPage', '20');
        // the menu: every section under its heading, in order; the first one open
        $html = $this->actingAs($admin)->get('/settings')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~settings-nav-item\s+active\s*"\s+aria-current="page"\s*>\s*<i class="bi bi-building"~', $html);
        $this->assertMatchesRegularExpression('~Υπηρεσία/Οργανισμός.*?settings-nav-heading">Πρωτόκολλο</div>.*?Αρίθμηση και έλεγχοι.*?Λίστες και αναζήτηση.*?Καταχώριση.*?Δικαιώματα.*?settings-nav-heading">Αρχεία</div>.*?Συνημμένα.*?Κάδος.*?settings-nav-heading">E-mail</div>.*?Λογαριασμός εισερχομένων.*?Εισερχόμενα.*?Ειδοποιήσεις~s', $html);
        $this->assertSame(count(SettingsCatalog::SECTIONS), substr_count($html, 'class="settings-nav-item'));
        // by address
        $this->actingAs($admin)->get('/settings?section=trash')->assertSee('id="s-trashRetentionDays"', false)->assertDontSee('id="s-ipiresiasName"', false);
        $this->actingAs($admin)->get('/settings?setting=saveEmailAs')->assertSee('id="s-saveEmailAs"', false)->assertSee('id="s-putStamp"', false);
        $this->actingAs($admin)->get('/settings?section=nonsense')->assertOk()->assertSee('id="s-ipiresiasName"', false);

        // saving a section saves only it: a wrong value typed elsewhere (not shown) is neither checked nor stored
        $page = Livewire::actingAs($admin)->test(SettingsPage::class)->call('show', 'display')
            ->set('values.showRowsInPage', '35')->set('values.yearInUse', '26')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('35', (string) Config::getConfigValueOf('showRowsInPage'));
        $this->assertNotSame('26', (string) Config::getConfigValueOf('yearInUse'));
        // and a wrong value in it is refused there; leaving the section drops what was typed and its errors
        $page->set('values.showRowsInPage', 'abc')->call('save')->assertHasErrors('values.showRowsInPage')->assertSee('settings-errors');
        $page->call('show', 'office')->assertHasNoErrors()->assertSet('values.showRowsInPage', '35');
        $page->call('show', 'nonsense')->assertSet('section', 'office');

        // review of R6f: ?setting= picks the section once and leaves the address (so a reload opens the section shown);
        // a section already in the address wins
        $deep = Livewire::actingAs($admin)->withQueryParams(['setting' => 'saveEmailAs'])->test(SettingsPage::class)
            ->assertSet('section', 'files')->assertSet('setting', '');
        $deep->call('show', 'office')->assertSet('section', 'office')->assertSet('setting', '');
        Livewire::actingAs($admin)->withQueryParams(['section' => 'display', 'setting' => 'saveEmailAs'])->test(SettingsPage::class)
            ->assertSet('section', 'display');
    }

    public function test_wrong_values_are_refused_at_their_field_and_nothing_is_saved(): void
    {
        $before = Config::getConfigValueOf('ipiresiasName');
        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        // a wrong value stops its whole section (the name is not saved with a wrong colour)
        $page->call('show', 'office')->set('values.ipiresiasName', 'Νέο όνομα')->set('values.titleColor', 'red; background:url(x)')
            ->call('save')->assertHasErrors(['values.titleColor'])->assertSee('settings-errors');
        $this->assertSame($before, Config::getConfigValueOf('ipiresiasName'));
        // R6f: each of the others in its own section
        foreach ([['numbering', 'yearInUse', '26'], ['rights', 'allowWriterUpdateProtocol', '5'], ['notifications', 'alwaysSendReceitForEmails', 'maybe'],
            ['files', 'diavgeiaUrl', 'javascript:alert(1)'], ['display', 'showRowsInPage', '']] as [$section, $key, $bad]) {
            $stored = (string) Config::getConfigValueOf($key);
            $page->call('show', $section)->set("values.$key", $bad)->call('save')->assertHasErrors("values.$key")->assertSee('settings-errors');
            $this->assertSame($stored, (string) Config::getConfigValueOf($key), $key);
        }
    }

    /**
     * R6m (owner, 2026-11-27): the settings planner's grouping. Gone from the page: «Εκτυπώσεις και Excel», «Καταχώριση
     * e-mail» and «Συντήρηση»; «Διαδρομή του mysqldump» and «Μέγιστες γραμμές εκτύπωσης και Excel» are .env values now
     * (MYSQLDUMP_PATH, PRINT_MAX_ROWS), their stored rows untouched.
     */
    public function test_sections_follow_the_owners_plan(): void
    {
        $this->assertSame(['office', 'numbering', 'display', 'registration', 'rights', 'files', 'trash', 'mailbox', 'inbox', 'notifications'],
            array_keys(SettingsCatalog::SECTIONS));
        $this->assertSame([null, 'Πρωτόκολλο', 'Πρωτόκολλο', 'Πρωτόκολλο', 'Πρωτόκολλο', 'Αρχεία', 'Αρχεία', 'E-mail', 'E-mail', 'E-mail'],
            array_column(SettingsCatalog::SECTIONS, 0));
        foreach (['allowUserChangeKeepSelect' => 'rights', 'saveEmailAs' => 'files', 'sendEmailOnDiekperaiosiChange' => 'notifications',
            'alwaysSendReceitForEmails' => 'notifications', 'daysToCheckEmailBack' => 'inbox', 'titleColor' => 'office'] as $key => $section) {
            $this->assertSame($section, SettingsCatalog::sectionOf($key), $key);
        }
        $this->assertSame(['minutesRefreshInterval', 'showRowsInPage', 'maxRowsInFindPage'], SettingsCatalog::SECTIONS['display'][4]);
        $this->assertSame('Υπηρεσία/Οργανισμός', SettingsCatalog::SECTIONS['office'][1]);
        $this->assertSame(['' => 'σελίδα html', 'eml' => 'αρχείο e-mail eml'], SettingsCatalog::all()['saveEmailAs']['options'], 'the stored values stay');

        Config::setConfigValueOf('mysqldumpPath', '/usr/bin/mysqldump');
        Config::setConfigValueOf('maxRowsInXlsExport', '700');
        $admin = $this->admin();
        foreach (['mysqldumpPath', 'maxRowsInXlsExport'] as $key) {
            $this->assertArrayNotHasKey($key, SettingsCatalog::all());
            $this->assertNull(SettingsCatalog::sectionOf($key));
        }
        $html = $this->actingAs($admin)->get('/settings?setting=mysqldumpPath')->assertOk()->getContent();
        $this->assertStringNotContainsString('s-mysqldumpPath', $html);
        $this->assertStringNotContainsString('Αντίγραφα ασφαλείας', $html);
        $this->assertStringNotContainsString('Εκτυπώσεις και Excel', $html);
        $this->assertStringNotContainsString('Καταχώριση e-mail', $html);
        Livewire::actingAs($admin)->test(SettingsPage::class)->call('show', 'backup')->assertSet('section', 'office');
        Livewire::actingAs($admin)->test(SettingsPage::class)->call('show', 'files')->set('values.putStamp', '1')->call('save')->assertHasNoErrors();
        $this->assertSame('/usr/bin/mysqldump', (string) Config::getConfigValueOf('mysqldumpPath'));
        $this->assertSame('700', (string) Config::getConfigValueOf('maxRowsInXlsExport'));
        // the print limit is PRINT_MAX_ROWS (config app.print_max_rows), not the old row
        $this->assertSame(5000, \App\Services\Printing\PrintRange::limit());
        config(['app.print_max_rows' => 12]);
        $this->assertSame(12, \App\Services\Printing\PrintRange::limit());
    }

    /**
     * R6d (was: test_turning_off_the_update_check_clears_the_notice): three settings that do nothing in the new version
     * are no longer shown (owner, 2026-11-19); saving the page leaves their stored values as they were.
     */
    public function test_settings_without_effect_are_hidden_and_kept(): void
    {
        foreach (['updatesAutoCheck' => '1', 'protocolArrowStep' => '10', 'safeNewProtocolNum' => '0'] as $key => $value) {
            Config::setConfigValueOf($key, $value);
        }
        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        foreach (['updatesAutoCheck', 'protocolArrowStep', 'safeNewProtocolNum'] as $key) {
            $page->assertDontSee('id="s-'.$key.'"', false);
            $this->assertArrayNotHasKey($key, SettingsCatalog::all());
        }
        $page->set('values.ipiresiasName', 'Άλλο όνομα')->call('save')->assertHasNoErrors();
        $this->assertSame('1', (string) Config::getConfigValueOf('updatesAutoCheck'));
        $this->assertSame('10', (string) Config::getConfigValueOf('protocolArrowStep'));
        $this->assertSame('0', (string) Config::getConfigValueOf('safeNewProtocolNum'));
    }

    /** R6d: every setting has its line and its explanation (ⓘ); the two numbering settings are framed together. */
    public function test_every_setting_has_help_and_an_explanation(): void
    {
        foreach (SettingsCatalog::all() as $key => $def) {
            $this->assertNotEmpty($def['help'] ?? null, "$key: help");
            // R6m (owner, 2026-11-27): the ⓘ is the owner's text; it may be shorter than the line (showRowsInPage), but never missing
            $this->assertNotEmpty($def['more'] ?? null, "$key: an explanation");
        }
        // R6f: every section's settings have their ⓘ
        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        $count = 0;
        foreach (array_keys(SettingsCatalog::SECTIONS) as $id) {
            $count += substr_count($page->call('show', $id)->html(), 'class="btn btn-link p-0 ms-1 lh-1 align-baseline text-body-secondary setting-more"');
        }
        $this->assertSame(count(SettingsCatalog::all()), $count);
        $html = $page->call('show', 'numbering')->html();
        $this->assertStringContainsString('data-bs-content="'.e(SettingsCatalog::all()['gapCheckMonths']['more']).'"', $html);

        $box = substr($html, strpos($html, 'id="box-numbering"'));
        $box = substr($box, 0, strpos($box, '</fieldset>'));
        $this->assertStringContainsString('id="s-firstProtocolNum"', $box);
        $this->assertStringContainsString('id="s-gapCheckMonths"', $box);
        $this->assertStringContainsString('ο έλεγχος κενών ξεκινά από τον μεγαλύτερο', $box);
        $this->assertStringNotContainsString('id="s-yearInUse"', $box);

        $this->assertSame('Αριθμός αποτελεσμάτων αναζήτησης ανά σελίδα', SettingsCatalog::all()['maxRowsInFindPage']['label']);
    }

    public function test_unused_settings_are_removed(): void
    {
        DB::table('configs')->insert(['key' => 'searchField1', 'value' => 'thema']);
        $migration = require base_path('database/migrations/2026_11_19_000002_remove_unused_settings.php');
        $migration->up();
        $this->assertFalse(Config::where('key', 'searchField1')->exists());
    }

    /** R6m (owner, 2026-11-27): the field suggestions' two values are on the page (Καταχώριση) and mean what they say */
    public function test_field_suggestions_follow_their_two_settings(): void
    {
        $this->assertSame('registration', SettingsCatalog::sectionOf('rowsToSearchGiaSimplirosi'));
        $this->assertSame('registration', SettingsCatalog::sectionOf('rowsToShowGiaSimplirosi'));
        $writer = $this->writer();
        foreach (['Αθήνα', 'Αθήνα', 'Πάτρα', 'Βόλος'] as $i => $place) {
            Protocol::factory()->create(['etos' => 2026, 'protocolnum' => 900 + $i, 'in_topos_ekdosis' => $place]);
        }
        $this->assertSame(['Αθήνα', 'Βόλος', 'Πάτρα'], \App\Support\Suggestions::for($writer)['in_topos_ekdosis']);
        $this->setting('rowsToShowGiaSimplirosi', '1');   // was raised to 50 at least: now 1 means 1, the most used
        $this->assertSame(['Αθήνα'], \App\Support\Suggestions::for($writer)['in_topos_ekdosis']);
        $this->setting('rowsToShowGiaSimplirosi', '');
        $this->setting('rowsToSearchGiaSimplirosi', '2');   // the two latest only
        $this->assertSame(['Βόλος', 'Πάτρα'], \App\Support\Suggestions::for($writer)['in_topos_ekdosis']);

        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class)->call('show', 'registration');
        $box = substr($page->html(), strpos($page->html(), 'id="box-suggestions"'));
        $this->assertStringContainsString('id="s-rowsToSearchGiaSimplirosi"', $box);
        $this->assertStringContainsString('id="s-rowsToShowGiaSimplirosi"', $box);
        $page->set('values.rowsToShowGiaSimplirosi', '0')->call('save')->assertHasErrors('values.rowsToShowGiaSimplirosi');
        $page->set('values.rowsToShowGiaSimplirosi', '30')->call('save')->assertHasNoErrors();
        $this->assertSame('30', (string) Config::getConfigValueOf('rowsToShowGiaSimplirosi'));
    }

    /** R6m (owner, 2026-11-27): the rows of the two settings moved to .env say so, with the old value kept */
    public function test_rows_of_settings_moved_to_env_say_where_they_went(): void
    {
        $migration = require base_path('database/migrations/2026_11_27_000002_settings_moved_to_env.php');
        DB::table('configs')->where('key', 'mysqldumpPath')->delete();
        Config::setConfigValueOf('timeZone', 'Europe/Athens');
        Config::setConfigValueOf('maxRowsInXlsExport', '700');
        $migration->up();
        $migration->up();   // twice: nothing more happens
        $this->assertSame('Δεν χρησιμοποιείται πια: ορίζεται στο .env ως MYSQLDUMP_PATH (δείτε το .env.example).',
            (string) Config::getConfigValueOf('mysqldumpPath'));
        $this->assertSame('Δεν χρησιμοποιείται πια: ορίζεται στο .env ως PRINT_MAX_ROWS (δείτε το .env.example). Παλιά τιμή: 700',
            (string) Config::getConfigValueOf('maxRowsInXlsExport'));
        $this->assertSame(5000, \App\Services\Printing\PrintRange::limit(), 'the row is not read');
        $this->assertStringStartsWith('Δεν χρησιμοποιείται πια: ορίζεται στο .env ως APP_TIMEZONE', (string) Config::getConfigValueOf('timeZone'));
        $this->assertSame('Europe/Athens', \App\Services\Mail\EmailRegistration::zone()->getName(), 'the time zone is APP_TIMEZONE');
        config(['app.timezone' => 'Europe/London']);
        $this->assertSame('Europe/London', \App\Services\Mail\EmailRegistration::zone()->getName());
        $this->assertStringContainsString("MYSQLDUMP_PATH=\n", file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("PRINT_MAX_ROWS=5000\n", file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("APP_TIMEZONE=Europe/Athens\n", file_get_contents(base_path('.env.example')));

        $migration->down();
        $this->assertSame('700', (string) Config::getConfigValueOf('maxRowsInXlsExport'));
        $this->assertFalse(Config::where('key', 'mysqldumpPath')->exists());
    }

    /** R6c: the inbox list of users is whole usernames (it was a substring check) */
    public function test_inbox_users_are_whole_usernames(): void
    {
        $this->setting('defaultImapEmail', 'default');
        $nik = \App\Models\User::factory()->writer()->create(['username' => 'nik']);
        $nikos = \App\Models\User::factory()->writer()->create(['username' => 'Nikos']);
        $this->setting('allowedEmailUsers', 'nikos, maria');
        $this->assertFalse(InboxAccess::allows($nik));
        $this->assertTrue(InboxAccess::allows($nikos));
        // R6m (owner, 2026-11-27): an administrator always has the inbox, listed or not (but not without an account)
        $admin = $this->admin();
        $this->assertTrue(InboxAccess::allows($admin));
        $this->actingAs($admin)->get('/inbox')->assertOk();
        $this->setting('defaultImapEmail', '');
        $this->assertFalse(InboxAccess::allows($admin));
        $this->setting('defaultImapEmail', 'default');
        $this->setting('allowedEmailUsers', '');
        $this->assertTrue(InboxAccess::allows($nik));
    }

    public function test_archive_page_lists_expired_attachments_and_prints_them(): void
    {
        $p = Protocol::factory()->number(7)->create(['fakelos' => 'Φ.1', 'thema' => 'Παλιό θέμα', 'protocoldate' => '2015-03-04']);
        $old = Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'παλιό.pdf', 'expires' => 20200101]);
        Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'ισχύει.pdf', 'expires' => 29990101]);
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(ArchivePage::class)
            ->assertSee('παλιό.pdf')->assertDontSee('ισχύει.pdf')->assertSee('01/01/2020')->assertSee('1 συνημμένο έχει');
        $this->actingAs($admin)->get('/expired')->assertOk()
            ->assertSee('παλιό.pdf')->assertDontSee('ισχύει.pdf')
            ->assertSee('04/03/2015')->assertSee('01/01/2020')   // dates as dates (the old print showed 20200101)
            ->assertSee('Παλιό θέμα');

        Livewire::actingAs($admin)->test(ArchivePage::class)->call('trashExpired')
            ->assertDispatched('toast', type: 'success')->assertSee('Κανένα συνημμένο δεν έχει περάσει');
        $this->assertTrue(Attachment::withTrashed()->find($old->id)->trashed());
    }
}
