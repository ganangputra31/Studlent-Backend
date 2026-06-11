<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function createFromDeal(Request $request, $dealId)
    {
        $request->validate([
            'catatan'  => 'nullable|string|max:1000',
            'deadline' => 'nullable|date',
        ]);

        $deal = Deal::findOrFail($dealId);

        if ($deal->status !== 'accepted') {
            return response()->json(['message' => 'Deal belum disetujui freelancer'], 400);
        }

        $existing = Order::where('id_deal', $dealId)
            ->whereNotIn('status', ['dibatalkan'])
            ->first();

        if ($existing) {
            return response()->json([
                'message'  => 'Order untuk deal ini sudah ada',
                'order_id' => $existing->id_order,
            ], 409);
        }

        $adminFee = 2500;
        $total    = $deal->price + $adminFee;

        $order = Order::create([
            'id_client'      => $deal->id_client,
            'id_freelancer'  => $deal->id_freelancer,
            'id_service'     => null,
            'id_package'     => null,
            'id_deal'        => $deal->id_deal,
            'detail_pesanan' => $deal->catatan ?? 'Project dari deal',
            'catatan'        => $request->catatan,
            'deadline'       => $request->deadline,
            'status'         => 'menunggu_pembayaran',
            'progress'       => 0,
        ]);

        $payment = Payment::create([
            'id_order'      => $order->id_order,
            'amount'        => $total,
            'admin_fee'     => $adminFee,
            'status'        => 'pending',
            'escrow_status' => 'hold',
        ]);

        return response()->json([
            'message'    => 'Order berhasil dibuat',
            'order_id'   => $order->id_order,
            'payment_id' => $payment->id_payment,
            'amount'     => $total,
            'breakdown'  => [
                'base_price' => $deal->price,
                'admin_fee'  => $adminFee,
                'total'      => $total,
            ],
        ], 201);
    }

    public function getStatus($id)
    {
        $order = Order::with('payment')
            ->where('id_order', $id)
            ->firstOrFail();

        $paymentStatus = strtolower((string) ($order->payment?->status ?? 'pending'));

        $isPaid = in_array($paymentStatus, [
            'paid',
            'settlement',
            'capture',
            'success'
        ]);

        return response()->json([
            'id_order'       => $order->id_order,
            'order_id'       => $order->id_order,
            'status'         => $order->status,
            'payment_status' => $paymentStatus,
            'is_paid'        => $isPaid,

            'payment_method' => $order->payment?->metode ?? null,
            'service_name'   => $order->detail_pesanan ?? '-',
            'admin_fee'      => (float) ($order->payment?->admin_fee ?? 2500),
            'amount'         => (float) ($order->payment?->amount ?? 0),
            'created_at'     => optional($order->payment?->created_at)?->toIso8601String(),

            'payment' => [
                'amount'     => (float) ($order->payment?->amount ?? 0),
                'admin_fee'  => (float) ($order->payment?->admin_fee ?? 2500),
                'method'     => $order->payment?->metode ?? null,
                'created_at' => optional($order->payment?->created_at)?->toIso8601String(),
                'status'     => $paymentStatus,
            ],
        ]);
    }
}