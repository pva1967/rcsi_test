<?php

namespace App\Services;

use App\Data\Bookings\CreateBookingData;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingOutsideBusinessHoursException;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class BookingService
{
    /**
     * @throws BookingConflictException
     */
    public function create(CreateBookingData $data): Booking
    {
        $hasConflict = Booking::query()
            ->where('room_id', $data->roomId)
            ->where('starts_at', '<=', $data->endsAt)
            ->where('ends_at', '>=', $data->startsAt)
            ->exists();

        if ($hasConflict) {
            throw new BookingConflictException;
        }

        if ($data->startsAt->hour < 9 || $data->startsAt->hour >= 20) {
            throw new BookingOutsideBusinessHoursException;
        }

        $booking = Booking::query()->create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'title' => $data->title,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
        ]);

        Log::withContext(['booking_id' => $booking->id]);
        Log::channel('bookings')->info('Booking created');

        return $booking;
    }

    public function cancel(Booking $booking): void
    {
        $booking->delete();
    }
}
