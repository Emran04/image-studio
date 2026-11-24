<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

test('requires sign-in to buy credits', function () {
    $this->post(route('credits.store'))->assertRedirectToRoute('login');
});

test('adds a fixed demo pack to the signed-in account and displays its new balance', function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    $buyer = User::factory()->create(['credit_balance' => 30_000]);
    $otherUser = User::factory()->create();

    $this->actingAs($buyer)->from(route('generations.index'))->post(route('credits.store'), [
        'user_id' => $otherUser->id,
        'amount' => 1000,
    ])->assertRedirectToRoute('generations.index');

    $this->assertDatabaseHas(User::class, ['id' => $buyer->id, 'credit_balance' => 130_000]);
    $this->assertDatabaseHas(User::class, ['id' => $otherUser->id, 'credit_balance' => 0]);

    $this->actingAs($buyer->refresh())->get(route('generations.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('credits.balance', 130_000)
            ->where('credits.packSize', 100_000)
            ->where('credits.purchaseUrl', '/app/credits')
        );
});
