<?php

namespace App\Services;

use App\Data\Bookings\CreateBookingData;
use App\Exceptions\BookingConflictException;
use App\Exceptions\BookingOutsideBusinessHoursException;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * @throws BookingConflictException
     */
    public function create(CreateBookingData $data): Booking
    {
        $room = Room::query()->with('office')->findOrFail($data->roomId);
        $officeTimezone = $room->office->timezone;
        $startsAtLocal = $data->startsAt->setTimezone($officeTimezone);
        $endsAtLocal = $data->endsAt->setTimezone($officeTimezone);

        $this->validateDurationAndGrid($data->startsAt, $data->endsAt, $startsAtLocal, $endsAtLocal);
        $this->validateOfficeHours($startsAtLocal, $endsAtLocal);

        if ($this->isHoliday($startsAtLocal)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Бронирование в праздничный день недоступно.'],
            ]);
        }

        $startsAtUtc = $data->startsAt->setTimezone('UTC');
        $endsAtUtc = $data->endsAt->setTimezone('UTC');

        $hasConflict = Booking::query()
            ->where('room_id', $data->roomId)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();

        if ($hasConflict) {
            throw new BookingConflictException;
        }

        $booking = Booking::query()->create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'title' => $data->title,
            'starts_at' => $startsAtUtc,
            'ends_at' => $endsAtUtc,
        ]);

        Log::withContext(['booking_id' => $booking->id]);
        Log::channel('bookings')->info('Booking created');

        return $booking;
    }

    public function cancel(Booking $booking, int $userId): void
    {
        if ($booking->user_id !== $userId) {
            throw new AuthorizationException('Вы можете отменить только собственную бронь.');
        }

        $booking->delete();
    }

    private function validateDurationAndGrid(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        CarbonInterface $startsAtLocal,
        CarbonInterface $endsAtLocal,
    ): void {
        $startsAtMicros = ((int) $startsAt->format('U') * 1_000_000) + (int) $startsAt->format('u');
        $endsAtMicros = ((int) $endsAt->format('U') * 1_000_000) + (int) $endsAt->format('u');
        $durationMicros = $endsAtMicros - $startsAtMicros;
        $minimumDurationMicros = 15 * 60 * 1_000_000;
        $maximumDurationMicros = 4 * 60 * 60 * 1_000_000;
        $durationStepMicros = 15 * 60 * 1_000_000;

        if (
            $durationMicros < $minimumDurationMicros
            || $durationMicros > $maximumDurationMicros
            || $durationMicros % $durationStepMicros !== 0
        ) {
            throw ValidationException::withMessages([
                'ends_at' => ['Длительность бронирования должна быть от 15 минут до 4 часов с шагом 15 минут.'],
            ]);
        }

        if (! $this->isOnQuarterHourGrid($startsAtLocal)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Время начала должно соответствовать шагу 15 минут.'],
            ]);
        }

        if (! $this->isOnQuarterHourGrid($endsAtLocal)) {
            throw ValidationException::withMessages([
                'ends_at' => ['Время окончания должно соответствовать шагу 15 минут.'],
            ]);
        }
    }

    private function validateOfficeHours(CarbonInterface $startsAtLocal, CarbonInterface $endsAtLocal): void
    {
        if (
            $startsAtLocal->toDateString() !== $endsAtLocal->toDateString()
            || $startsAtLocal->format('H:i:s') < '09:00:00'
            || $endsAtLocal->format('H:i:s') > '20:00:00'
        ) {
            throw new BookingOutsideBusinessHoursException;
        }
    }

    private function isOnQuarterHourGrid(CarbonInterface $date): bool
    {
        return (int) $date->format('i') % 15 === 0
            && $date->format('s.u') === '00.000000';
    }

    private function isHoliday(CarbonInterface $date): bool
    {
        // TODO: Реализовать проверку календаря праздников на следующем шаге.
        return false;
    }
}
