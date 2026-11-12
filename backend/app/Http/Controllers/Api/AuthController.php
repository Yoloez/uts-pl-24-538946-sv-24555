<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Login user and issue Bearer token.
     * Public endpoint.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            // Log terstruktur: Autentikasi kredensial login gagal (Tingkat: WARNING)
            Log::warning('Autentikasi login gagal: kredensial salah', [
                'who' => 'guest',
                'doing' => 'verifikasi_kredensial_login',
                'reason' => 'Kombinasi email atau password yang dimasukkan salah / tidak terdaftar',
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'room_id' => $user->room_id,
            ],
        ], 200);
    }

    /**
     * Register a new user (penghuni / admin).
     * Public endpoint.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|string|in:admin,penghuni',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'penghuni',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'room_id' => $user->room_id,
            ],
        ], 201);
    }

    /**
     * Logout user and revoke token.
     * Protected endpoint.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil',
        ], 200);
    }

    /**
     * Get authenticated user profile.
     * Protected endpoint.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('room');

        return response()->json([
            'user' => $user,
        ], 200);
    }
}
