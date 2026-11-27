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
        'flag',
        'stamp_in_file',   // R4f
    ];

    /** D03: numbers; D04: DATE column, shown to the legacy code as an integer Ymd (transitional, see LegacyYmdDate) */
    protected $casts = ['size' => 'integer', 'deleted_by' => 'integer', 'expires' => \App\Casts\LegacyYmdDate::class, 'stamp_in_file' => 'boolean', 'stamp_opaque' => 'boolean'];

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
        // D03: size and SHA-256 of the stored file on every save. Not only when the path changes: uploading a file
        // with the same name again overwrites the file at the same path (review finding).
        static::saving(function (Attachment $attachment) {
            if ($attachment->isDirty('savedPath') || $attachment->savedPath) {
                $attachment->fillFileMetadata();
            }
        });
        // D03: who moved it to the trash (soft delete); cleared when restored
        static::softDeleted(function (Attachment $attachment) {
            \App\Services\Stamp\StampedCopy::forget($attachment);   // R4f: the stamped copy is made again if restored
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
        foreach ($this->fileCandidates() as $p) {
            if ($storage->exists($p) && is_file($storage->path($p))) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Where the file may be, in the order to look (also used by D10's import of the server's files).
     * @return list<string>
     */
    public function fileCandidates(): array
    {
        $path = (string) $this->savedPath;
        if ($path === '') {
            return [];
        }
        // attachDelete moves the file to trash/ but keeps the path; look there first, because a newer upload with
        // the same name may now be at the old path (review finding)
        return $this->trashed() ? array_values(array_unique([str_replace('arxeio', 'trash', $path), $path])) : [$path];
    }

    /** R6c: the end of the keeping time as a date (the attribute itself is Ymd for legacy code until Z01), or null */
    public function expiresDate(): ?\Carbon\CarbonImmutable
    {
        $raw = $this->getAttributes()['expires'] ?? null;
        return is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? \Carbon\CarbonImmutable::parse(substr($raw, 0, 10))->startOfDay() : null;
    }
}
