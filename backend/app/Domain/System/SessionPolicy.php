<?php

namespace App\Domain\System;

class SessionPolicy
{
    public function timeoutMinutes(mixed $value): int
    {
        return max(15, min(480, (int) ($value ?? 60)));
    }
}
