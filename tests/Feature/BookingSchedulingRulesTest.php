<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingSchedulingRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_bookings_that_touch_at_their_boundaries(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T11:00:00+03:00')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T11:00:00+03:00', '2026-11-06T12:00:00+03:00')
            ->assertCreated();
    }

    public function test_detects_overlap_when_client_uses_a_different_offset(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T11:00:00+03:00')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T07:30:00Z', '2026-11-06T08:30:00Z')
            ->assertConflict();
    }

    public function test_accepts_minimum_and_maximum_booking_duration(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T09:00:00+03:00', '2026-11-06T09:15:00+03:00')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T16:00:00+03:00', '2026-11-06T20:00:00+03:00')
            ->assertCreated();
    }

    public function test_rejects_durations_outside_limits_or_not_on_fifteen_minute_steps(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T10:14:00+03:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T14:15:00+03:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T10:20:00+03:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_requires_start_and_end_times_to_align_to_quarter_hour_grid(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T10:05:00+03:00', '2026-11-06T10:20:00+03:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->postBooking($room, '2026-11-06T10:00:00+03:00', '2026-11-06T10:17:00+03:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_checks_moscow_business_hours_after_converting_utc_input_to_office_time(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T06:00:00Z', '2026-11-06T06:15:00Z')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T16:45:00Z', '2026-11-06T17:00:00Z')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T05:45:00Z', '2026-11-06T06:00:00Z')
            ->assertUnprocessable();

        $this->postBooking($room, '2026-11-06T16:45:00Z', '2026-11-06T17:15:00Z')
            ->assertUnprocessable();
    }

    public function test_checks_novosibirsk_business_hours_using_office_timezone(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $office = Office::factory()->novosibirsk()->create();
        $room = Room::factory()->for($office)->create();

        $this->postBooking($room, '2026-11-06T02:00:00Z', '2026-11-06T02:15:00Z')
            ->assertCreated();

        $this->postBooking($room, '2026-11-06T01:45:00Z', '2026-11-06T02:00:00Z')
            ->assertUnprocessable();
    }

    public function test_allows_past_future_and_holiday_dates_while_calendar_is_stubbed(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2020-02-03T10:00:00+03:00', '2020-02-03T10:15:00+03:00')
            ->assertCreated();

        $this->postBooking($room, '2026-01-01T10:00:00+03:00', '2026-01-01T10:15:00+03:00')
            ->assertCreated();

        $this->postBooking($room, '2099-12-31T10:00:00+03:00', '2099-12-31T10:15:00+03:00')
            ->assertCreated();
    }

    public function test_rejects_timestamps_without_iso8601_timezone_offset(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $room = Room::factory()->create();

        $this->postBooking($room, '2026-11-06T10:00:00', '2026-11-06T10:15:00')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at'])
            ->assertJsonPath(
                'errors.starts_at.0',
                'Время должно быть в формате ISO 8601 и содержать смещение часового пояса.',
            );
    }

    private function postBooking(Room $room, string $startsAt, string $endsAt): TestResponse
    {
        return $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'title' => 'Planning',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
