<?php

use App\Models\Reservation;
use App\Models\ReservationSeries;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->room = Room::factory()->create();
    Sanctum::actingAs($this->user);
    $this->start = CarbonImmutable::now()->addWeek()->setTime(9, 0);
});

it('materialises one reservation per occurrence and links them to a series', function () {
    $response = $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $this->start->toIso8601String(),
        'ends_at' => $this->start->addHour()->toIso8601String(),
        'recurrence' => ['frequency' => 'weekly', 'interval' => 1, 'count' => 4],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.occurrences', 4)
        ->assertJsonCount(4, 'data.reservations');

    expect(ReservationSeries::count())->toBe(1)
        ->and(Reservation::count())->toBe(4)
        ->and(Reservation::whereNotNull('reservation_series_id')->count())->toBe(4);
});

it('rejects the whole series and persists nothing when one occurrence conflicts', function () {
    // Block the 3rd weekly occurrence.
    Reservation::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => User::factory()->create()->id,
        'starts_at' => $this->start->addWeeks(2),
        'ends_at' => $this->start->addWeeks(2)->addHour(),
    ]);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $this->start->toIso8601String(),
        'ends_at' => $this->start->addHour()->toIso8601String(),
        'recurrence' => ['frequency' => 'weekly', 'count' => 4],
    ])->assertStatus(422)->assertJsonCount(1, 'conflicts');

    expect(ReservationSeries::count())->toBe(0)
        ->and(Reservation::count())->toBe(1); // only the pre-existing blocker
});

it('caps the number of occurrences', function () {
    config()->set('reservations.max_occurrences', 10);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $this->start->toIso8601String(),
        'ends_at' => $this->start->addHour()->toIso8601String(),
        'recurrence' => ['frequency' => 'weekly', 'count' => 11],
    ])->assertStatus(422)->assertJsonValidationErrorFor('recurrence.count');
});
