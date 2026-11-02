<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class VehicleHistoryService
{
    public function record(
        ?object $before,
        ?object $after,
        object $actor,
        string $action,
        ?string $notes,
        ?string $ipAddress,
    ): void {
        $vehicle = $after ?? $before;
        if (! $vehicle?->terminal_id) {
            return;
        }
        DB::table('vehicle_histories')->insert([
            'vehicle_id' => $vehicle->id ?? null,
            'terminal_id' => $vehicle->terminal_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'before_data' => $before ? json_encode((array) $before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after_data' => $after ? json_encode((array) $after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'notes' => $notes,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
