<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_booking_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'user_id' => $otherUser->id,
            'title' => 'Planning',
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T11:00:00+03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Planning',
        ]);
    }

    public function test_rejects_booking_when_required_input_is_missing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'room_id' => Room::factory()->create()->id,
            'user_id' => $user->id,
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_returns_conflict_when_room_is_already_booked(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->for($room)->create([
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T11:00:00+03:00',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'title' => 'Planning',
            'starts_at' => '2026-11-06T10:30:00+03:00',
            'ends_at' => '2026-11-06T11:30:00+03:00',
        ])
            ->assertConflict()
            ->assertJsonPath('error', 'Комната занята');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_rejects_start_time_outside_existing_business_hours(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [
            'room_id' => Room::factory()->create()->id,
            'title' => 'Planning',
            'starts_at' => '2026-11-06T08:00:00+03:00',
            'ends_at' => '2026-11-06T09:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Нерабочее время');

        $this->assertDatabaseCount('bookings', 0);
    }
}
