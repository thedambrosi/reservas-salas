<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->room = Room::factory()->create();
    Sanctum::actingAs($this->user);

    // Existing confirmed reservation: 10:00 - 11:00 tomorrow.
    $this->base = CarbonImmutable::now()->addDay()->setTime(10, 0);
    $this->existing = Reservation::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'starts_at' => $this->base,
        'ends_at' => $this->base->addHour(),
    ]);
});

dataset('windows', [
    'identical' => [0, 60, true],
    'requested inside existing' => [15, 30, true],
    'existing inside requested' => [-30, 120, true],
    'overlaps left edge' => [-30, 60, true],
    'overlaps right edge' => [30, 60, true],
    'back to back before' => [-60, 60, false],
    'back to back after' => [60, 60, false],
    'well separated' => [180, 60, false],
]);

it('accepts or rejects a new reservation against an existing one', function (int $offsetMinutes, int $durationMinutes, bool $shouldConflict) {
    $start = $this->base->addMinutes($offsetMinutes);

    $response = $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addMinutes($durationMinutes)->toIso8601String(),
    ]);

    if ($shouldConflict) {
        $response->assertStatus(422)
            ->assertJsonPath('conflicts.0.conflicting_reservation_id', $this->existing->id);
        expect(Reservation::count())->toBe(1);
    } else {
        $response->assertCreated();
        expect(Reservation::count())->toBe(2);
    }
})->with('windows');

it('ignores cancelled reservations when checking for conflicts', function () {
    $this->existing->update(['status' => ReservationStatus::Cancelled]);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $this->base->toIso8601String(),
        'ends_at' => $this->base->addHour()->toIso8601String(),
    ])->assertCreated();
});

it('allows the same time slot in a different room', function () {
    $otherRoom = Room::factory()->create();

    $this->postJson('/api/reservations', [
        'room_id' => $otherRoom->id,
        'starts_at' => $this->base->toIso8601String(),
        'ends_at' => $this->base->addHour()->toIso8601String(),
    ])->assertCreated();
});
