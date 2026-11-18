<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R6b: one automatic assignment of a folder: protocols saved in the folder go to this user for διεκπεραίωση ("d")
 * or ενημέρωση ("e"). Applied by App\Services\Protocols\ProtocolWriter (R1d).
 *
 * @property int $keepvalue_id
 * @property int $user_id
 * @property string $type
 */
class FolderAssignment extends Model
{
    protected $fillable = ['keepvalue_id', 'user_id', 'type'];

    protected $casts = ['keepvalue_id' => 'integer', 'user_id' => 'integer'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Keepvalue::class, 'keepvalue_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
