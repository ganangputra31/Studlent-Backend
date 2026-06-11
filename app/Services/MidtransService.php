<?php
// app/Services/MidtransService.php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey    = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized  = true;
        Config::$is3ds        = true;

        if (!config('midtrans.is_production')) {
            Config::$curlOptions = [CURLOPT_SSL_VERIFYPEER => false];
        }
    }

    public function createTransaction($order, int $amount, ?string $paymentMethod = null): array
    {
        $midtransOrderId = 'STD-' . uniqid() . '-' . $order->id_order;

        $params = [
            'transaction_details' => [
                'order_id'     => $midtransOrderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => $order->client->nama  ?? 'Client',
                'email'      => $order->client->email ?? 'client@studlent.com',
                'phone'      => $order->client->no_hp  ?? '',
            ],
            'item_details' => [
                [
                    'id'       => 'SVC-' . $order->id_order,
                    'price'    => $amount,
                    'quantity' => 1,
                    'name'     => substr($order->detail_pesanan ?? 'Studlent Service', 0, 50),
                ],
            ],
            'callbacks' => [
                'finish'  => 'https://studlent.app/payment/finish',
                'error'   => 'https://studlent.app/payment/error',
                'pending' => 'https://studlent.app/payment/pending',
            ],
        ];

        if ($paymentMethod) {
            $enabledPayments = $this->_resolveEnabledPayments($paymentMethod);
            if (!empty($enabledPayments)) {
                $params['enabled_payments'] = $enabledPayments;
            }
        }

        // @ untuk suppress warning dari library Midtrans (array key 10023)
        // Warning ini tidak mempengaruhi hasil transaksi
        $snap = @Snap::createTransaction($params);

        if (!$snap || !isset($snap->token)) {
            Log::error('Midtrans snap null atau tidak ada token', [
                'midtrans_order_id' => $midtransOrderId,
                'amount'            => $amount,
            ]);
            throw new \Exception('Gagal mendapatkan token dari Midtrans');
        }

        return [
            'token'             => $snap->token,
            'redirect_url'      => $snap->redirect_url,
            'midtrans_order_id' => $midtransOrderId,
        ];
    }

    private function _resolveEnabledPayments(string $method): array
    {
        $map = [
            'gopay'       => ['gopay'],
            'shopeepay'   => ['shopeepay'],
            'dana'        => ['other_qris'],
            'ovo'         => ['other_qris'],
            'qris'        => ['other_qris'],
            'bri_va'      => ['bri_va'],
            'bca_va'      => ['bca_va'],
            'bni_va'      => ['bni_va'],
            'echannel'    => ['echannel'],
            'credit_card' => ['credit_card'],
        ];

        return $map[$method] ?? [];
    }
}