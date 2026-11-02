<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class LocalizedApiResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/test-forbidden', fn () => abort(403));
        Route::get('/api/test-server-error', fn () => throw new RuntimeException('Internal failure'));
    }

    public function test_unauthenticated_response_is_in_indonesian(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.',
                'data' => null,
            ]);
    }

    public function test_default_http_errors_are_in_indonesian(): void
    {
        $this->getJson('/api/test-forbidden')
            ->assertForbidden()
            ->assertJsonPath('message', 'Akses ditolak.');

        $this->getJson('/api/endpoint-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Endpoint atau data yang diminta tidak ditemukan.');

        $this->postJson('/api/test-forbidden')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Metode permintaan tidak diizinkan.');
    }

    public function test_validation_response_is_in_indonesian(): void
    {
        $this->getJson('/api/v1/public/transporters')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kolom Integrated Terminal wajib diisi.')
            ->assertJsonPath('data.terminal_id.0', 'Kolom Integrated Terminal wajib diisi.');
    }

    public function test_production_server_error_does_not_expose_english_detail(): void
    {
        config(['app.debug' => false]);

        $this->getJson('/api/test-server-error')
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server. Silakan coba kembali.',
                'data' => null,
            ]);
    }
}
