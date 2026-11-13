<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    /**
     * Display a listing of all rooms.
     * Accessible by authenticated users (Admin & Penghuni).
     */
    public function index(): JsonResponse
    {
        $rooms = Room::withCount('occupants')
            ->with(['occupants:id,name,email,room_id'])
            ->get();

        return response()->json([
            'message' => 'Daftar kamar berhasil dimuat',
            'data' => $rooms,
        ], 200);
    }

    /**
     * Display a specific room.
     * Accessible by authenticated users.
     */
    public function show(string|int $id): JsonResponse
    {
        $room = Room::withCount('occupants')
            ->with(['occupants:id,name,email,room_id'])
            ->find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail kamar berhasil dimuat',
            'data' => $room,
        ], 200);
    }

    /**
     * Store a newly created room in storage.
     * Admin only.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => 'required|string|max:50|unique:rooms,room_number',
            'type' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'facilities' => 'required|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:available,full,maintenance',
        ]);

        $room = Room::create([
            'room_number' => $validated['room_number'],
            'type' => $validated['type'],
            'price' => $validated['price'],
            'capacity' => $validated['capacity'],
            'facilities' => $validated['facilities'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'available',
        ]);

        // Log terstruktur: Menyimpan data kamar baru (Tingkat: INFO)
        Log::info('Berhasil menyimpan data kamar baru ke basis data', [
            'who' => [
                'user_id' => $request->user()?->id,
                'role' => $request->user()?->role,
            ],
            'doing' => 'simpan_data_kamar',
            'reason' => 'Admin menambahkan unit kamar baru ke sistem',
            'room_id' => $room->id,
            'room_number' => $room->room_number,
        ]);

        return response()->json([
            'message' => 'Kamar berhasil ditambahkan',
            'data' => $room,
        ], 201);
    }

    /**
     * Update the specified room in storage.
     * Admin only.
     */
    public function update(Request $request, string|int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'room_number' => 'sometimes|required|string|max:50|unique:rooms,room_number,' . $id,
            'type' => 'sometimes|required|string|max:50',
            'price' => 'sometimes|required|numeric|min:0',
            'capacity' => 'sometimes|required|integer|min:1',
            'facilities' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:available,full,maintenance',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Kamar berhasil diperbarui',
            'data' => $room,
        ], 200);
    }

    /**
     * Remove the specified room from storage.
     * Admin only.
     */
    public function destroy(string|int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        // Unassign any current occupants
        User::where('room_id', $room->id)->update(['room_id' => null]);

        $room->delete();

        return response()->json([
            'message' => 'Kamar berhasil dihapus',
        ], 200);
    }

    /**
     * Admin: Melihat siapa saja yang ada di kamar kos tertentu.
     */
    public function occupants(string|int $id): JsonResponse
    {
        $room = Room::with(['occupants:id,name,email,role,room_id'])->find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Daftar penghuni kamar berhasil dimuat',
            'room' => [
                'id' => $room->id,
                'room_number' => $room->room_number,
                'type' => $room->type,
                'capacity' => $room->capacity,
                'total_occupants' => $room->occupants->count(),
            ],
            'occupants' => $room->occupants,
        ], 200);
    }

    /**
     * Admin: Menghapus user yang terdapat di kamar kos tertentu.
     */
    public function removeUser(string|int $roomId, string|int $userId): JsonResponse
    {
        $room = Room::find($roomId);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        $user = User::where('id', $userId)->where('room_id', $roomId)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Penghuni tidak ditemukan di kamar ini',
            ], 404);
        }

        // Release user from room
        $user->room_id = null;
        $user->save();

        // Update active booking status
        Booking::where('user_id', $user->id)
            ->where('room_id', $roomId)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'check_out_date' => now(),
                'notes' => 'Dikeluarkan dari kamar oleh admin',
            ]);

        // If room status was full, check capacity and update to available
        $currentOccupants = $room->occupants()->count();
        if ($room->status === 'full' && $currentOccupants < $room->capacity) {
            $room->update(['status' => 'available']);
        }

        return response()->json([
            'message' => "Penghuni {$user->name} berhasil dikeluarkan dari kamar {$room->room_number}",
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'room_id' => $user->room_id,
                ],
                'room' => $room->fresh(),
            ],
        ], 200);
    }
}
