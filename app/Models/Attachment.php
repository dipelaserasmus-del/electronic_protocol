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

    public function protocol(){
        return $this->belongsTo('App\Models\Protocol');
    }
}
