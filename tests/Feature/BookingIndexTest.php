<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_own_bookings(): void
    {
        $user = User::factory()->create();
        $own = Booking::factory()->for($user)->create();
        Booking::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_filters_own_bookings_by_room_id(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $matchingBooking = Booking::factory()->for($user)->for($room)->create();
        Booking::factory()->for($user)->for($otherRoom)->create();
        Booking::factory()->for($otherRoom)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings?room_id='.$room->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingBooking->id);
    }

    public function test_filters_own_bookings_by_inclusive_start_date_range(): void
    {
        $user = User::factory()->create();
        $beforeRange = Booking::factory()->for($user)->create([
            'starts_at' => '2026-06-09 23:45:00',
            'ends_at' => '2026-06-10 00:45:00',
        ]);
        $fromBoundary = Booking::factory()->for($user)->create([
            'starts_at' => '2026-06-10 09:00:00',
            'ends_at' => '2026-06-10 10:00:00',
        ]);
        $toBoundary = Booking::factory()->for($user)->create([
            'starts_at' => '2026-06-12 19:00:00',
            'ends_at' => '2026-06-12 20:00:00',
        ]);
        $afterRange = Booking::factory()->for($user)->create([
            'starts_at' => '2026-06-13 09:00:00',
            'ends_at' => '2026-06-13 10:00:00',
        ]);
        Booking::factory()->create([
            'starts_at' => '2026-06-11 09:00:00',
            'ends_at' => '2026-06-11 10:00:00',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/bookings?date_from=2026-06-10&date_to=2026-06-12')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$fromBoundary->id, $toBoundary->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
        $this->assertNotContains($beforeRange->id, collect($response->json('data'))->pluck('id'));
        $this->assertNotContains($afterRange->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_rejects_date_range_when_date_from_is_after_date_to(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/bookings?date_from=2026-06-12&date_to=2026-06-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');
    }
}
