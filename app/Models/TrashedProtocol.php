<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * R4h: a protocol in the trash (App\Services\Protocols\ProtocolTrash). data = ['protocol' => the row's columns as
 * they were, 'assignments' => its protocol_assignments rows, 'attachments' => ids of the attachments trashed with it].
 */
class TrashedProtocol extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'protocoldate' => 'date', 'deleted_at' => 'datetime',
        'protocol_id' => 'integer', 'protocolnum' => 'integer', 'etos' => 'integer', 'user_id' => 'integer', 'deleted_by' => 'integer'];

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** the attachments trashed with it (still in the attachments' trash) */
    public function attachments()
    {
        return Attachment::onlyTrashed()->whereIn('id', $this->data['attachments'] ?? [])->where('protocol_id', $this->protocol_id);
    }

    public function label(): string
    {
        return "{$this->protocolnum}/{$this->etos}";
    }
}
