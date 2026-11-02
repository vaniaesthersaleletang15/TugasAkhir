<?php

use App\Http\Middleware\EnsurePrimaAvailable;
use App\Http\Middleware\ExtendPrimaSession;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Backend hanya dapat diakses lewat reverse proxy nginx di network Docker
        // (tidak ada port yang dipublish), sehingga '*' aman di sini. Tanpa ini,
        // seluruh request terlihat berasal dari IP container nginx sehingga
        // throttle:login menjadi rate limit global, dan HTTPS tidak terdeteksi.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Endpoint API harus selalu menghasilkan respons JSON 401. Tanpa
        // callback ini, klien yang tidak mengirim header Accept JSON membuat
        // middleware auth mencoba mengarahkan ke route web `login` yang memang
        // tidak tersedia pada backend API-only.
        $middleware->redirectGuestsTo(
            static fn (Request $request): ?string => $request->is('api/*') ? null : '/'
        );

        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'prima.available' => EnsurePrimaAvailable::class,
            'prima.session' => ExtendPrimaSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();
        $jsonError = static fn (string $message, int $status, mixed $data = null, array $headers = []) => response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status, $headers);

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Sesi Anda telah berakhir. Silakan masuk kembali.', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $jsonError('Anda tidak memiliki izin untuk melakukan tindakan ini.', 403);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $errors = $exception->errors();
            $message = collect($errors)->flatten()->first() ?: 'Data yang diberikan tidak valid.';

            return $jsonError($message, 422, $errors);
        });

        $exceptions->render(function (HttpException $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            $messages = [
                401 => 'Sesi Anda telah berakhir. Silakan masuk kembali.',
                403 => 'Akses ditolak.',
                404 => 'Endpoint atau data yang diminta tidak ditemukan.',
                405 => 'Metode permintaan tidak diizinkan.',
                419 => 'Sesi telah kedaluwarsa. Silakan muat ulang halaman.',
                429 => 'Terlalu banyak permintaan. Silakan coba kembali beberapa saat lagi.',
            ];

            $message = trim($exception->getMessage());
            $isDefaultEnglishMessage = preg_match(
                '/^(Unauthenticated|Unauthorized|Forbidden|Not Found|Method Not Allowed|Page Expired|Too Many Attempts)\.?$|could not be found|method is not supported for route/i',
                $message
            );
            if (isset($messages[$status]) && ($message === '' || $isDefaultEnglishMessage)) {
                $message = $messages[$status];
            }

            return $jsonError($message ?: 'Permintaan tidak dapat diproses.', $status, null, $exception->getHeaders());
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiRequest, $jsonError) {
            if (! $isApiRequest($request) || config('app.debug')) {
                return null;
            }

            return $jsonError('Terjadi kesalahan pada server. Silakan coba kembali.', 500);
        });
    })->create();
