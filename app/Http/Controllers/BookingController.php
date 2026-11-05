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
        return Booking::where('user_id', $request->user()->id)
            ->orderByRaw('starts_at DESC')
            ->paginate(20);
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

    public function destroy(string $id, BookingService $bookingService): Response
    {
        $bookingService->cancel(Booking::findOrFail($id));

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
