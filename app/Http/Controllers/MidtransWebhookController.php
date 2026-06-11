<?php
// app/Http/Controllers/MidtransWebhookController.php

namespace App\Http\Controllers;

use App\Models\Escrow;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Endpoint POST /api/midtrans/callback
     * Dipanggil server Midtrans — bukan Flutter.
     * Tidak perlu auth Sanctum, tapi tetap verifikasi signature key.
     */
    public function callback(Request $request)
    {
        $payload = $request->all();

        Log::info('Midtrans webhook received', $payload);

        $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
        $fraudStatus       = strtolower((string) ($payload['fraud_status'] ?? ''));
        $statusCode        = (string) ($payload['status_code'] ?? '');
        $gatewayTrxId      = (string) ($payload['order_id'] ?? '');
        $grossAmount       = (string) ($payload['gross_amount'] ?? '');
        $signatureKey      = (string) ($payload['signature_key'] ?? '');

        if (!$gatewayTrxId) {
            return response()->json(['message' => 'order_id missing'], 422);
        }

        $expectedSignature = hash(
            'sha512',
            $gatewayTrxId .
            $statusCode .
            $grossAmount .
            config('midtrans.server_key')
        );

        if ($expectedSignature !== $signatureKey) {
            Log::warning('Midtrans webhook: invalid signature', [
                'order_id' => $gatewayTrxId,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('midtrans_order_id', $gatewayTrxId)->first();

        if (!$payment) {
            Log::warning('Midtrans webhook: payment not found', [
                'midtrans_order_id' => $gatewayTrxId,
            ]);

            return response()->json(['message' => 'Payment tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($transactionStatus, $fraudStatus, $payment, $payload) {
            $order = Order::where('id_order', $payment->id_order)->first();

            if ($payment->status === 'paid') {
                Log::info('Midtrans webhook ignored: payment already paid', [
                    'order_id' => $payment->id_order,
                    'midtrans_order_id' => $payment->midtrans_order_id,
                ]);
                return;
            }

            if (!empty($payload['payment_type'])) {
                $payment->metode = $payload['payment_type'];
            }

            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'challenge') {
                    $payment->status = 'challenge';
                    $payment->save();

                    if ($order) {
                        $order->status = 'menunggu_verifikasi';
                        $order->save();
                    }

                    Log::info('Payment CHALLENGE', [
                        'order_id' => $payment->id_order,
                    ]);
                } else {
                    $payment->status = 'paid';
                    $payment->escrow_status = 'hold';
                    $payment->tanggal_bayar = now();
                    $payment->save();

                    Escrow::firstOrCreate(
                        ['id_payment' => $payment->id_payment],
                        [
                            'amount' => $payment->amount,
                            'platform_fee' => 0,
                            'freelancer_amount' => 0,
                            'status' => 'hold',
                        ]
                    );

                    if ($order) {
                        $order->status = 'diproses';
                        $order->save();
                    }

                    Log::info('Payment SUCCESS (capture)', [
                        'order_id' => $payment->id_order,
                    ]);
                }

                return;
            }

            if ($transactionStatus === 'settlement') {
                $payment->status = 'paid';
                $payment->escrow_status = 'hold';
                $payment->tanggal_bayar = now();
                $payment->save();

                Escrow::firstOrCreate(
                    ['id_payment' => $payment->id_payment],
                    [
                        'amount' => $payment->amount,
                        'platform_fee' => 0,
                        'freelancer_amount' => 0,
                        'status' => 'hold',
                    ]
                );

                if ($order) {
                    $order->status = 'diproses';
                    $order->save();
                }

                Log::info('Payment SUCCESS (settlement)', [
                    'order_id' => $payment->id_order,
                ]);

                return;
            }

            if ($transactionStatus === 'pending') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'pending';
                    $payment->save();
                }

                if ($order && $order->status !== 'diproses') {
                    $order->status = 'menunggu_pembayaran';
                    $order->save();
                }

                Log::info('Payment PENDING', [
                    'order_id' => $payment->id_order,
                ]);

                return;
            }

            if (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'])) {
                if ($payment->status !== 'paid') {
                    $payment->status = $transactionStatus === 'expire' ? 'expired' : 'failed';
                    $payment->save();
                }

                if ($order && $order->status !== 'diproses') {
                    $order->status = 'pembayaran_gagal';
                    $order->save();
                }

                Log::info('Payment FAILED/EXPIRED', [
                    'order_id' => $payment->id_order,
                    'status' => $transactionStatus,
                ]);

                return;
            }

            Log::info('Midtrans webhook: unhandled status', [
                'order_id' => $payment->id_order,
                'transaction_status' => $transactionStatus,
            ]);
        });

        return response()->json(['message' => 'OK']);
    }
}