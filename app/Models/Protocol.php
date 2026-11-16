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
 * @property integer $protocoldate  (D04: DATE column, shown as Ymd integer; dateOf() for a date)
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

    /** D04: DATE columns, shown to the legacy code as integers Ymd (transitional, see LegacyYmdDate) */
    public const DATE_COLUMNS = ['protocoldate', 'in_date', 'out_date', 'diekp_eos', 'diekp_date'];

    protected $casts = [
        'protocoldate' => \App\Casts\LegacyYmdDate::class,
        'in_date' => \App\Casts\LegacyYmdDate::class,
        'out_date' => \App\Casts\LegacyYmdDate::class,
        'diekp_eos' => \App\Casts\LegacyYmdDate::class,
        'diekp_date' => \App\Casts\LegacyYmdDate::class,
    ];

    /** D04: a date column as a date (for new code), or null */
    public function dateOf(string $column): ?\Carbon\CarbonImmutable
    {
        abort_unless(in_array($column, self::DATE_COLUMNS, true), 500, "Not a date column: $column");
        $raw = $this->getAttributes()[$column] ?? null;
        return is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) ? \Carbon\CarbonImmutable::parse(substr($raw, 0, 10))->startOfDay() : null;
    }

    public function attachments()
    {
        return $this->hasMany('App\Models\Attachment', 'protocol_id');
    }

    public static function last()
    {
        return self::latest()->first();
    }
}
