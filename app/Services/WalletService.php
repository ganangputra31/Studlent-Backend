<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletLedger;

class WalletService
{
    public function credit($userId, $amount, $source, $refId)
    {
        $wallet = Wallet::firstOrCreate(
            ['id_user' => $userId],
            ['balance' => 0]
        );

        $wallet->balance += (int) $amount;
        $wallet->save();

        WalletLedger::create([
            'id_user'      => $userId,
            'type'         => 'credit',
            'amount'       => (int) $amount,
            'source'       => $source,
            'reference_id' => $refId,
        ]);

        return $wallet;
    }

    public function debit($userId, $amount, $source, $refId)
    {
        $wallet = Wallet::where('id_user', $userId)->first();

        if (!$wallet) {
            throw new \Exception("Wallet user #{$userId} tidak ditemukan");
        }

        if ($wallet->balance < $amount) {
            throw new \Exception("Saldo tidak cukup. Balance: {$wallet->balance}, dibutuhkan: {$amount}");
        }

        $wallet->balance -= (int) $amount;
        $wallet->save();

        WalletLedger::create([
            'id_user'      => $userId,
            'type'         => 'debit',
            'amount'       => (int) $amount,
            'source'       => $source,
            'reference_id' => $refId,
        ]);

        return $wallet;
    }
}