<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Protocol
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $protocolnum
 * @property integer $protocoldate
 * @property integer $etos
 * @property string $fakelos
 * @property string $thema
 * @property string $in_num
 * @property integer $in_date
 * @property string $in_topos_ekdosis
 * @property string $in_arxi_ekdosis
 * @property string $in_paraliptis
 * @property string $diekperaiosi
 * @property string $in_perilipsi
 * @property integer $out_date
 * @property string $out_to
 * @property string $out_cc
 * @property string $out_perilipsi
 * @property string $keywords
 * @property string $paratiriseis
 * @property string $flags
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereProtocolnum($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereProtocoldate($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereEtos($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereFakelos($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereThema($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInNum($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInDate($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInToposEkdosis($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInArxiEkdosis($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInParaliptis($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInResponsible($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereInPerilipsi($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereOutDate($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereOutSintaktis($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereOutTo($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereOutCc($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereOutPerilipsi($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereKeywords($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereParatiriseis($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereFlags($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Protocol whereUpdatedAt($value)
 */


class Protocol extends Model
{
    use HasFactory;

    /**
     * D01: the longest text (in characters) for the TEXT columns thema, in_arxi_ekdosis, in_perilipsi and
     * out_perilipsi. TEXT holds 65,535 bytes; 16,000 characters fit even if every one takes 4 bytes (emoji).
     */
    public const LONG_TEXT_MAX = 16000;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'protocolnum',
        'protocoldate',
        'etos',
        'fakelos',
        'thema',
        'in_num',
        'in_date',
        'in_topos_ekdosis',
        'in_arxi_ekdosis',
        'in_paraliptis',
        'diekperaiosi',
        'diekp_eos',
        'in_perilipsi',
        'out_date',
        'diekp_date',
        'sxetiko',
        'out_to',
        'out_perilipsi',
        'keywords',
        'paratiriseis'
    ];

    public function attachments()
    {
        return $this->hasMany('App\Models\Attachment', 'protocol_id');
    }

    /** D02: users assigned for διεκπεραίωση (type d) or ενημέρωση (type e) */
    public function assignments()
    {
        return $this->hasMany(ProtocolAssignment::class, 'protocol_id');
    }

    protected static function booted(): void
    {
        // D02: the legacy form writes the text "d14,e10"; keep the assignments table in step with it
        static::saved(function (Protocol $protocol) {
            if ($protocol->wasRecentlyCreated || $protocol->wasChanged('diekperaiosi')) {
                \App\Support\Assignments::sync($protocol);
            }
        });
    }

    /** D02: assigned to this user (for type d or e, or either) */
    public function scopeAssignedTo($query, int $userId, ?string $type = null)
    {
        return $query->whereHas('assignments', function ($q) use ($userId, $type) {
            $q->where('user_id', $userId);
            if ($type !== null) {
                $q->where('type', $type);
            }
        });
    }

    /** D02: assigned to anyone for this type */
    public function scopeAssignedFor($query, string $type)
    {
        return $query->whereHas('assignments', fn ($q) => $q->where('type', $type));
    }

    /** S02 "limitProtocolAccessList": created by the user or assigned to them (d or e) */
    public function scopeOwnOrAssigned($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere(fn ($q) => $q->assignedTo($userId));
        });
    }

    public static function last()
    {
        return self::latest()->first();
    }
}
