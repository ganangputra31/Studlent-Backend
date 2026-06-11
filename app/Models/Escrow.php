<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Escrow extends Model
{
    protected $primaryKey = 'id_escrow';
    protected $table = 'escrow';
    protected $fillable = [
        'id_payment',
        'amount',
        'platform_fee',
        'freelancer_amount',
        'status',
        'released_at'
    ];
}