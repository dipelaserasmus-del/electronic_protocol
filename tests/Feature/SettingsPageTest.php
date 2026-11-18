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
        $this->actingAs($this->admin())->get('/settings')->assertOk()->assertSee('Υπηρεσία και αρίθμηση')->assertSee('Συντήρηση');
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
        $page = Livewire::actingAs($this->admin())->test(SettingsPage::class);
        foreach (array_keys(SettingsCatalog::all()) as $key) {
            $page->assertSee('id="s-'.$key.'"', false);
        }
        $page->set('values.ipiresiasName', '  Γυμνάσιο Δοκιμών  ')->set('values.limitProtocolAccessList', '1')
            ->set('values.allowWriterUpdateProtocol', '1')->set('values.titleColor', '#1f4e79')
            ->set('values.madeUpKey', 'x')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', type: 'success');

        $this->assertSame('Γυμνάσιο Δοκιμών', Config::getConfigValueOf('ipiresiasName'));
        $this->assertSame('1', Config::getConfigValueOf('limitProtocolAccessList'));
        $this->assertSame('1', Config::getConfigValueOf('allowWriterUpdateProtocol'));
        $this->assertSame('#1f4e79', Config::getConfigValueOf('titleColor'));
        $this->assertFalse(Config::where('key', 'madeUpKey')->exists());

        $page->set('values.limitProtocolAccessList', '0')->call('save');
        $this->assertSame('0', Config::getConfigValueOf('limitProtocolAccessList'));
    }

    public function test_wrong_values_are_refused_at_their_field_and_nothing_is_saved(): void
    {
        $before = Config::getConfigValueOf('ipiresiasName');
        Livewire::actingAs($this->admin())->test(SettingsPage::class)
            ->set('values.ipiresiasName', 'Νέο όνομα')
            ->set('values.titleColor', 'red; background:url(x)')
            ->set('values.yearInUse', '26')
            ->set('values.allowWriterUpdateProtocol', '5')
            ->set('values.mysqldumpPath', '/bin/sh')
            ->set('values.diavgeiaUrl', 'javascript:alert(1)')
            ->set('values.showRowsInPage', '')
            ->call('save')
            ->assertHasErrors(['values.titleColor', 'values.yearInUse', 'values.allowWriterUpdateProtocol', 'values.mysqldumpPath',
                'values.diavgeiaUrl', 'values.showRowsInPage'])
            ->assertSee('settings-errors');
        $this->assertSame($before, Config::getConfigValueOf('ipiresiasName'));
    }

    public function test_mysqldump_path_accepts_windows_and_linux_paths(): void
    {
        foreach (['', 'c:\xampp\mysql\bin\mysqldump.exe', '/usr/bin/mariadb-dump'] as $path) {
            Livewire::actingAs($this->admin())->test(SettingsPage::class)->set('values.mysqldumpPath', $path)->call('save')->assertHasNoErrors();
        }
    }

    public function test_turning_off_the_update_check_clears_the_notice(): void
    {
        Config::setConfigValueOf('needsUpdate', 1);
        Livewire::actingAs($this->admin())->test(SettingsPage::class)->set('values.updatesAutoCheck', '0')->call('save');
        $this->assertSame('0', (string) Config::getConfigValueOf('needsUpdate'));
    }

    public function test_unused_settings_are_removed(): void
    {
        DB::table('configs')->insert(['key' => 'searchField1', 'value' => 'thema']);
        $migration = require base_path('database/migrations/2026_11_19_000002_remove_unused_settings.php');
        $migration->up();
        $this->assertFalse(Config::where('key', 'searchField1')->exists());
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
