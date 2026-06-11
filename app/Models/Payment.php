<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $primaryKey = 'id_payment';

   protected $fillable = [
        'id_order',
        'metode',
        'amount',
        'admin_fee',
        'gateway_trx_id',
        'midtrans_order_id',
        'payment_url',
        'status',
        'escrow_status',
        'fee_percent',
        'platform_fee',
        'freelancer_receive',
        'tanggal_bayar'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}