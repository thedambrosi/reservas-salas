<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationSeries;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->room = Room::factory()->create();
    $this->reservation = Reservation::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->owner->id,
        'starts_at' => CarbonImmutable::now()->addDay()->setTime(10, 0),
        'ends_at' => CarbonImmutable::now()->addDay()->setTime(11, 0),
    ]);
});

it('lets the responsible user cancel their reservation without deleting the row', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/reservations/{$this->reservation->id}/cancel", ['reason' => 'Reunião adiada'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->reservation->refresh();
    expect($this->reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($this->reservation->cancelled_by)->toBe($this->owner->id)
        ->and($this->reservation->cancellation_reason)->toBe('Reunião adiada')
        ->and(Reservation::count())->toBe(1);
});

it('forbids another user from cancelling the reservation', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/reservations/{$this->reservation->id}/cancel")
        ->assertForbidden();

    expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('lets an admin cancel any reservation', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/reservations/{$this->reservation->id}/cancel")->assertOk();
});

it('returns 409 when cancelling an already cancelled reservation', function () {
    Sanctum::actingAs($this->owner);
    $this->reservation->update(['status' => ReservationStatus::Cancelled]);

    $this->postJson("/api/reservations/{$this->reservation->id}/cancel")->assertStatus(409);
});

it('cancels only future occurrences when scope is series', function () {
    $series = ReservationSeries::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->owner->id,
    ]);

    $past = Reservation::factory()->past()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->owner->id,
        'reservation_series_id' => $series->id,
    ]);

    $future = Reservation::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->owner->id,
        'reservation_series_id' => $series->id,
        'starts_at' => CarbonImmutable::now()->addWeek(),
        'ends_at' => CarbonImmutable::now()->addWeek()->addHour(),
    ]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/reservations/{$future->id}/cancel", ['scope' => 'series'])
        ->assertOk()
        ->assertJsonPath('cancelled_count', 1);

    expect($past->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($future->fresh()->status)->toBe(ReservationStatus::Cancelled);
});
