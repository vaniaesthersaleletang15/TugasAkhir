<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait InteractsWithPrimaApi
{
    protected function ok(string $message = 'OK', mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function fail(string $message, int $status = 422, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function requireRole(Request $request, array $roles): void
    {
        if (! in_array($request->user()?->role, $roles, true)) {
            throw new HttpException(403, 'Akses ditolak.');
        }
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return DB::table('system_settings')
            ->where('setting_key', $key)
            ->value('setting_value') ?? $default;
    }

    protected function setSetting(string $key, mixed $value): void
    {
        DB::table('system_settings')->upsert([
            [
                'setting_key' => $key,
                'setting_value' => (string) $value,
                'updated_at' => now(),
            ],
        ], ['setting_key'], ['setting_value', 'updated_at']);
    }

    protected function audit(Request $request, string $action, string $description, ?int $formId = null): void
    {
        $terminalId = $formId
            ? DB::table('formulir_checklist')->where('id', $formId)->value('terminal_id')
            : $request->user()?->terminal_id;

        DB::table('audit_log')->insert([
            'terminal_id' => $terminalId,
            'formulir_id' => $formId,
            'action' => $action,
            'user_name' => $request->user()?->username ?? 'System',
            'ip_address' => $request->ip(),
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
