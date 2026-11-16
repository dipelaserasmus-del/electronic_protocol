<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D02: one user assigned to one protocol, for διεκπεραίωση (type "d") or ενημέρωση (type "e").
 * Kept in sync with protocols.diekperaiosi by App\Support\Assignments::sync (the legacy form writes the text).
 *
 * @property int $protocol_id
 * @property int $user_id
 * @property string $type
 */
class ProtocolAssignment extends Model
{
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
