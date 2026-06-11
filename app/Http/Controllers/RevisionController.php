<?php

namespace App\Http\Controllers;

use App\Models\Revision;

class RevisionController extends Controller
{
    public function request()
    {
        $count = Revision::where('id_order', request('id_order'))->count();

        if ($count >= 3) {
            return response()->json(['message' => 'Max revisi 3 kali'], 400);
        }

        return Revision::create([
            'id_order' => request('id_order'),
            'pesan' => request('pesan'),
            'status' => 'pending'
        ]);
    }
}