<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

test('shows the login form to guests', function () {
    $this->withoutVite();

    $this->get(route('login'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Login')
        ->where('submitUrl', '/login')
    );
});

test('redirects guests from authenticated routes to login', function (string $method, string $routeName) {
    $this->call($method, route($routeName))->assertRedirectToRoute('login');
})->with([
    'workspace' => ['GET', 'generations.create'],
    'generation submission' => ['POST', 'generations.store'],
    'logout' => ['POST', 'logout'],
]);

test('redirects signed-in users from login to the workspace', function () {
    $this->actingAs(User::factory()->make());

    $this->get(route('login'))->assertRedirectToRoute('generations.create');
});

test('signs in a registered user when the email is entered with capital letters', function () {
    $user = User::factory()->create(['email' => 'member@example.com']);

    $response = $this->post(route('login.store'), ['email' => 'Member@Example.com', 'password' => 'password']);

    $response->assertRedirectToRoute('generations.create');
    $this->assertAuthenticatedAs($user);
});

test('signs in with valid credentials and redirects to the workspace', function () {
    $user = User::factory()->create();
    $this->withSession(['url.intended' => route('home')]);
    $previousSessionId = session()->getId();

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $response->assertRedirectToRoute('generations.create')->assertSessionMissing('url.intended');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSessionId);
});

test('requires both login fields', function () {
    $this->from(route('login'))->post(route('login.store'))
        ->assertRedirectToRoute('login')
        ->assertSessionHasErrors([
            'email' => 'The email field is required.',
            'password' => 'The password field is required.',
        ]);
    $this->assertGuest();
});

test('rejects invalid credentials without flashing the password', function () {
    $user = User::factory()->create();

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
});

test('temporarily blocks login after five failed attempts', function () {
    $user = User::factory()->create();
    $this->freezeTime();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->from(route('login'))->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $response->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);
    $this->assertGuest();
});

test('logs out and invalidates the old session', function () {
    $this->actingAs(User::factory()->make())->withSession(['private_data' => true, '_token' => 'old-token']);
    $previousSessionId = session()->getId();

    $response = $this->post(route('logout'));

    $response->assertRedirectToRoute('login')->assertSessionMissing('private_data');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($previousSessionId);
    expect(session()->token())->not->toBe('old-token');
});
