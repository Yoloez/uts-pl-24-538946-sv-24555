<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Manajemen Kost
|--------------------------------------------------------------------------
|
| a. Login dan logout memakai token (Laravel Sanctum).
|    Seluruh endpoint selain login hanya dapat diakses dengan token.
| b. Minimal satu resource dengan CRUD lengkap (Kamar / Rooms) tersimpan di basis data.
| c. Seluruh request dan response berformat JSON:
|    - 201 saat data dibuat
|    - 422 saat validasi gagal
|    - 401 saat token tidak ada / unauthenticated
|
*/

Route::get('/health', [HealthController::class, 'check']);

// =========================================================================
// 1. PUBLIC ROUTES (Tanpa Token)
// =========================================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);


// =========================================================================
// 2. PROTECTED ROUTES (Wajib Bearer Token / auth:sanctum)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Authentication ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Fitur Penghuni Kos ---
    // Memantau kamar yang saat ini ditempati & fasilitasnya
    Route::get('/my-room', [BookingController::class, 'myRoom']);
    
    // Booking kamar kos (menghasilkan status 201 Created)
    Route::post('/rooms/{id}/book', [BookingController::class, 'book']);
    
    // Checkout dari kamar kos
    Route::post('/checkout', [BookingController::class, 'checkout']);
    
    // Memantau rincian fasilitas kamar tertentu
    Route::get('/rooms/{id}/facilities', [BookingController::class, 'facilities']);

    // --- Resource CRUD Kamar (Read) ---
    // Tampil semua kamar kos
    Route::get('/rooms', [RoomController::class, 'index']);
    
    // Tampil satu detail kamar kos
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // =====================================================================
    // 3. ADMIN ONLY ROUTES (role:admin)
    // =====================================================================
    Route::middleware('role:admin')->group(function () {
        // Resource CRUD Kamar (Tambah, Ubah, Hapus)
        Route::post('/rooms', [RoomController::class, 'store']);       // Tambah kamar (201 Created)
        Route::put('/rooms/{id}', [RoomController::class, 'update']);     // Ubah kamar
        Route::delete('/rooms/{id}', [RoomController::class, 'destroy']); // Hapus kamar

        // Admin: Melihat siapa saja yang ada di kamar kos tertentu
        Route::get('/rooms/{id}/occupants', [RoomController::class, 'occupants']);

        // Admin: Menghapus user yang terdapat di kamar kos tertentu
        Route::delete('/rooms/{roomId}/occupants/{userId}', [RoomController::class, 'removeUser']);
    });
});
