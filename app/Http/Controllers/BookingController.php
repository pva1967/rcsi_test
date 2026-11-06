<?php

namespace App\Http\Controllers;

use App\Data\Bookings\CreateBookingData;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingOutsideBusinessHoursException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->when($filters['room_id'] ?? null, fn ($query, $roomId) => $query->where('room_id', $roomId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '<=', $date));

        return $bookings->orderByRaw('starts_at DESC')->paginate(20);
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService): Booking|JsonResponse
    {
        $data = CreateBookingData::fromValidated(
            $request->validated(),
            (int) $request->user()->id,
        );

        try {
            return $bookingService->create($data);
        } catch (BookingConflictException) {
            return response()->json(['error' => 'Комната занята'], 409);
        } catch (BookingOutsideBusinessHoursException) {
            return response()->json(['error' => 'Нерабочее время'], 422);
        }
    }

    public function destroy(string $id, Request $request, BookingService $bookingService): Response
    {
        $bookingService->cancel(Booking::findOrFail($id), (int) $request->user()->id);

        return response()->noContent();
    }

    public function today(Room $room)
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn ($b) => [
                'time' => $b->starts_at . ' – ' . $b->ends_at,
                'author' => $b->user->name,
            ]);
    }
}
