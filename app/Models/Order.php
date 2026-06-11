<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $primaryKey = 'id_order';

    protected $fillable = [
        'id_client',
        'id_freelancer',
        'id_service',
        'id_package',
        'id_deal',
        'detail_pesanan',
        'catatan',
        'deadline',
        'status',
        'progress',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'id_client', 'id_user');
    }

    public function freelancer()
    {
        return $this->belongsTo(User::class, 'id_freelancer', 'id_user');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'id_order', 'id_order');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'id_deal', 'id_deal');
    }
}