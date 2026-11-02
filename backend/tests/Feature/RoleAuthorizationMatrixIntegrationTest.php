<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAuthorizationMatrixIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleEndpointProvider')]
    public function test_role_access_matrix(string $role, string $path, int $expectedStatus): void
    {
        $terminalId = $role === 'regional_manager'
            ? null
            : (int) DB::table('integrated_terminals')->where('code', 'IT-BITUNG')->value('id');
        $key = str_replace(['/', '-'], '_', trim($path, '/'));
        $user = User::create([
            'username' => "matrix_{$role}_{$key}",
            'full_name' => "Matrix $role",
            'email' => "matrix.{$role}.{$key}@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'terminal_id' => $terminalId,
            'region_code' => 'SULAWESI',
            'status' => 'active',
        ]);

        $response = $this->withToken($user->createToken('role-matrix')->plainTextToken)
            ->getJson($path);

        $response->assertStatus($expectedStatus);
    }

    public static function roleEndpointProvider(): array
    {
        $roles = ['admin', 'hsse', 'manager_hsse', 'pengurus', 'regional_manager'];
        $endpoints = [
            '/api/v1/checklists' => ['admin', 'hsse', 'manager_hsse', 'regional_manager'],
            '/api/v1/users' => ['admin'],
            '/api/v1/documents' => ['admin', 'pengurus'],
            '/api/v1/regional/terminals' => ['regional_manager'],
            '/api/v1/my-vehicles' => ['pengurus'],
        ];
        $datasets = [];
        foreach ($endpoints as $path => $allowedRoles) {
            foreach ($roles as $role) {
                $datasets["$role $path"] = [
                    $role,
                    $path,
                    in_array($role, $allowedRoles, true) ? 200 : 403,
                ];
            }
        }

        return $datasets;
    }

    public function test_protected_module_groups_reject_requests_without_token(): void
    {
        foreach ([
            '/api/v1/checklists',
            '/api/v1/users',
            '/api/v1/documents',
            '/api/v1/regional/terminals',
            '/api/v1/my-vehicles',
            '/api/v1/settings',
        ] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }
}
