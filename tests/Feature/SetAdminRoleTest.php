<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('grants admin access to an existing account', function () {
    $user = User::factory()->create(['email' => 'operator@example.com']);

    $this->artisan('app:set-admin-role', ['email' => 'Operator@Example.com'])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

test('revokes admin access from an existing account', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $this->artisan('app:set-admin-role', ['email' => $user->email, '--revoke' => true])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeFalse();
});

test('does not create an account when granting admin access to an unknown email', function () {
    $this->artisan('app:set-admin-role', ['email' => 'missing@example.com'])
        ->expectsOutput('No account found for that email.')
        ->assertFailed();

    $this->assertDatabaseEmpty('users');
});
