<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleConsolidationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_user_accounts_are_consolidated_into_hsse_without_deletion(): void
    {
        $id = DB::table('users')->insertGetId([
            'username' => 'legacy_role_account',
            'full_name' => 'Legacy Role Account',
            'email' => 'legacy-role@example.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'user',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_02_000000_consolidate_user_role_into_hsse.php');
        $migration->up();

        $this->assertDatabaseHas('users', [
            'id' => $id,
            'role' => 'hsse',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('users', 1);
    }
}
