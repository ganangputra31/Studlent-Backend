<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLedger extends Model
{
    protected $primaryKey = 'id_ledger';

    // ← FIX: tabel wallet_ledger di DB tidak punya kolom updated_at
    // Tanpa ini Laravel coba set updated_at → error
    const UPDATED_AT = null;

    protected $fillable = [
        'id_user',
        'type',
        'amount',
        'source',
        'reference_id',
    ];
}