<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D02: one user assigned to one protocol, for διεκπεραίωση (type "d") or ενημέρωση (type "e").
 * Written by App\Support\Assignments::replace (R1a; the old text column protocols.diekperaiosi is gone).
 *
 * @property int $protocol_id
 * @property int $user_id
 * @property string $type
 */
class ProtocolAssignment extends Model
{
    use \App\Models\Concerns\Audited;   // X01: changes go to the audit log

    public function auditType(): string
    {
        return 'assignment';
    }

    public function auditProtocolId(): ?int
    {
        return (int) $this->protocol_id;
    }

    protected $fillable = ['protocol_id', 'user_id', 'type'];

    protected $casts = ['protocol_id' => 'integer', 'user_id' => 'integer'];

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
