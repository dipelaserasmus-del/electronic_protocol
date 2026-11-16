<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Attachment
 *
 * @property integer $id
 * @property integer $protocol_id
 * @property string $ada
 * @property string $name
 * @property string $url
 * @property string $keep
 * @property string $flag
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereProtocolId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereName($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereUrl($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereKeep($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereFlag($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Attachment whereUpdatedAt($value)
 */


class Attachment extends Model
{
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'protocol_id',
        'protocolnum',
        'ada',
        'name',
        'savedPath',
        'mimeType',
        'keep',
        'expires',
        'flag'
    ];

    protected $casts = ['size' => 'integer', 'deleted_by' => 'integer'];

    public function protocol(){
        return $this->belongsTo('App\Models\Protocol');
    }

    /** D03: the user who moved this attachment to the trash */
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    protected static function booted(): void
    {
        // D03: size and SHA-256 of the stored file, whenever the stored file changes
        static::saving(function (Attachment $attachment) {
            if ($attachment->isDirty('savedPath') || ($attachment->savedPath && $attachment->size === null)) {
                $attachment->fillFileMetadata();
            }
        });
        // D03: who moved it to the trash (soft delete); cleared when restored
        static::softDeleted(function (Attachment $attachment) {
            $attachment->deleted_by = auth()->id();
            $attachment->saveQuietly();
        });
        static::restored(function (Attachment $attachment) {
            $attachment->deleted_by = null;
            $attachment->saveQuietly();
        });
    }

    /**
     * D03: read size and hash from the stored file (default disk). A file that is not there leaves both empty.
     * For an attachment in the trash, the file may already have been moved from arxeio/ to trash/.
     * @return bool whether a file was found
     */
    public function fillFileMetadata(): bool
    {
        $path = $this->storedFilePath();
        if ($path === null) {
            $this->size = null;
            $this->sha256 = null;
            return false;
        }
        $full = \Illuminate\Support\Facades\Storage::path($path);
        $this->size = filesize($full);
        $this->sha256 = hash_file('sha256', $full);
        return true;
    }

    /** Path on the default disk where the file is now, or null */
    public function storedFilePath(): ?string
    {
        $storage = \Illuminate\Support\Facades\Storage::disk();
        $candidates = [$this->savedPath];
        if ($this->trashed() && $this->savedPath) {
            $candidates[] = str_replace('arxeio', 'trash', $this->savedPath);   // attachDelete moves the file, keeps the path
        }
        foreach ($candidates as $p) {
            if (is_string($p) && $p !== '' && $storage->exists($p) && is_file($storage->path($p))) {
                return $p;
            }
        }
        return null;
    }
}
