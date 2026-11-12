<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Endpoint untuk memeriksa kesehatan sistem (Database & Cache).
     * Mengembalikan HTTP 200 jika semua sehat, atau HTTP 503 jika ada yang bermasalah.
     */
    public function check(): JsonResponse
    {
        $status = 'healthy';
        $httpCode = 200;
        $components = [
            'database' => [
                'status' => 'healthy',
                'message' => 'Koneksi basis data MySQL berfungsi normal',
            ],
            'cache' => [
                'status' => 'healthy',
                'message' => 'Layanan cache berfungsi normal',
            ],
        ];

        // 1. Memeriksa Konektivitas Basis Data
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $httpCode = 503;
            $components['database'] = [
                'status' => 'unhealthy',
                'message' => 'Gagal menghubungi basis data MySQL: ' . $e->getMessage(),
            ];

            // Log terstruktur: Gagal menghubungi basis data
            Log::error('Gagal menghubungi basis data MySQL pada endpoint /health', [
                'who' => 'system_health_checker',
                'doing' => 'periksa_koneksi_basis_data',
                'reason' => 'Koneksi ke MySQL database gagal atau terputus: ' . $e->getMessage(),
            ]);
        }

        // 2. Memeriksa Layanan Cache
        try {
            $cacheProbeKey = 'health_probe_' . uniqid();
            Cache::put($cacheProbeKey, 'ok', 10);
            $cacheValue = Cache::get($cacheProbeKey);
            Cache::forget($cacheProbeKey);

            if ($cacheValue !== 'ok') {
                throw new \Exception('Nilai cache yang dibaca kembali tidak valid');
            }
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $httpCode = 503;
            $components['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Gagal mengakses layanan cache: ' . $e->getMessage(),
            ];

            // Log terstruktur: Gagal mengakses cache
            Log::error('Gagal mengakses cache pada endpoint /health', [
                'who' => 'system_health_checker',
                'doing' => 'periksa_layanan_cache',
                'reason' => 'Operasi read/write cache gagal: ' . $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'components' => $components,
        ], $httpCode);
    }
}

