<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\InteractsWithPrimaApi;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InteractsWithPrimaApiTest extends TestCase
{
    public function test_ok_response_has_consistent_contract(): void
    {
        $response = $this->subject()->success('Tersimpan', ['id' => 7], 201);

        $this->assertSame(201, $response->status());
        $this->assertSame(['success' => true, 'message' => 'Tersimpan', 'data' => ['id' => 7]], $response->getData(true));
    }

    public function test_fail_response_has_consistent_contract(): void
    {
        $response = $this->subject()->failure('Tidak valid', 422, ['field' => 'email']);

        $this->assertSame(422, $response->status());
        $this->assertSame(['success' => false, 'message' => 'Tidak valid', 'data' => ['field' => 'email']], $response->getData(true));
    }

    public function test_allowed_role_passes_authorization(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => (object) ['role' => 'hsse']);

        $this->subject()->authorize($request, ['hsse', 'admin']);
        $this->addToAssertionCount(1);
    }

    public function test_missing_or_disallowed_role_is_forbidden(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => (object) ['role' => 'transportir']);

        try {
            $this->subject()->authorize($request, ['hsse']);
            $this->fail('HttpException seharusnya dilempar.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('Akses ditolak.', $exception->getMessage());
        }
    }

    private function subject(): object
    {
        return new class
        {
            use InteractsWithPrimaApi;

            public function success(string $message, mixed $data, int $status)
            {
                return $this->ok($message, $data, $status);
            }

            public function failure(string $message, int $status, mixed $data)
            {
                return $this->fail($message, $status, $data);
            }

            public function authorize(Request $request, array $roles): void
            {
                $this->requireRole($request, $roles);
            }
        };
    }
}
