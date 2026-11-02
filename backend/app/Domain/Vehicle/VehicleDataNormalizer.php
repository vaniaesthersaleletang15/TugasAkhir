<?php

namespace App\Domain\Vehicle;

final class VehicleDataNormalizer
{
    public function plate(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    public function email(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    public function transportirEmail(?string $emailTransportir, ?string $legacyEmail = null): string
    {
        return $this->email($emailTransportir ?? $legacyEmail);
    }
}
