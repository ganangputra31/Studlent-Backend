<?php
// app/Services/FeeService.php
 
namespace App\Services;
 
use Carbon\Carbon;
 
class FeeService
{
    /**
     * Hitung platform fee berdasarkan lama bergabung freelancer.
     *
     * - 0–2 bulan pertama  : 5%
     * - Lebih dari 2 bulan : 8%
     *
     * @param  float            $amount      Base amount (sudah dikurangi admin fee)
     * @param  string|null      $joinedAt    Timestamp joined_at freelancer
     * @return array{fee_percent: float, platform_fee: int, freelancer_amount: int}
     */
    public function calculate(float $amount, ?string $joinedAt): array
    {
        $feePercent = 0.08; // default 8%
 
        if ($joinedAt) {
            $joined     = Carbon::parse($joinedAt);
            $monthsSince = $joined->diffInMonths(now());
 
            // 2 bulan pertama sejak bergabung → 5%
            if ($monthsSince < 2) {
                $feePercent = 0.05;
            }
        }
 
        $platformFee      = (int) round($amount * $feePercent);
        $freelancerAmount = (int) round($amount - $platformFee);
 
        return [
            'fee_percent'       => $feePercent,         // 0.05 atau 0.08
            'platform_fee'      => $platformFee,
            'freelancer_amount' => $freelancerAmount,
        ];
    }
}