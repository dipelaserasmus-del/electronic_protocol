<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * X01: one line of the audit log (table audit_log). Append-only: an entry is never changed or deleted by the
 * application; App\Services\Audit\AuditLog writes them and its purgeExpired() alone removes old ones (the retention,
 * AUDIT_KEEP_DAYS), with a query, not through this model.
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property ?int $user_id
 * @property ?string $username
 * @property string $action
 * @property ?string $entity_type
 * @property ?int $entity_id
 * @property ?int $protocol_id
 * @property ?array $changes
 * @property ?array $context
 * @property ?string $ip
 */
class AuditEntry extends Model
{
    protected $table = 'audit_log';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array', 'context' => 'array', 'created_at' => 'datetime',
        'user_id' => 'integer', 'entity_id' => 'integer', 'protocol_id' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The audit log is append-only.'));
        static::deleting(fn () => throw new \LogicException('The audit log is append-only.'));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
