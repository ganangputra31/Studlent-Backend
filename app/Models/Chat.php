<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $primaryKey = 'id_chat';

    protected $fillable = [
        'id_order',
        'sender_id',
        'pesan'
    ];
}