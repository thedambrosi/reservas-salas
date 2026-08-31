<?php

use App\Models\User;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token'])
        ->assertJsonPath('user.role', 'user');

    expect(User::where('email', 'maria@example.com')->exists())->toBeTrue();
});

it('never lets a registration payload set the admin role', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
        'role' => 'admin',
    ])->assertCreated();

    expect(User::where('email', 'sneaky@example.com')->first()->isAdmin())->toBeFalse();
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Dup',
        'email' => 'taken@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});
