<?php

use App\Modules\Identity\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('logs in with a valid username and password, receiving a usable token', function () {
    $user = User::factory()->create(['username' => 'ahmad', 'password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/v1/login', [
        'login' => 'ahmad',
        'password' => 'correct-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['public_id', 'username', 'email']]);

    $token = $response->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user')
        ->assertOk();
});

it('logs in with email interchangeably with username', function () {
    User::factory()->create(['email' => 'ahmad@example.test', 'password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/v1/login', [
        'login' => 'ahmad@example.test',
        'password' => 'correct-password',
    ]);

    $response->assertOk();
});

it('rejects an incorrect password without revealing whether the account exists', function () {
    User::factory()->create(['username' => 'ahmad', 'password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/v1/login', [
        'login' => 'ahmad',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('rejects login for an unknown identifier with the same error shape as a wrong password', function () {
    $response = $this->postJson('/api/v1/login', [
        'login' => 'nobody',
        'password' => 'whatever',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('rejects login for an inactive account even with the correct password', function () {
    User::factory()->inactive()->create(['username' => 'ahmad', 'password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/v1/login', [
        'login' => 'ahmad',
        'password' => 'correct-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('rejects login for a suspended account', function () {
    User::factory()->suspended()->create(['username' => 'ahmad', 'password' => bcrypt('correct-password')]);

    $response = $this->postJson('/api/v1/login', [
        'login' => 'ahmad',
        'password' => 'correct-password',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('records last_login_at on a successful login', function () {
    $user = User::factory()->create(['username' => 'ahmad', 'password' => bcrypt('correct-password')]);

    expect($user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/login', ['login' => 'ahmad', 'password' => 'correct-password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('logs out, deleting the current access token from storage', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api');

    $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    // Asserted against storage directly rather than a second simulated
    // request with the same (now-revoked) token: Sanctum's guard
    // memoizes the resolved user for the lifetime of the test's
    // container, so a second call in the same test doesn't re-resolve
    // from the database the way a real, separate HTTP request would --
    // what actually matters (and is what Sanctum itself checks on every
    // real request) is that the token record is gone.
    expect(PersonalAccessToken::find($token->accessToken->id))->toBeNull();
});

it('refuses logout without a token', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});
