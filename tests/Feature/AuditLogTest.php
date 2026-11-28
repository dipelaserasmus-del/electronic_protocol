<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditEntry;
use App\Models\Config;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\TrashedProtocol;
use App\Models\User;
use App\Services\Attachments\Trash;
use App\Services\Audit\AuditLog;
use App\Services\Protocols\ProtocolTrash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** X01: the audit log records who did what and when. */
class AuditLogTest extends FeatureTestCase
{
    /** @return list<AuditEntry> */
    private function entries(?string $action = null): array
    {
        return AuditEntry::query()->when($action, fn ($q) => $q->where('action', $action))->orderBy('id')->get()->all();
    }

    private function last(string $action): AuditEntry
    {
        return AuditEntry::where('action', $action)->orderByDesc('id')->firstOrFail();
    }

    public function test_a_protocol_created_and_changed_is_recorded_with_who_when_and_what(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer);
        Carbon::setTestNow('2026-11-28 10:00:00');

        $p = Protocol::factory()->number(7, 2026)->create(['thema' => 'Αίτηση', 'fakelos' => 'Φ.1']);
        $created = $this->last('created');
        $this->assertSame(['protocol', $p->id, $p->id, $writer->id, $writer->username],
            [$created->entity_type, $created->entity_id, $created->protocol_id, $created->user_id, $created->username]);
        $this->assertSame([null, 'Αίτηση'], $created->changes['thema']);
        $this->assertSame('7/2026', $created->context['ref']);
        $this->assertSame('127.0.0.1', $created->ip);
        $this->assertSame('2026-11-28 10:00:00', $created->created_at->format('Y-m-d H:i:s'));
        $this->assertArrayNotHasKey('id', $created->changes);
        $this->assertArrayNotHasKey('created_at', $created->changes);

        $p->update(['thema' => 'Αίτηση γονέα', 'fakelos' => 'Φ.1']);
        $this->assertSame(['thema' => ['Αίτηση', 'Αίτηση γονέα']], $this->last('updated')->changes, 'only what changed');

