<?php

namespace App\Data\Bookings;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class CreateBookingData
{
    public function __construct(
        public int $roomId,
        public int $userId,
        public string $title,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
    ) {}

    /**
     * @param  array{room_id: int|string, title: string, starts_at: string, ends_at: string}  $validated
     */
    public static function fromValidated(array $validated, int $userId): self
    {
        return new self(
            roomId: (int) $validated['room_id'],
            userId: $userId,
            title: $validated['title'],
            startsAt: CarbonImmutable::parse($validated['starts_at']),
            endsAt: CarbonImmutable::parse($validated['ends_at']),
        );
    }
}
