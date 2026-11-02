<?php

namespace App\Http\Middleware;

use App\Domain\System\SystemAvailabilityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrimaAvailable
{
    public function __construct(private readonly SystemAvailabilityPolicy $availabilityPolicy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $this->availabilityPolicy->accountIsActive($user->status)) {
            return response()->json(['success' => false, 'message' => 'Akun tidak aktif.', 'data' => null], 401);
        }
        $maintenance = DB::table('system_settings')->where('setting_key', 'maintenance_mode')->value('setting_value') === '1';
        if ($this->availabilityPolicy->maintenanceBlocks($maintenance, $user->role)) {
            $message = $this->availabilityPolicy->maintenanceMessage(
                DB::table('system_settings')->where('setting_key', 'maintenance_message')->value('setting_value')
            );

            return response()->json(['success' => false, 'message' => $message, 'data' => null], 503);
        }

        return $next($request);
    }
}
