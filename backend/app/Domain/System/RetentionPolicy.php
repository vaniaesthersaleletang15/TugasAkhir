<?php

namespace App\Domain\System;

final class RetentionPolicy
{
    public function auditDays(mixed $value): int
    {
        return max(90, min(3650, (int) $value));
    }

    public function backupDays(mixed $value): int
    {
        return max(7, min(365, (int) $value));
    }
}
