# Booking Controller Service Layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use the executing-plans workflow to implement this plan task by task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move booking creation and cancellation orchestration out of `BookingController` into a typed DTO and application service while preserving the endpoint contract.

**Architecture:** `StoreBookingRequest` validates and supplies only the HTTP input needed by the operation. `CreateBookingData` carries typed, validated fields with the authenticated user assigned server-side. `BookingService` owns create/cancel operations; the controller delegates and returns the existing model or no-content response.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, PostgreSQL, PHPUnit, Laravel Sanctum.

**Spec:** `docs/superpowers/specs/2026-11-05-booking-controller-service-layer-design.md`

## Global Constraints

- Change only booking creation and cancellation plus classes/tests required by those operations.
- Keep `index()`, `today()`, routes, database schema, and dependencies unchanged.
- Preserve successful response bodies and status codes.
- Build the DTO only from validated input; assign `userId` from the authenticated user.
- Leave scheduling-rule corrections and cancellation ownership authorization to their separate review items.

## Review Focus

- Extra client-supplied `user_id` must not override the authenticated user; pin with a feature assertion on the persisted booking.
- Invalid or incomplete request input must return Laravel's JSON 422 response; pin with a missing-required-field test.
- A valid create operation must persist and return the booking; pin with a database assertion and response assertion.
- An already occupied interval must preserve the current 409 response; pin with a feature test before extracting conflict handling.
- Cancel must still return 204 and delete the requested booking; pin with a database assertion.
- The API's existing authentication middleware must still reject unauthenticated writes; pin create/destroy authentication if not already covered by the new feature tests.

### Task 1: Extract create and cancel operations behind DTO and service

**Files:**
- Create: `app/Http/Requests/StoreBookingRequest.php`
- Create: `app/Data/Bookings/CreateBookingData.php`
- Create: `app/Exceptions/BookingConflictException.php`
- Create: `app/Exceptions/BookingOutsideBusinessHoursException.php`
- Create: `app/Services/BookingService.php`
- Modify: `app/Http/Controllers/BookingController.php`
- Create: `tests/Feature/BookingStoreTest.php`
- Create: `tests/Feature/BookingDestroyTest.php`

**Interfaces:**
- `CreateBookingData::fromValidated(array $validated, int $userId): self`
- DTO fields: `int $roomId`, `int $userId`, `string $title`, `CarbonInterface $startsAt`, `CarbonInterface $endsAt`
- `BookingService::create(CreateBookingData $data): Booking`
- `BookingService::create()` throws `BookingConflictException` when the room overlaps an existing booking.
- `BookingService::create()` throws `BookingOutsideBusinessHoursException` when the current start-hour rule rejects the request.
- `BookingService::cancel(Booking $booking): void`
- `StoreBookingRequest::rules(): array`

- [ ] **Step 1: Add failing API contract tests**

Add a successful creation test that authenticates a user, submits valid room/title/ISO-8601 times plus a different `user_id`, then asserts a 201 response, one persisted booking, and the authenticated user's ID in the response and database. Add a creation test that omits a required field and expects 422. Add a conflict test that creates an existing overlapping booking and expects 409. Add a test for an out-of-hours start rejected by the pre-refactor logic and expects 422. Add a cancellation test that authenticates, deletes an existing booking, expects 204, and asserts the row is gone.

```php
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
```

- [ ] **Step 2: Run the focused tests and confirm the expected failures**

Run: `docker compose exec app php artisan test --compact tests/Feature/BookingStoreTest.php tests/Feature/BookingDestroyTest.php`

Expected: the missing-field test demonstrates the current lack of validation; the authenticated-user assertion demonstrates that request `user_id` is currently trusted; the tests must not fail because of a test setup or database error.

- [ ] **Step 3: Create the request, DTO, and service using Laravel conventions**

Run:

```bash
docker compose exec app php artisan make:request StoreBookingRequest --no-interaction
docker compose exec app php artisan make:class Data/Bookings/CreateBookingData --no-interaction
docker compose exec app php artisan make:class Services/BookingService --no-interaction
```

Give the request only the structural rules needed to construct the DTO: required existing `room_id`, required string `title` up to the database column's 255-character limit, and required parseable `starts_at`/`ends_at`. Do not add duration, office-hours, overlap, timezone-policy, or authorization rules from other review items. Convert validated timestamps to Carbon values in the DTO. Assign the DTO user ID from the authenticated principal.

- [ ] **Step 4: Move operation code to `BookingService` and reduce the controller to orchestration**

Implement `create()` to perform the current conflict check, current start-hour working-time check, model creation, and booking log in their existing order; implement `cancel()` to delete the supplied model. Throw `BookingConflictException` on conflict and `BookingOutsideBusinessHoursException` when the current start-hour condition rejects the request, and map them in the controller to the existing 409 and 422 JSON responses. Keep the existing successful create response as the returned `Booking` (201) and cancellation response as 204. Use a parameterized Eloquent/Query Builder conflict query; do not retain interpolation of request values into SQL. Keep the current authorization behavior for cancellation in this refactor; ownership checks remain outside this specification.

- [ ] **Step 5: Run focused tests and fix implementation defects**

Run: `docker compose exec app php artisan test --compact tests/Feature/BookingStoreTest.php tests/Feature/BookingDestroyTest.php`

Expected: all focused feature tests pass against the PostgreSQL testing database.

- [ ] **Step 6: Run the full feature suite and format changed PHP files**

Run:

```bash
docker compose exec app php artisan test --compact
docker compose exec app vendor/bin/pint --dirty --format agent
```

Expected: the full test suite passes, and Pint reports/formats only dirty PHP files. If Pint changes PHP files, rerun the focused feature tests once.

- [ ] **Step 7: Review the diff and commit the implementation**

Confirm that `BookingController::index()` and `today()` are unchanged, no routes/schema/dependencies changed, and `REVIEW.md` remains unstaged. Commit only the implementation and its tests with:

```bash
git add app/Http/Requests/StoreBookingRequest.php app/Data/Bookings/CreateBookingData.php app/Exceptions/BookingConflictException.php app/Exceptions/BookingOutsideBusinessHoursException.php app/Services/BookingService.php app/Http/Controllers/BookingController.php tests/Feature/BookingStoreTest.php tests/Feature/BookingDestroyTest.php
git commit -m "refactor: extract booking operations to service"
```
