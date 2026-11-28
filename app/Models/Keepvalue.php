<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\Keepvalue
 *
 * @property integer $id
 * @property string $fakelos
 * @property integer $keep
 * @property string $keep_alt
 * @property string $describe
 * @property string $remarks
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $readNotifications
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $unreadNotifications
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereFakelos($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereKeep($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereKeepAlt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereDescribe($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereRemarks($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Keepvalue whereUpdatedAt($value)
 */

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Keepvalue;

class Keepvalue extends Model
{
    use \App\Models\Concerns\Audited;   // X01: changes go to the audit log

    public function auditType(): string
    {
        return 'folder';
    }

    public function auditContext(): array
    {
        return ['folder' => $this->fakelos];
    }

    use HasFactory;


    use Notifiable;

    /** D06: describe and remarks are TEXT; room for 4-byte characters (like Protocol::LONG_TEXT_MAX). */
    public const TEXT_MAX = 16000;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fakelos', 'keep', 'keep_alt', 'describe', 'remarks',
    ];

    /** R6b: users who get protocols of this folder automatically (App\Models\FolderAssignment) */
    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FolderAssignment::class);
    }

    /** Folders in their usual order: Φ.1, Φ.2 … Φ.10 (by the number after "Φ."), then the rest by code */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-3)+0<>0 DESC, SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-(3))+0, `fakelos`');
    }

    /** "Διατήρηση": years, or the alternative text (e.g. "Μόνιμα") */
    public function keepLabel(): string
    {
        if ($this->keep) {
            return $this->keep.' '.((int) $this->keep === 1 ? 'έτος' : 'έτη');
        }
        return (string) $this->keep_alt;
    }

    /** The retention ("Διατήρηση") a folder gives its attachments: its years, else its text; '' without a folder. */
    public static function retentionOf(?string $folder): string
    {
        $k = $folder ? static::whereFakelos($folder)->first() : null;

        return (string) ($k?->keep ?: $k?->keep_alt ?: '');
    }
}
