<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TerminalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $code): int
    {
        return (int) DB::table('integrated_terminals')->where('code', $code)->value('id');
    }

    private function user(string $role, string $username, int $terminalId): User
    {
        return User::create([
            'username' => $username,
            'full_name' => ucfirst($username),
            'email' => "$username@example.test",
            'password' => 'Secret123!',
            'role' => $role,
            'terminal_id' => $terminalId,
            'status' => 'active',
        ]);
    }

    public function test_public_terminal_list_contains_the_five_fixed_sulawesi_terminals(): void
    {
        $this->getJson('/api/v1/public/terminals')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonFragment(['code' => 'IT-BITUNG'])
            ->assertJsonFragment(['code' => 'IT-GORONTALO'])
            ->assertJsonFragment(['code' => 'IT-DONGGALA'])
            ->assertJsonFragment(['code' => 'IT-KENDARI'])
            ->assertJsonFragment(['code' => 'IT-MAKASSAR']);
    }

    public function test_transportir_registration_is_visible_only_to_spv_of_selected_terminal(): void
    {
        $bitung = $this->terminal('IT-BITUNG');
        $kendari = $this->terminal('IT-KENDARI');
        $spvBitung = $this->user('admin', 'spv_bitung', $bitung);
        $spvKendari = $this->user('admin', 'spv_kendari', $kendari);
        $transporterId = $this->createTransporter($kendari, 'PT Kendari Pengujian');
        $payload = [
            'email' => 'transportir-kendari@example.test', 'password' => 'Strong123!',
            'full_name' => 'Transportir Kendari', 'phone' => '08123', 'reason' => 'Operasional Kendari',
            'requested_role' => 'pengurus', 'portal' => 'transportir', 'terminal_id' => $kendari,
            'transporter_id' => $transporterId,
        ];
        $this->postJson('/api/v1/auth/register', $payload)->assertOk();

        $this->withToken($spvBitung->createToken('test')->plainTextToken)->getJson('/api/v1/registrations')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->app['auth']->forgetGuards();
        $this->withToken($spvKendari->createToken('test')->plainTextToken)->getJson('/api/v1/registrations')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'transportir-kendari@example.test');
    }

    public function test_spv_can_create_only_operational_accounts_for_own_terminal(): void
    {
        $bitung = $this->terminal('IT-BITUNG');
        $kendari = $this->terminal('IT-KENDARI');
        $spv = $this->user('admin', 'spv_scope', $bitung);
        $token = $spv->createToken('test')->plainTextToken;
        $payload = ['full_name' => 'Tim HSSE Bitung', 'email' => 'hsse-bitung@example.test', 'phone' => '0812', 'password' => 'Strong123!', 'role' => 'hsse', 'terminal_id' => $kendari];

        $this->withToken($token)->postJson('/api/v1/users', $payload)->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'hsse-bitung@example.test', 'role' => 'hsse', 'terminal_id' => $bitung]);
        $this->withToken($token)->postJson('/api/v1/users', array_merge($payload, ['email' => 'regional-by-spv@example.test', 'role' => 'regional_manager']))->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/users', array_merge($payload, ['email' => 'spv-by-spv@example.test', 'role' => 'admin']))->assertUnprocessable();
    }
}
