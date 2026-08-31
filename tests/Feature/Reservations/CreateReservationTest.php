<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->room = Room::factory()->create();
    Sanctum::actingAs($this->user);
});

it('creates a single reservation for the authenticated user', function () {
    $start = CarbonImmutable::now()->addDay()->setTime(14, 0);

    $response = $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addHour()->toIso8601String(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.is_recurring', false);

    expect(Reservation::count())->toBe(1);
});

it('rejects an end before the start', function () {
    $start = CarbonImmutable::now()->addDay()->setTime(14, 0);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->subHour()->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrorFor('ends_at');
});

it('rejects a reservation that starts in the past', function () {
    $start = CarbonImmutable::now()->subHour();

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addHour()->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrorFor('starts_at');
});

it('rejects a reservation longer than the configured maximum', function () {
    config()->set('reservations.max_duration_minutes', 120);
    $start = CarbonImmutable::now()->addDay()->setTime(8, 0);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addHours(5)->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrorFor('ends_at');
});

it('forbids a non-admin from booking on behalf of another user', function () {
    $other = User::factory()->create();
    $start = CarbonImmutable::now()->addDay()->setTime(14, 0);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'user_id' => $other->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addHour()->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrorFor('user_id');
});

it('lets an admin book on behalf of another user', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $other = User::factory()->create();
    $start = CarbonImmutable::now()->addDay()->setTime(14, 0);

    $this->postJson('/api/reservations', [
        'room_id' => $this->room->id,
        'user_id' => $other->id,
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->addHour()->toIso8601String(),
    ])->assertCreated()->assertJsonPath('data.user_id', $other->id);
});
