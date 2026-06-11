<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Escrow;

class EscrowService
{
    public function hold($payment)
    {
        return Escrow::create([
            'id_payment' => $payment->id_payment,
            'amount' => $payment->amount,
            'status' => 'hold'
        ]);
    }

    public function release($payment, $feeData)
    {
        $escrow = Escrow::where('id_payment', $payment->id_payment)->first();

        $escrow->platform_fee = $feeData['platform_fee'];
        $escrow->freelancer_amount = $feeData['freelancer_amount'];
        $escrow->status = 'released';
        $escrow->released_at = now();
        $escrow->save();

        return $escrow;
    }
}