<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function createTransporter(int $terminalId, string $name = 'PT Transportir Pengujian'): int
    {
        return DB::table('transporters')->insertGetId([
            'terminal_id' => $terminalId,
            'code' => 'TR-'.substr(hash('sha256', $terminalId.$name.microtime(true)), 0, 12),
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function linkTransporter(User|int $user, int $transporterId, ?int $approvedBy = null): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        DB::table('transporter_user')->insertOrIgnore([
            'transporter_id' => $transporterId,
            'user_id' => $userId,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