        $count = AuditEntry::count();
        $p->touch();
        $p->save();
        $this->assertSame($count, AuditEntry::count(), 'nothing changed (only the time stamp): no entry');
    }

    public function test_an_attachment_to_the_trash_back_and_deleted_for_good(): void
    {
        Storage::fake();
        $admin = $this->admin();
        $this->actingAs($admin);
        $p = Protocol::factory()->create();
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'έγγραφο.pdf']);
        $this->assertSame([null, 'έγγραφο.pdf'], $this->last('created')->changes['name']);

        app(Trash::class)->trash($a->fresh());
        $trashed = $this->last('trashed');
        $this->assertSame(['attachment', $a->id, $p->id, $admin->id, 'έγγραφο.pdf'],
            [$trashed->entity_type, $trashed->entity_id, $trashed->protocol_id, $trashed->user_id, $trashed->context['name']]);

        app(Trash::class)->restore($a->fresh());
        $this->assertSame($a->id, $this->last('restored')->entity_id);

        app(Trash::class)->purge($a->fresh());
        $this->assertSame(['attachment', $a->id, $p->id], [$this->last('purged')->entity_type, $this->last('purged')->entity_id, $this->last('purged')->protocol_id]);
    }

    public function test_a_protocol_to_the_trash_back_and_the_daily_clean_up_by_nobody(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $p = Protocol::factory()->number(8, 2026)->create();
        ProtocolAssignment::create(['protocol_id' => $p->id, 'user_id' => $admin->id, 'type' => 'd']);
        $this->assertSame([$p->id, 'assignment'], [$this->last('created')->protocol_id, $this->last('created')->entity_type]);

        $t = app(ProtocolTrash::class)->trash($admin, $p);
        $this->assertSame(['protocol', $p->id, '8/2026', $admin->id],
            [$this->last('trashed')->entity_type, $this->last('trashed')->protocol_id, $this->last('trashed')->context['ref'], $this->last('trashed')->user_id]);

        app(ProtocolTrash::class)->restore($admin, $t);
        $this->assertSame([$p->id, '8/2026'], [$this->last('restored')->protocol_id, $this->last('restored')->context['ref']]);

        // in the trash longer than the retention: the clean-up runs during someone's request, but it is nobody's doing
        $t = app(ProtocolTrash::class)->trash($admin, $p->fresh());
        TrashedProtocol::whereKey($t->id)->update(['deleted_at' => Carbon::now()->subDays(40)]);
        $this->setting('trashRetentionDays', 30);
        $this->assertSame(1, app(ProtocolTrash::class)->purgeExpired());
        $purged = $this->last('purged');
        $this->assertSame([$p->id, null, null, true], [$purged->protocol_id, $purged->user_id, $purged->username, $purged->context['automatic']]);
    }

    public function test_a_user_s_password_is_never_recorded_and_their_own_view_is_not_either(): void
    {
        $this->actingAs($this->admin());
        $user = User::factory()->writer()->create(['password' => bcrypt('μυστικό-1')]);
        $this->assertSame([null, '***'], $this->last('created')->changes['password']);

        $user->update(['password' => bcrypt('μυστικό-2'), 'name' => 'Νέο όνομα']);
        $changes = $this->last('updated')->changes;
        $this->assertSame(['***', '***'], $changes['password']);
        $this->assertSame('Νέο όνομα', $changes['name'][1]);
        $this->assertStringNotContainsString('$2y$', json_encode(AuditEntry::pluck('changes')));

        $count = AuditEntry::count();
        $user->forceFill(['remember_token' => 'x', 'preferences' => ['inboxEnvelope' => 'none']])->save();
        $this->assertSame($count, AuditEntry::count());
    }

    public function test_a_setting_changed_is_recorded_with_its_key_not_the_application_s_own_rows(): void
    {
        $this->actingAs($this->admin());
        Config::setConfigValueOf('showRowsInPage', '20');   // as the settings page saves them
        Config::setConfigValueOf('showRowsInPage', '50');
        $e = $this->last('updated');
        $this->assertSame(['setting', 'showRowsInPage', ['20', '50']], [$e->entity_type, $e->context['key'], $e->changes['value']]);

        $count = AuditEntry::count();
        Config::getConfigValueOf('aKeyNobodySet');       // a row made empty: nothing to record
        Config::setConfigValueOf('trashLastPurge', '2026-11-28');
        $this->assertSame($count, AuditEntry::count());
    }

    public function test_folders_are_recorded(): void
    {
        $this->actingAs($this->admin());
        $f = Keepvalue::create(['fakelos' => 'Φ.99', 'describe' => 'Δοκιμή']);
        $this->assertSame(['folder', 'Φ.99'], [$this->last('created')->entity_type, $this->last('created')->context['folder']]);
        $f->delete();
        $this->assertSame($f->id, $this->last('deleted')->entity_id);
    }

    public function test_sign_ins_are_recorded_without_the_password(): void
    {
        $user = User::factory()->writer()->create(['password' => bcrypt('password')]);

        $this->post('/login', ['username' => $user->username, 'password' => 'λάθος-κωδικός']);
        $failed = $this->last('login_failed');
        $this->assertSame([null, $user->id, $user->username], [$failed->user_id, $failed->entity_id, $failed->context['typed']]);
        $this->post('/login', ['username' => 'κανένας', 'password' => 'λάθος-κωδικός']);
        $this->assertSame([null, 'κανένας'], [$this->last('login_failed')->entity_id, $this->last('login_failed')->context['typed']]);
        $this->assertStringNotContainsString('λάθος-κωδικός', json_encode(AuditEntry::all()->toArray()));

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $this->assertSame([$user->id, $user->username], [$this->last('login')->user_id, $this->last('login')->username]);
        $this->post('/logout');
        $this->assertSame($user->id, $this->last('logout')->user_id);
    }

    public function test_a_download_is_recorded(): void
    {
        Storage::fake();
        $this->setting('putStamp', 0);
        $admin = $this->admin();
        $p = Protocol::factory()->create();
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'απόφαση.pdf']);

        $this->actingAs($admin)->get('/download/'.$a->id)->assertOk();
        $e = $this->last('downloaded');
        $this->assertSame([$a->id, $p->id, $admin->id, 'απόφαση.pdf'], [$e->entity_id, $e->protocol_id, $e->user_id, $e->context['name']]);
    }

    public function test_the_log_is_append_only_and_kept_as_long_as_set(): void
    {
        $this->actingAs($this->admin());
        Protocol::factory()->create();
        $entry = $this->last('created');
        try {
            $entry->update(['action' => 'x']);
            $this->fail('an entry was changed');
        } catch (\LogicException) {
        }
        try {
            $entry->delete();
            $this->fail('an entry was deleted');
        } catch (\LogicException) {
        }

        AuditEntry::query()->toBase()->update(['created_at' => Carbon::now()->subDays(400)]);
        $old = AuditEntry::count();
        Protocol::factory()->create();
        $new = AuditEntry::count() - $old;
        $this->assertSame(0, AuditLog::purgeExpired(), 'AUDIT_KEEP_DAYS empty: everything is kept');
        config(['app.audit_keep_days' => 365]);
        $this->assertSame($old, AuditLog::purgeExpired());
        $this->assertCount($new, $this->entries(), 'only the old ones');
    }

    public function test_a_failure_to_write_the_log_does_not_stop_the_work(): void
    {
        $this->actingAs($this->admin());
        Schema::drop('audit_log');
        $p = Protocol::factory()->create(['thema' => 'Παρ’ όλα αυτά']);
        $this->assertSame('Παρ’ όλα αυτά', $p->fresh()->thema);
    }
}
