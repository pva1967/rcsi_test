<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_booking_is_written_to_bookings_log(): void
    {
        $logPath = storage_path('logs/bookings.log');
        $initialLogSize = is_file($logPath) ? (int) filesize($logPath) : 0;
        $room = Room::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'title' => 'Log feature test',
            'starts_at' => '2026-11-06T10:00:00+03:00',
            'ends_at' => '2026-11-06T10:15:00+03:00',
        ]);

        $response->assertCreated();
        $bookingId = (int) $response->json('id');
        $this->assertGreaterThan(0, $bookingId);
        $this->assertFileExists($logPath);

        $logContent = file_get_contents($logPath);
        $this->assertIsString($logContent);
        $newLogContent = substr($logContent, $initialLogSize);

        $this->assertStringContainsString('Booking created', $newLogContent);
        $this->assertStringContainsString('"booking_id":'.$bookingId, $newLogContent);
    }
}
