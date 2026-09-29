<?php

use App\Exceptions\ProcessException;
use App\Http\Middleware\EnsureAdminAccountIsActive;
use App\Http\Middleware\EnsureMemberAccountIsActive;
use App\Http\Middleware\NormalizeDataTableQuery;
use App\Http\Middleware\UseUnescapedJson;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', NormalizeDataTableQuery::class);
        $middleware->appendToGroup('api', UseUnescapedJson::class);

        $middleware->alias([
            'admin.active' => EnsureAdminAccountIsActive::class,
            'member.active' => EnsureMemberAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $exception->errors();
            $firstMessage = collect($errors)->flatten()->first();

            return response()->json([
                'message' => $firstMessage ?: 'Data yang diberikan tidak valid.',
                'error_code' => 'validation',
                'errors' => $errors,
            ], $exception->status);
        });

        $exceptions->render(function (ProcessException $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'process_error',
            ], $exception->httpStatus());
        });

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $message = match ($status) {
                400 => 'Permintaan tidak valid.',
                401 => 'Silakan masuk untuk melanjutkan.',
                403 => 'Anda tidak memiliki akses untuk melakukan proses ini.',
                404 => 'Data yang diminta tidak ditemukan.',
                405 => 'Metode permintaan tidak diizinkan.',
                409 => 'Data mengalami konflik dengan kondisi saat ini.',
                419 => 'Sesi telah berakhir. Silakan masuk kembali.',
                429 => 'Terlalu banyak permintaan. Silakan coba kembali nanti.',
                503 => 'Layanan sedang tidak tersedia. Silakan coba kembali nanti.',
                default => $status >= 500
                    ? 'Terjadi kesalahan pada server.'
                    : 'Proses tidak dapat dilakukan.',
            };

            return response()->json([
                'message' => $message,
                'error_code' => 'process_error',
            ], $status);
        });
    })->create();
