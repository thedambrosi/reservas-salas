<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

it('lets any authenticated user list and filter rooms', function () {
    Sanctum::actingAs(User::factory()->create());

    Room::factory()->create(['name' => 'Sala Alpha', 'capacity' => 4]);
    Room::factory()->create(['name' => 'Sala Beta', 'capacity' => 20]);

    $this->getJson('/api/rooms?min_capacity=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Sala Beta');
});

it('lets an admin create a room', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/rooms', ['name' => 'Sala Nova', 'capacity' => 8])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sala Nova');

    expect(Room::where('name', 'Sala Nova')->exists())->toBeTrue();
});

it('forbids a non-admin from creating a room', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/rooms', ['name' => 'Sala X', 'capacity' => 8])
        ->assertForbidden();
});

it('validates room input', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson('/api/rooms', ['name' => '', 'capacity' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'capacity']);
});

it('blocks deleting a room that has upcoming confirmed reservations', function () {
    Sanctum::actingAs(User::factory()->admin()->create());
    $room = Room::factory()->create();
    $room->reservations()->save(Reservation::factory()->make([
        'user_id' => User::factory()->create()->id,
        'starts_at' => CarbonImmutable::now()->addDay(),
        'ends_at' => CarbonImmutable::now()->addDay()->addHour(),
    ]));

    $this->deleteJson("/api/rooms/{$room->id}")->assertStatus(409);
});
