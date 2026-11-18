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

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */



}
