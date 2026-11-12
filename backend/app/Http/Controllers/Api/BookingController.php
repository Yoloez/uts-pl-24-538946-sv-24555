<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Penghuni: Booking kamar kos.
     * Returns 201 Created.
     */
    public function book(Request $request, string|int $id): JsonResponse
    {
        $user = $request->user();

        // Cek apakah user sudah memiliki kamar aktif
        if ($user->room_id) {
            return response()->json([
                'message' => 'Anda sudah menempati kamar lain. Silakan checkout terlebih dahulu sebelum booking kamar baru.',
            ], 400);
        }

        $room = Room::find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        if ($room->status === 'maintenance') {
            return response()->json([
                'message' => 'Kamar sedang dalam pemeliharaan (maintenance) dan tidak dapat dibooking.',
            ], 400);
        }

        $currentOccupants = $room->occupants()->count();
        if ($currentOccupants >= $room->capacity) {
            // Update status kamar jika belum full
            if ($room->status !== 'full') {
                $room->update(['status' => 'full']);
            }

            return response()->json([
                'message' => 'Kapasitas kamar ini sudah penuh.',
            ], 400);
        }

        // Buat record booking
        $booking = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'check_in_date' => now(),
            'status' => 'active',
            'notes' => $request->input('notes'),
        ]);

        // Hubungkan user ke kamar
        $user->room_id = $room->id;
        $user->save();

        // Jika setelah penambahan user kapasitas penuh, ubah status kamar
        if ($currentOccupants + 1 >= $room->capacity) {
            $room->update(['status' => 'full']);
        }

        // Log terstruktur: Menyimpan data booking kamar (Tingkat: INFO)
        Log::info('Berhasil menyimpan data booking kamar ke basis data', [
            'who' => [
                'user_id' => $user->id,
                'role' => $user->role,
            ],
            'doing' => 'simpan_data_booking',
            'reason' => 'Penghuni mengajukan pemesanan kamar yang masih tersedia',
            'room_id' => $room->id,
            'booking_id' => $booking->id,
            'room_number' => $room->room_number,
        ]);

        return response()->json([
            'message' => "Berhasil melakukan booking kamar {$room->room_number}",
            'data' => [
                'booking' => $booking,
                'room' => $room->fresh(),
            ],
        ], 201);
    }

    /**
     * Penghuni: Memantau kamar yang saat ini ditempati beserta fasilitasnya.
     */
    public function myRoom(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->room_id) {
            return response()->json([
                'message' => 'Anda saat ini belum menempati kamar manapun.',
                'data' => null,
            ], 200);
        }

        $room = Room::with(['occupants:id,name,email'])->find($user->room_id);
        $activeBooking = Booking::where('user_id', $user->id)
            ->where('room_id', $user->room_id)
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'message' => 'Informasi kamar Anda berhasil dimuat',
            'data' => [
                'room' => $room,
                'facilities' => $room ? $room->facilities : null,
                'active_booking' => $activeBooking,
            ],
        ], 200);
    }

    /**
     * Penghuni: Memantau fasilitas kamar tertentu.
     */
    public function facilities(string|int $id): JsonResponse
    {
        $room = Room::find($id);

        if (! $room) {
            return response()->json([
                'message' => 'Kamar tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => "Fasilitas kamar {$room->room_number} berhasil dimuat",
            'data' => [
                'room_number' => $room->room_number,
                'type' => $room->type,
                'facilities' => $room->facilities,
                'price' => $room->price,
                'capacity' => $room->capacity,
                'status' => $room->status,
                'description' => $room->description,
            ],
        ], 200);
    }

    /**
     * Penghuni: Checkout dari kamar.
     */
    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->room_id) {
            return response()->json([
                'message' => 'Anda sedang tidak menempati kamar kos manapun.',
            ], 400);
        }

        $roomId = $user->room_id;
        $room = Room::find($roomId);

        // Update active booking
        Booking::where('user_id', $user->id)
            ->where('room_id', $roomId)
            ->where('status', 'active')
            ->update([
                'status' => 'checked_out',
                'check_out_date' => now(),
            ]);

        // Release user from room
        $user->room_id = null;
        $user->save();

        // Update status room to available if it was full
        if ($room) {
            $currentOccupants = $room->occupants()->count();
            if ($room->status === 'full' && $currentOccupants < $room->capacity) {
                $room->update(['status' => 'available']);
            }
        }

        return response()->json([
            'message' => 'Berhasil checkout dari kamar kos.',
            'data' => [
                'room_number' => $room ? $room->room_number : null,
                'checkout_at' => now()->toIso8601String(),
            ],
        ], 200);
    }
}
