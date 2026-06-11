<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Payment;

class EnsurePaymentPaid
{
    public function handle(Request $request, Closure $next)
    {
        $paymentId = $request->route('paymentId');

        $payment = Payment::find($paymentId);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment tidak ditemukan'
            ], 404);
        }

        if ($payment->status !== 'paid') {
            return response()->json([
                'message' => 'Payment belum lunas'
            ], 403);
        }

        return $next($request);
    }
}