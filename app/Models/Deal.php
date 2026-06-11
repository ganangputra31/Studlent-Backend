<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $primaryKey = 'id_deal';

    protected $fillable = [
        'id_client',
        'id_freelancer',
        'price',
        'status',
        'catatan',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'id_client', 'id_user');
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'id_freelancer', 'id_user');
    }
}