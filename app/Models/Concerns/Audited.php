<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * X01: a model whose changes go to the audit log by themselves: created, updated (only the fields that changed, old
 * and new), deleted; with soft deletes: trashed, restored (deleted_at back to empty), purged (deleted for good).
 *
 * Not through the model, so not recorded here: query-builder updates and deletes (DB::table(...), Model::where()->
 * update()) and saveQuietly(). Where those change something a user did, the code writes the entry itself
 * (docs/AUDIT-LOG.md lists where).
 *
 * A model may override: auditType() (the name in the log), auditProtocolId() (the protocol it belongs to),
 * auditContext() (what helps to read the entry), auditIgnored() (fields never recorded), auditMasked() (fields
 * recorded only as «changed», e.g. a password), auditSkipped() (an internal row not to record at all).
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(function (Model $m) {
            if (($values = $m->auditValues($m->getAttributes(), false)) !== []) {   // e.g. a setting's row made empty
                $m->writeAudit('created', $values);
            }
        });
        static::updated(function (Model $m) {
            $changes = [];
            foreach ($m->getChanges() as $field => $new) {
                $changes[$field] = [$m->getRawOriginal($field), $new];
            }
            $restored = array_key_exists('deleted_at', $changes) && $changes['deleted_at'][0] !== null && $changes['deleted_at'][1] === null;
            $changes = $m->auditFilter($changes);
            if ($changes !== [] || $restored) {
                $m->writeAudit($restored ? 'restored' : 'updated', $changes);
            }
        });
        static::deleted(function (Model $m) {
            $soft = method_exists($m, 'isForceDeleting') && ! $m->isForceDeleting();
            $m->writeAudit($soft ? 'trashed' : (method_exists($m, 'isForceDeleting') ? 'purged' : 'deleted'), $m->auditValues($m->getRawOriginal(), true));
        });
    }

    public function auditType(): string
    {
        return \Illuminate\Support\Str::snake(class_basename($this));
    }

    public function auditProtocolId(): ?int
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function auditContext(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function auditIgnored(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function auditMasked(): array
    {
        return [];
    }

    protected function auditSkipped(): bool
    {
        return false;
    }

    /** @param array<string, array{0: mixed, 1: mixed}> $changes */
    protected function writeAudit(string $action, array $changes): void
    {
        if ($this->auditSkipped()) {
            return;
        }
        AuditLog::record($action, $this->auditType(), (int) $this->getKey(), $this->auditProtocolId(), $changes, $this->auditContext());
    }

    /**
     * The values a new row was created with ($old false: [null, value]) or a deleted row had ($old true: [value, null]);
     * empty values are left out.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    protected function auditValues(array $values, bool $old): array
    {
        $values = array_filter($values, fn ($v) => $v !== null && $v !== '');

        return $this->auditFilter(array_map(fn ($v) => $old ? [$v, null] : [null, $v], $values));
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    protected function auditFilter(array $changes): array
    {
        $ignored = array_merge([$this->getKeyName(), 'created_at', 'updated_at'], $this->auditIgnored());
        $changes = array_diff_key($changes, array_flip($ignored));
        foreach (array_intersect_key($changes, array_flip($this->auditMasked())) as $field => [$old, $new]) {
            $changes[$field] = [$old === null ? null : '***', $new === null ? null : '***'];
        }

        return $changes;
    }
}
