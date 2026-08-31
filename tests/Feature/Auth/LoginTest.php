<?php

use App\Models\User;

it('logs in with valid credentials', function () {
    User::factory()->create([
        'email' => 'joao@example.com',
        'password' => 'secret-password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'joao@example.com',
        'password' => 'secret-password',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'joao@example.com',
        'password' => 'secret-password',
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'joao@example.com',
        'password' => 'wrong',
    ])->assertStatus(422)->assertJsonValidationErrorFor('email');
});

it('throttles repeated login attempts', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);
    }

    $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ])->assertStatus(429);
});

it('requires authentication for protected routes', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->getJson('/api/rooms')->assertUnauthorized();
});
