<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        return Chat::create([
            'id_order' => $request->id_order,
            'sender_id' => auth()->id(),
            'pesan' => $request->pesan
        ]);
    }

    public function get($orderId)
    {
        return Chat::where('id_order', $orderId)->get();
    }
}