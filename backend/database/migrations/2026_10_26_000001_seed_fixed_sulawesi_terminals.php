<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TERMINALS = [
        ['code' => 'IT-BITUNG', 'name' => 'Integrated Terminal (IT) Bitung', 'city' => 'Bitung'],
        ['code' => 'IT-GORONTALO', 'name' => 'Integrated Terminal (IT) Gorontalo', 'city' => 'Gorontalo'],
        ['code' => 'IT-DONGGALA', 'name' => 'Integrated Terminal (IT) Donggala', 'city' => 'Donggala'],
        ['code' => 'IT-KENDARI', 'name' => 'Integrated Terminal (IT) Kendari', 'city' => 'Kendari'],
        ['code' => 'IT-MAKASSAR', 'name' => 'Integrated Terminal (IT) Makassar', 'city' => 'Makassar'],
    ];

    public function up(): void
    {
        foreach (self::TERMINALS as $terminal) {
            DB::table('integrated_terminals')->updateOrInsert(
                ['code' => $terminal['code']],
                $terminal + [
                    'region_code' => 'SULAWESI',
                    'region_name' => 'Regional Sulawesi',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('integrated_terminals')
            ->where('region_code', 'SULAWESI')
            ->whereNotIn('code', array_column(self::TERMINALS, 'code'))
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('integrated_terminals')
            ->whereIn('code', ['IT-GORONTALO', 'IT-DONGGALA', 'IT-KENDARI', 'IT-MAKASSAR'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.terminal_id', 'integrated_terminals.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('kendaraan')->whereColumn('kendaraan.terminal_id', 'integrated_terminals.id'))
            ->delete();
    }
};
