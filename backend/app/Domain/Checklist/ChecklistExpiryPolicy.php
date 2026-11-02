<?php

namespace App\Domain\Checklist;

final class ChecklistExpiryPolicy
{
    private const EXPIRY_ITEMS = [
        'STNK',
        'PAJAK',
        'SIMFIT (Industri)',
        'Surat Tera Metrologi',
        'Surat Keur DLLAAJR',
    ];

    public function tracksExpiry(string $itemName): bool
    {
        return in_array($itemName, self::EXPIRY_ITEMS, true);
    }

    public function requiresExpiry(string $itemName, string $vehicleType): bool
    {
        if (! $this->tracksExpiry($itemName)) {
            return false;
        }

        return $itemName !== 'SIMFIT (Industri)' || strtoupper($vehicleType) === 'INDUSTRI';
    }

    public function validate(array $items, string $vehicleType, string $inspectionDate): ?string
    {
        foreach ($items as $item) {
            $name = (string) ($item['nama'] ?? '');
            if (! $this->requiresExpiry($name, $vehicleType)) {
                continue;
            }

            $expiryDate = trim((string) ($item['tanggal_expire'] ?? ''));
            if ($expiryDate === '') {
                return "Tanggal masa berlaku $name wajib diisi.";
            }
            if ($expiryDate < $inspectionDate) {
                return "$name sudah kedaluwarsa pada tanggal pemeriksaan.";
            }
        }

        return null;
    }
}
