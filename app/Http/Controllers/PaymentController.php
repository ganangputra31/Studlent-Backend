<?php

namespace App\Http\Controllers;

use App\Models\Escrow;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\FeeService;
use App\Services\MidtransService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Buat order + payment + escrow, lalu redirect ke Midtrans
    // ─────────────────────────────────────────────────────────
    public function initiatePayment(Request $request, MidtransService $midtrans)
    {
        $request->validate([
            'client_id'       => 'required|integer',
            'freelancer_id'   => 'required|integer',
            'service_id'      => 'required|integer',
            'package_id'      => 'nullable|integer',
            'service_name'    => 'required|string',
            'package_name'    => 'required|string',
            'catatan'         => 'nullable|string',
            'deadline'        => 'required|date',
            'amount'          => 'required|integer|min:1000',
            'admin_fee'       => 'required|integer',
            'package_price'   => 'required|integer',
            'payment_method'  => 'nullable|string',
            'customer'        => 'required|array',
            'customer.name'   => 'required|string',
            'customer.email'  => 'required|email',
            'customer.phone'  => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Status awal menunggu_pembayaran (bukan diproses)
            // karena belum tentu user langsung bayar
            $order = Order::create([
                'id_client'      => $request->client_id,
                'id_freelancer'  => $request->freelancer_id,
                'id_service'     => $request->service_id,
                'id_package'     => $request->package_id,
                'detail_pesanan' => $request->service_name . ' - ' . $request->package_name . ' Package',
                'catatan'        => $request->catatan,
                'deadline'       => $request->deadline,
                'status'         => 'menunggu_pembayaran',
                'progress'       => 0,
            ]);

            // fee_percent, platform_fee, freelancer_receive dibiarkan null
            // karena baru dihitung saat order complete (completeOrder)
            $payment = Payment::create([
                'id_order'           => $order->id_order,
                'metode'             => $request->payment_method,
                'amount'             => $request->amount,
                'admin_fee'          => $request->admin_fee,
                'status'             => 'pending',
                'escrow_status'      => 'hold',
                'fee_percent'        => null,
                'platform_fee'       => null,
                'freelancer_receive' => null,
            ]);

            // Escrow awal: simpan harga service (package_price),
            // fee dan freelancer_amount = 0 dulu, diupdate saat complete
            Escrow::create([
                'id_payment'        => $payment->id_payment,
                'amount'            => (float) $request->package_price,
                'platform_fee'      => 0,
                'freelancer_amount' => 0,
                'status'            => 'hold',
            ]);

            $order->setRelation('client', (object) [
                'nama'  => $request->customer['name'],
                'email' => $request->customer['email'],
                'no_hp' => $request->customer['phone'] ?? '',
            ]);

            $snap = $midtrans->createTransaction(
                $order,
                (int) $request->amount,
                $request->payment_method
            );

            $payment->gateway_trx_id    = $snap['token'];
            $payment->midtrans_order_id = $snap['midtrans_order_id'];
            $payment->payment_url       = $snap['redirect_url'];
            $payment->save();

            DB::commit();

            return response()->json([
                'order_id'          => $order->id_order,
                'payment_id'        => $payment->id_payment,
                'payment_url'       => $snap['redirect_url'],
                'snap_token'        => $snap['token'],
                'midtrans_order_id' => $snap['midtrans_order_id'],
                'amount'            => $payment->amount,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('initiatePayment error', [
                'message' => $e->getMessage(),
                'client'  => $request->client_id,
            ]);

            return response()->json([
                'message' => 'Gagal membuat transaksi: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Dipanggil Flutter setelah polling detect order = diproses
    // Khusus sandbox — karena webhook tidak bisa hit localhost
    // Update: payments.status=paid, order=diproses,
    //         total_spent client += harga service
    // ─────────────────────────────────────────────────────────
    public function confirmPaid(Request $request)
    {
        $request->validate([
            'id_order' => 'required|integer|exists:orders,id_order',
        ]);

        $order   = Order::with('payment')->findOrFail($request->id_order);
        $payment = $order->payment;

        if (!$payment) {
            return response()->json(['message' => 'Data payment tidak ditemukan'], 404);
        }

        // Idempotent — jika sudah paid, langsung return sukses
        if ($payment->status === 'paid') {
            return response()->json([
                'message'      => 'Payment sudah dikonfirmasi sebelumnya',
                'order_status' => $order->status,
            ]);
        }

        DB::transaction(function () use ($order, $payment) {
            $payment->status        = 'paid';
            $payment->escrow_status = 'hold';
            $payment->tanggal_bayar = now();
            $payment->save();

            $order->status = 'diproses';
            $order->save();

            Escrow::where('id_payment', $payment->id_payment)
                ->update(['status' => 'hold', 'updated_at' => now()]);

            // Total spent: harga service saja tanpa admin_fee
            $servicePrice = $payment->amount - ($payment->admin_fee ?? 2500);

            DB::table('users')
                ->where('id_user', $order->id_client)
                ->increment('total_spent', $servicePrice, ['updated_at' => now()]);

            Log::info('confirmPaid: payment confirmed', [
                'order_id'      => $order->id_order,
                'client_id'     => $order->id_client,
                'service_price' => $servicePrice,
            ]);
        });

        return response()->json([
            'message'      => 'Payment dikonfirmasi, order diproses',
            'order_status' => 'diproses',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Webhook Midtrans — untuk production nanti
    // Di sandbox tidak dipanggil jika pakai localhost
    // ─────────────────────────────────────────────────────────
    public function handleNotification(Request $request)
    {
        DB::beginTransaction();

        try {
            $payload           = $request->all();
            $orderId           = (string) ($payload['order_id'] ?? '');
            $statusCode        = (string) ($payload['status_code'] ?? '');
            $grossAmount       = (string) ($payload['gross_amount'] ?? '');
            $signatureKey      = (string) ($payload['signature_key'] ?? '');
            $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
            $fraudStatus       = strtolower((string) ($payload['fraud_status'] ?? ''));
            $paymentType       = $payload['payment_type'] ?? null;
            $transactionId     = $payload['transaction_id'] ?? null;

            if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
                DB::rollBack();
                return response()->json(['message' => 'Payload not valid'], 400);
            }

            $localSignature = hash('sha512',
                $orderId . $statusCode . $grossAmount . config('midtrans.server_key')
            );

            if ($signatureKey !== $localSignature) {
                Log::warning('Invalid Midtrans signature', [
                    'order_id'           => $orderId,
                    'received_signature' => $signatureKey,
                    'expected_signature' => $localSignature,
                ]);
                DB::rollBack();
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            $payment = Payment::where('midtrans_order_id', $orderId)->first();
            if (!$payment) {
                DB::rollBack();
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $order = Order::where('id_order', $payment->id_order)->first();
            if (!$order) {
                DB::rollBack();
                return response()->json(['message' => 'Order not found'], 404);
            }

            if (!empty($paymentType))   $payment->metode         = $paymentType;
            if (!empty($transactionId)) $payment->gateway_trx_id = $transactionId;

            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                if ($transactionStatus === 'capture' && $fraudStatus === 'challenge') {
                    $payment->status = 'challenge';
                    $payment->save();
                    DB::commit();
                    return response()->json(['message' => 'Payment challenge handled']);
                }

                // Idempotent — jika sudah paid skip
                if ($payment->status === 'paid') {
                    DB::commit();
                    return response()->json(['message' => 'Already paid']);
                }

                $payment->status        = 'paid';
                $payment->escrow_status = 'hold';
                $payment->tanggal_bayar = now();
                $payment->save();

                Escrow::where('id_payment', $payment->id_payment)
                    ->update(['status' => 'hold', 'updated_at' => now()]);

                if ($order->status !== 'selesai') {
                    $order->status = 'diproses';
                    $order->save();
                }

                // Update total_spent via webhook (aktif saat production)
                $servicePrice = $payment->amount - ($payment->admin_fee ?? 2500);
                DB::table('users')
                    ->where('id_user', $order->id_client)
                    ->increment('total_spent', $servicePrice, ['updated_at' => now()]);

                DB::commit();
                return response()->json(['message' => 'Payment success handled']);
            }

            if ($transactionStatus === 'pending') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'pending';
                    $payment->save();
                    if ($order->status !== 'diproses') {
                        $order->status = 'menunggu_pembayaran';
                        $order->save();
                    }
                }
                DB::commit();
                return response()->json(['message' => 'Payment pending handled']);
            }

            if (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'])) {
                if ($payment->status !== 'paid') {
                    $payment->status = $transactionStatus === 'expire' ? 'expired' : 'failed';
                    $payment->save();
                    if ($order->status !== 'diproses') {
                        $order->status = 'pembayaran_gagal';
                        $order->save();
                    }
                }
                DB::commit();
                return response()->json(['message' => 'Payment failed/expired handled']);
            }

            DB::commit();
            return response()->json([
                'message'            => 'Notification received but status not handled explicitly',
                'transaction_status' => $transactionStatus,
                'order_id'           => $order->id_order,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('handleNotification error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Dipanggil Flutter saat client submit rating
    // Release escrow → hitung fee → update total_earned freelancer
    // ─────────────────────────────────────────────────────────
    public function completeOrder(Request $request, FeeService $feeService, WalletService $walletService)
    {
        $request->validate([
            'id_order' => 'required|integer|exists:orders,id_order',
        ]);

        $order   = Order::with('payment')->findOrFail($request->id_order);
        $payment = $order->payment;

        if (!$payment) {
            return response()->json(['message' => 'Data payment tidak ditemukan'], 404);
        }

        if ($payment->status !== 'paid') {
            return response()->json(['message' => 'Payment belum lunas'], 400);
        }

        if ($order->status === 'selesai') {
            return response()->json(['message' => 'Order sudah selesai'], 400);
        }

        DB::transaction(function () use ($order, $payment, $feeService, $walletService) {
            // Ambil freelancer untuk cek joined_at
            $freelancer = User::findOrFail($order->id_freelancer);

            // Base = harga service saja (amount dikurangi admin_fee)
            $baseAmount = $payment->amount - ($payment->admin_fee ?? 2500);

            // Hitung fee: 5% jika < 2 bulan, 8% jika >= 2 bulan
            $fee = $feeService->calculate($baseAmount, $freelancer->joined_at);

            Log::info('completeOrder', [
                'order_id'          => $order->id_order,
                'freelancer_id'     => $order->id_freelancer,
                'base_amount'       => $baseAmount,
                'fee_percent'       => ($fee['fee_percent'] * 100) . '%',
                'platform_fee'      => $fee['platform_fee'],
                'freelancer_amount' => $fee['freelancer_amount'],
            ]);

            // Update escrow
            Escrow::where('id_payment', $payment->id_payment)->update([
                'platform_fee'      => $fee['platform_fee'],
                'freelancer_amount' => $fee['freelancer_amount'],
                'status'            => 'released',
                'released_at'       => now(),
                'updated_at'        => now(),
            ]);

            // Update payment
            $payment->escrow_status      = 'released';
            $payment->fee_percent        = $fee['fee_percent'] * 100; // 5.0 atau 8.0
            $payment->platform_fee       = $fee['platform_fee'];
            $payment->freelancer_receive = $fee['freelancer_amount'];
            $payment->save();

            // Kredit wallet freelancer (balance + ledger)
            $walletService->credit(
                $order->id_freelancer,
                $fee['freelancer_amount'],
                'order_complete',
                $order->id_order
            );

            // Update total_earned — HANYA freelancer pemilik order ini
            DB::table('freelancer_profiles')
                ->where('id_user', $order->id_freelancer)
                ->increment('total_earned', $fee['freelancer_amount'], ['updated_at' => now()]);

            // Mark order selesai
            $order->status = 'selesai';
            $order->save();
        });

        $freshPayment = $payment->fresh();

        return response()->json([
            'message'            => 'Order selesai, dana berhasil dicairkan',
            'freelancer_receive' => $freshPayment->freelancer_receive,
            'platform_fee'       => $freshPayment->platform_fee,
            'fee_percent'        => $freshPayment->fee_percent,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Polling status dari Flutter
    // ─────────────────────────────────────────────────────────
    public function getStatus($id)
    {
        $order = Order::with('payment')->where('id_order', $id)->firstOrFail();

        return response()->json([
            'order_id'       => $order->id_order,
            'status'         => $order->status,
            'payment_status' => $order->payment?->status ?? 'pending',
            'is_paid'        => $order->payment?->status === 'paid',
            'payment_method' => $order->payment?->metode ?? '-',
            'service_name'   => $order->detail_pesanan ?? '-',
            'admin_fee'      => $order->payment?->admin_fee ?? 2500,
            'created_at'     => $order->payment?->created_at?->toIso8601String(),
        ]);
    }
}