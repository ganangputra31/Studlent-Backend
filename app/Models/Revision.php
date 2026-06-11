<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Revision extends Model
{
    protected $primaryKey = 'id_revisi';

    protected $fillable = [
        'id_order',
        'pesan',
        'status'
    ];
}