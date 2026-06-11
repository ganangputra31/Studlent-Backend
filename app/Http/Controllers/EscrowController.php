<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\FeeService;
use App\Services\WalletService;

class EscrowController extends Controller
{
    public function release($paymentId, FeeService $feeService, WalletService $walletService)
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->escrow_status === 'released') {
            return response()->json(['message' => 'Escrow sudah pernah dicairkan'], 400);
        }

        if ($payment->status !== 'paid') {
            return response()->json(['message' => 'Payment belum lunas'], 400);
        }

        // ← FIX: ambil freelancer dari order untuk dapat joined_at yang benar
        $order      = Order::with('freelancer')->where('id_order', $payment->id_order)->firstOrFail();
        $freelancer = $order->freelancer;

        // ← FIX: gunakan $payment->admin_fee bukan hardcode 2000
        $baseAmount = $payment->amount - ($payment->admin_fee ?? 2500);

        // ← FIX: pakai joined_at freelancer, bukan created_at payment
        $fee = $feeService->calculate($baseAmount, $freelancer->joined_at);

        $payment->platform_fee       = $fee['platform_fee'];
        $payment->freelancer_receive = $fee['freelancer_amount'];
        $payment->escrow_status      = 'released';
        $payment->save();

        $walletService->credit(
            $order->id_freelancer,
            $fee['freelancer_amount'],
            'escrow_release',
            $payment->id_payment
        );

        return response()->json([
            'message' => 'Dana berhasil dilepas',
            'data'    => $fee,
        ]);
    }
}