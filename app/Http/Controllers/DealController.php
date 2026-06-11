<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function create(Request $request)
    {
         /** @var \App\Models\User $user */
        $user = auth()->user();

        return Deal::create([
            'id_client' => $user->id_user,
            'id_freelancer' => $request->id_freelancer,
            'price' => $request->price, // 500000
            'status' => 'pending'
        ]);
    }

    public function accept($dealId)
    {
        $deal = Deal::findOrFail($dealId);
        $deal->status = 'accepted';
        $deal->save();

        return $deal;
    }
}