<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrated_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('city', 100)->nullable();
            $table->string('region_code', 30)->default('SULAWESI')->index();
            $table->string('region_name', 100)->default('Regional Sulawesi');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $terminalId = DB::table('integrated_terminals')->insertGetId([
            'code' => 'IT-BITUNG',
            'name' => 'Integrated Terminal Bitung',
            'city' => 'Bitung',
            'region_code' => 'SULAWESI',
            'region_name' => 'Regional Sulawesi',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('role')->constrained('integrated_terminals')->nullOnDelete();
            $table->string('region_code', 30)->default('SULAWESI')->after('terminal_id')->index();
        });
        Schema::table('user_registrations', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('requested_role')->constrained('integrated_terminals')->nullOnDelete();
        });
        Schema::table('kendaraan', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('id')->constrained('integrated_terminals')->nullOnDelete();
        });
        Schema::table('formulir_checklist', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('id')->constrained('integrated_terminals')->nullOnDelete();
        });
        Schema::table('dokumen_kendaraan', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('id')->constrained('integrated_terminals')->nullOnDelete();
        });
        Schema::table('audit_log', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('id')->constrained('integrated_terminals')->nullOnDelete();
        });

        DB::table('users')->where('role', '<>', 'regional_manager')->whereNull('terminal_id')->update(['terminal_id' => $terminalId]);
        foreach (['user_registrations', 'kendaraan', 'formulir_checklist', 'dokumen_kendaraan', 'audit_log'] as $table) {
            DB::table($table)->whereNull('terminal_id')->update(['terminal_id' => $terminalId]);
        }
    }

    public function down(): void
    {
        foreach (['audit_log', 'dokumen_kendaraan', 'formulir_checklist', 'kendaraan', 'user_registrations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('terminal_id');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terminal_id');
            $table->dropColumn('region_code');
        });
        Schema::dropIfExists('integrated_terminals');
    }
};
