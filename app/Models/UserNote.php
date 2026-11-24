<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** N05: a user's notepad (App\Http\Controllers\NotesController). */
class UserNote extends Model
{
    protected $fillable = ['user_id', 'body', 'version'];

    protected $casts = ['user_id' => 'integer', 'version' => 'integer'];
}
