<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->where('role', 'user')->update(['role' => 'hsse']);
        }

        if (Schema::hasTable('user_registrations')) {
            DB::table('user_registrations')->where('requested_role', 'user')->update(['requested_role' => 'hsse']);
        }

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'hsse'");
            DB::statement("ALTER TABLE user_registrations ALTER COLUMN requested_role SET DEFAULT 'pengurus'");
        }
    }

    public function down(): void
    {
        // Data HSSE tidak dikembalikan menjadi "user" karena keduanya tidak lagi
        // dapat dibedakan setelah konsolidasi. Rollback hanya memulihkan default lama.
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user'");
            DB::statement("ALTER TABLE user_registrations ALTER COLUMN requested_role SET DEFAULT 'user'");
        }
    }
};
