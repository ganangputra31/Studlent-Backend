<?php

namespace App\Services;

use App\Models\Deal;

class DealService
{
    public function createDeal($clientId, $freelancerId, $price)
    {
        return Deal::create([
            'id_client' => $clientId,
            'id_freelancer' => $freelancerId,
            'price' => $price,
            'status' => 'pending'
        ]);
    }

    public function acceptDeal($dealId)
    {
        $deal = Deal::findOrFail($dealId);
        $deal->status = 'accepted';
        $deal->save();

        return $deal;
    }
}