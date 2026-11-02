<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kendaraan', 'email_kontraktor') && ! Schema::hasColumn('kendaraan', 'email_transportir')) {
            Schema::table('kendaraan', function ($table) {
                $table->renameColumn('email_kontraktor', 'email_transportir');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kendaraan', 'email_transportir') && ! Schema::hasColumn('kendaraan', 'email_kontraktor')) {
            Schema::table('kendaraan', function ($table) {
                $table->renameColumn('email_transportir', 'email_kontraktor');
            });
        }
    }
};
