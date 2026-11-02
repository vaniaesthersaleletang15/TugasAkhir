<?php

namespace Tests\Unit;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_added_to_response(): void
    {
        $response = $this->handle('/dashboard');

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_api_response_disables_cache(): void
    {
        $this->assertSame('no-store, private', $this->handle('/api/v1/checklists')->headers->get('Cache-Control'));
    }

    public function test_non_api_response_keeps_default_cache_header(): void
    {
        $this->assertSame('no-cache, private', $this->handle('/dashboard')->headers->get('Cache-Control'));
    }

    private function handle(string $path): Response
    {
        return (new SecurityHeaders)->handle(
            Request::create($path),
            fn () => new Response('OK')
        );
    }
}
