<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

test('shows the signup page to guests', function () {
    $this->withoutVite();

    $this->get(route('register'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Register')
        ->where('submitUrl', '/register')
        ->where('loginUrl', '/login')
    );
});

test('creates an account, hashes the password, and signs the user in', function () {
    $password = 'H3dx!8vn';
    $this->withSession([]);
    $previousSessionId = session()->getId();

    $response = $this->post(route('register.store'), [
        'name' => 'New Member',
        'email' => 'Member@Example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertRedirectToRoute('generations.create');
    $user = User::where('email', 'member@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('New Member');
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(session()->getId())->not->toBe($previousSessionId);
});

test('rejects a duplicate email without changing the existing account', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'New Member',
        'email' => 'Member@Example.com',
        'password' => 'A long unique passphrase!',
        'password_confirmation' => 'A long unique passphrase!',
    ]);

    $response->assertRedirectToRoute('register')->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    $this->assertGuest();
    $this->assertDatabaseCount(User::class, 1);
    expect($user->fresh()->password)->toBe($user->password);
});

test('rejects invalid signup details without storing an account or flashing passwords', function (array $overrides, array $errors) {
    $payload = $overrides + [
        'name' => 'New Member',
        'email' => 'member@example.com',
        'password' => 'A long unique passphrase!',
        'password_confirmation' => 'A long unique passphrase!',
    ];

    $response = $this->from(route('register'))->post(route('register.store'), $payload);

    $response->assertRedirectToRoute('register')->assertSessionHasErrors($errors)
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');
    $this->assertGuest();
    $this->assertDatabaseEmpty(User::class);
})->with([
    'missing name' => [['name' => ''], ['name' => 'The name field is required.']],
    'invalid email' => [['email' => 'not-an-email'], ['email' => 'The email field must be a valid email address.']],
    'password mismatch' => [['password_confirmation' => 'A different passphrase!'], ['password' => 'The password field confirmation does not match.']],
    'seven-character password' => [['password' => 'H3dx!8v', 'password_confirmation' => 'H3dx!8v'], ['password' => 'The password field must be at least 8 characters.']],
    'password exceeds bcrypt byte limit' => [
        ['password' => str_repeat('é', 37), 'password_confirmation' => str_repeat('é', 37)],
        ['password' => 'The password must be at most 72 bytes and must not contain null characters.'],
    ],
]);

test('redirects signed-in users away from signup', function (string $method) {
    $this->actingAs(User::factory()->make());

    $this->call($method, route('register'))->assertRedirectToRoute('generations.create');
})->with(['GET', 'POST']);

test('rate limits repeated signup requests', function () {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post(route('register.store'))->assertSessionHasErrors('name');
    }

    $this->post(route('register.store'))->assertTooManyRequests();
});
