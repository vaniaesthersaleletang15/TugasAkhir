<?php

namespace App\Domain\Ekim;

use Carbon\CarbonImmutable;

final class EkimStatusCalculator
{
    public function evaluate(?string $validUntil, string $referenceDate, int $warningDays = 30): array
    {
        if (blank($validUntil)) {
            return [
                'status' => 'unavailable',
                'days_remaining' => null,
            ];
        }

        $reference = CarbonImmutable::parse($referenceDate)->startOfDay();
        $expiry = CarbonImmutable::parse($validUntil)->startOfDay();
        $daysRemaining = (int) $reference->diffInDays($expiry, false);
        $warningDays = max(0, $warningDays);

        $status = match (true) {
            $daysRemaining < 0 => 'expired',
            $daysRemaining <= $warningDays => 'expiring',
            default => 'active',
        };

        return [
            'status' => $status,
            'days_remaining' => $daysRemaining,
        ];
    }
}
