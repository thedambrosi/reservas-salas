<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->roomA = Room::factory()->create();
    $this->roomB = Room::factory()->create();

    $day = CarbonImmutable::parse('2026-10-05')->setTime(9, 0);

    $this->onRoomA = Reservation::factory()->create([
        'room_id' => $this->roomA->id,
        'user_id' => $this->user->id,
        'starts_at' => $day,
        'ends_at' => $day->addHour(),
    ]);

    $this->onRoomB = Reservation::factory()->create([
        'room_id' => $this->roomB->id,
        'user_id' => User::factory()->create()->id,
        'starts_at' => $day->addDays(3),
        'ends_at' => $day->addDays(3)->addHour(),
    ]);
});

it('filters by room', function () {
    $this->getJson("/api/reservations?room_id={$this->roomA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->onRoomA->id);
});

it('filters by a single day', function () {
    $this->getJson('/api/reservations?date=2026-10-05')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->onRoomA->id);
});

it('filters by a date range', function () {
    $this->getJson('/api/reservations?from=2026-10-07&to=2026-10-10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->onRoomB->id);
});

it('filters to the current user with mine=true', function () {
    $this->getJson('/api/reservations?mine=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->onRoomA->id);
});
