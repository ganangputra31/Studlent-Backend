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
            $order = Order::create([
                'id_client'      => $request->client_id,
                'id_freelancer'  => $request->freelancer_id,
                'id_service'     => $request->service_id,
                'id_package'     => $request->package_id,
                'detail_pesanan' => $request->service_name . ' - ' . $request->package_name . ' Package',
                'catatan'        => $request->catatan,
                'deadline'       => $request->deadline,
                'status'         => 'diproses',
                'progress'       => 0,
            ]);

            Log::info('Request data', $request->all());

            $feePercent = 10.0;
            $platformFee = $request->package_price * ($feePercent / 100);
            $freelancerGet = $request->package_price - $platformFee;

            $payment = Payment::create([
                'id_order'            => $order->id_order,
                'metode'              => $request->payment_method,
                'amount'              => $request->amount,
                'admin_fee'           => $request->admin_fee,
                'status'              => 'pending',
                'escrow_status'       => 'hold',
                'fee_percent'         => 10.0,
                'platform_fee'        => $platformFee,
                'freelancer_receive'  => $freelancerGet,
            ]);

            Escrow::create([
                'id_payment'         => $payment->id_payment,
                'amount'             => $request->amount,
                'platform_fee'       => $platformFee,
                'freelancer_amount'  => $freelancerGet,
                'status'             => 'hold',
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

            $payment->gateway_trx_id = $snap['token'];
            $payment->midtrans_order_id = $snap['midtrans_order_id'];
            $payment->payment_url = $snap['redirect_url'];
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

    public function handleNotification(Request $request)
    {
        DB::beginTransaction();

        try {
            $payload = $request->all();

            Log::info('Midtrans notification received', $payload);

            $orderId = (string) ($payload['order_id'] ?? '');
            $statusCode = (string) ($payload['status_code'] ?? '');
            $grossAmount = (string) ($payload['gross_amount'] ?? '');
            $signatureKey = (string) ($payload['signature_key'] ?? '');
            $transactionStatus = strtolower((string) ($payload['transaction_status'] ?? ''));
            $fraudStatus = strtolower((string) ($payload['fraud_status'] ?? ''));
            $paymentType = $payload['payment_type'] ?? null;
            $transactionId = $payload['transaction_id'] ?? null;

            if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
                DB::rollBack();
                return response()->json(['message' => 'Payload not valid'], 400);
            }

            $serverKey = config('midtrans.server_key');
            $localSignature = hash(
                'sha512',
                $orderId . $statusCode . $grossAmount . $serverKey
            );

            if ($signatureKey !== $localSignature) {
                Log::warning('Invalid Midtrans signature', [
                    'order_id' => $orderId,
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

            if (!empty($paymentType)) {
                $payment->metode = $paymentType;
            }

            if (!empty($transactionId)) {
                $payment->gateway_trx_id = $transactionId;
            }

            if ($transactionStatus === 'capture') {
                if ($fraudStatus === 'challenge') {
                    $payment->status = 'challenge';
                    $payment->save();

                    DB::commit();

                    return response()->json([
                        'message' => 'Payment challenge handled',
                        'order_id' => $order->id_order,
                        'payment_status' => $payment->status,
                        'order_status' => $order->status,
                    ], 200);
                }

                $payment->status = 'paid';
                $payment->escrow_status = 'hold';
                $payment->tanggal_bayar = now();
                $payment->save();

                Escrow::updateOrCreate(
                    ['id_payment' => $payment->id_payment],
                    [
                        'amount' => $payment->amount,
                        'platform_fee' => $payment->platform_fee ?? 0,
                        'freelancer_amount' => $payment->freelancer_receive ?? 0,
                        'status' => 'hold',
                    ]
                );

                if ($order->status !== 'selesai') {
                    $order->status = 'diproses';
                    $order->save();
                }

                DB::commit();

                return response()->json([
                    'message' => 'Payment capture success handled',
                    'order_id' => $order->id_order,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                ], 200);
            }

            if ($transactionStatus === 'settlement') {
                $payment->status = 'paid';
                $payment->escrow_status = 'hold';
                $payment->tanggal_bayar = now();
                $payment->save();

                Escrow::updateOrCreate(
                    ['id_payment' => $payment->id_payment],
                    [
                        'amount' => $payment->amount,
                        'platform_fee' => $payment->platform_fee ?? 0,
                        'freelancer_amount' => $payment->freelancer_receive ?? 0,
                        'status' => 'hold',
                    ]
                );

                if ($order->status !== 'selesai') {
                    $order->status = 'diproses';
                    $order->save();
                }

                DB::commit();

                return response()->json([
                    'message' => 'Payment settlement success handled',
                    'order_id' => $order->id_order,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                ], 200);
            }

            if ($transactionStatus === 'pending') {
                if ($payment->status !== 'paid') {
                    $payment->status = 'pending';
                    $payment->save();
                }

                DB::commit();

                return response()->json([
                    'message' => 'Payment pending handled',
                    'order_id' => $order->id_order,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                ], 200);
            }

            if (in_array($transactionStatus, ['cancel', 'deny', 'expire', 'failure'])) {
                if ($payment->status !== 'paid') {
                    $payment->status = $transactionStatus === 'expire' ? 'expired' : 'failed';
                    $payment->save();
                }

                DB::commit();

                return response()->json([
                    'message' => 'Payment failed/expired handled',
                    'order_id' => $order->id_order,
                    'payment_status' => $payment->status,
                    'order_status' => $order->status,
                ], 200);
            }

            DB::commit();

            return response()->json([
                'message' => 'Notification received but status not handled explicitly',
                'transaction_status' => $transactionStatus,
                'order_id' => $order->id_order,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Midtrans notification error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Internal server error'
            ], 500);
        }
    }

    public function completeOrder(Request $request, FeeService $feeService, WalletService $walletService)
    {
        $request->validate([
            'id_order' => 'required|integer|exists:orders,id_order',
        ]);

        $order = Order::with('payment')->findOrFail($request->id_order);
        $payment = $order->payment;

        if (!$payment || $payment->status !== 'paid') {
            return response()->json(['message' => 'Payment belum lunas'], 400);
        }

        if ($order->status === 'selesai') {
            return response()->json(['message' => 'Order sudah selesai'], 400);
        }

        DB::transaction(function () use ($order, $payment, $feeService, $walletService) {
            $freelancer = User::findOrFail($order->id_freelancer);
            $baseAmount = $payment->amount - ($payment->admin_fee ?? 2500);
            $fee = $feeService->calculate($baseAmount, $freelancer->joined_at);

            $escrow = Escrow::where('id_payment', $payment->id_payment)->first();
            if ($escrow) {
                $escrow->platform_fee = $fee['platform_fee'];
                $escrow->freelancer_amount = $fee['freelancer_amount'];
                $escrow->status = 'released';
                $escrow->released_at = now();
                $escrow->save();
            }

            $walletService->credit(
                $order->id_freelancer,
                $fee['freelancer_amount'],
                'order_complete',
                $order->id_order
            );

            $order->status = 'selesai';
            $order->save();

            $payment->escrow_status = 'released';
            $payment->fee_percent = $fee['fee_percent'] * 100;
            $payment->platform_fee = $fee['platform_fee'];
            $payment->freelancer_receive = $fee['freelancer_amount'];
            $payment->save();
        });

        return response()->json([
            'message' => 'Order selesai, dana berhasil dicairkan ke freelancer',
            'freelancer_receive' => $payment->fresh()->freelancer_receive,
            'platform_fee' => $payment->fresh()->platform_fee,
        ]);
    }

    public function getStatus($id)
    {
        $order = Order::with('payment')
            ->where('id_order', $id)
            ->firstOrFail();

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