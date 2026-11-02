<?php

namespace Tests\Unit;

use App\Services\TurnstileVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileVerifierTest extends TestCase
{
    public function test_disabled_verification_is_accepted_without_http_request(): void
    {
        config(['services.turnstile.enabled' => false]);
        Http::fake();

        $this->assertTrue((new TurnstileVerifier)->verify(null));
        Http::assertNothingSent();
    }

    public function test_missing_secret_or_token_is_rejected_without_http_request(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => '']);
        Http::fake();

        $this->assertFalse((new TurnstileVerifier)->verify('token'));

        config(['services.turnstile.secret_key' => 'secret']);
        $this->assertFalse((new TurnstileVerifier)->verify(''));
        Http::assertNothingSent();
    }

    public function test_successful_provider_response_is_accepted(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $this->assertTrue((new TurnstileVerifier)->verify('valid-token', '127.0.0.1'));
        Http::assertSent(fn ($request) => $request['secret'] === 'secret'
            && $request['response'] === 'valid-token'
            && $request['remoteip'] === '127.0.0.1');
    }

    public function test_provider_rejection_is_rejected(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200)]);

        $this->assertFalse((new TurnstileVerifier)->verify('invalid-token'));
    }

    public function test_unsuccessful_http_status_is_rejected(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertFalse((new TurnstileVerifier)->verify('token'));
    }

    public function test_connection_failure_is_rejected(): void
    {
        config(['services.turnstile.enabled' => true, 'services.turnstile.secret_key' => 'secret']);
        Http::fake(fn () => throw new ConnectionException('provider unavailable'));

        $this->assertFalse((new TurnstileVerifier)->verify('token'));
    }
}
