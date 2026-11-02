<?php

namespace App\Domain\System;

class SystemAvailabilityPolicy
{
    public function accountIsActive(?string $status): bool
    {
        return $status === 'active';
    }

    public function maintenanceBlocks(bool $maintenance, ?string $role): bool
    {
        return $maintenance && $role !== 'admin';
    }

    public function maintenanceMessage(mixed $message): string
    {
        $message = trim((string) $message);

        return $message !== '' ? $message : 'Sistem sedang dalam pemeliharaan.';
    }
}
