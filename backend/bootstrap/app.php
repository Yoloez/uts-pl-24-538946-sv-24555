<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::get('/health', [\App\Http\Controllers\Api\HealthController::class, 'check']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Token tidak ditemukan atau tidak valid. Silakan login terlebih dahulu.',
                ], 401);
            }
        });

        // 1. Log Terstruktur: Validasi Gagal (Tingkat: WARNING)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            \Illuminate\Support\Facades\Log::warning('Validasi permintaan gagal', [
                'who' => $request->user() ? [
                    'user_id' => $request->user()->id,
                    'role' => $request->user()->role,
                ] : 'guest',
                'doing' => 'validasi_input_permintaan',
                'reason' => 'Data input tidak memenuhi aturan validasi yang ditentukan',
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'failed_fields' => array_keys($e->errors()),
            ]);

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validasi data gagal.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 2. Log Terstruktur: Gagal Menghubungi Basis Data (Tingkat: ERROR)
        $exceptions->render(function (\PDOException $e, $request) {
            \Illuminate\Support\Facades\Log::error('Gagal menghubungi basis data MySQL', [
                'who' => $request->user() ? [
                    'user_id' => $request->user()->id,
                    'role' => $request->user()->role,
                ] : 'guest_or_system',
                'doing' => 'eksekusi_query_basis_data',
                'reason' => 'Koneksi ke database MySQL terputus atau timeout: ' . $e->getMessage(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Terjadi gangguan saat menghubungi basis data.',
                ], 500);
            }
        });
    })->create();
