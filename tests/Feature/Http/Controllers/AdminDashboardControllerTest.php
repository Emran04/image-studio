<?php

use App\Models\ImageGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

test('redirects guests from the admin dashboard to the admin login', function () {
    $this->get('/admin')->assertRedirectToRoute('admin.login');
});

test('forbids signed-in users without the admin role', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
});

test('shows an empty report for the current month when there are no finished generations', function () {
    $this->travelTo(now()->setDate(2026, 11, 24));
    $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('AdminDashboard')
        ->where('month', '2026-11')
        ->where('report.totals.spend', '0.0000')
        ->has('report.days', 0)
        ->has('report.users', 0)
    );
});

test('groups calculated costs by submission day and user without treating missing pricing as zero', function () {
    $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));
    $ada = User::factory()->create(['name' => 'Ada']);
    $bea = User::factory()->create(['name' => 'Bea']);
    $priced = ImageGeneration::factory()->state([
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 100, 'input_image_tokens' => 200, 'output_tokens' => 300, 'reasoning_tokens' => 400],
        'rates' => ['input_text_tokens' => '1.00', 'input_image_tokens' => '2.00', 'output_tokens' => '3.00', 'reasoning_tokens' => '4.00'],
    ]);
    $priced->for($ada)->create(['created_at' => '2026-11-24 00:00:00']);
    $priced->for($bea)->create(['created_at' => '2026-11-24 23:59:59']);
    $priced->for($ada)->create(['created_at' => '2026-11-01 00:00:00', 'completed_at' => '2026-12-01 00:00:00']);
    $priced->for($bea)->create(['created_at' => '2026-12-01 00:00:00']);
    $priced->for($bea)->create(['created_at' => '2026-10-31 23:59:59']);
    ImageGeneration::factory()->for($ada)->create(['created_at' => '2026-11-24', 'status' => 'succeeded']);
    ImageGeneration::factory()->for($bea)->create(['created_at' => '2026-11-22', 'status' => 'failed']);
    ImageGeneration::factory()->for($bea)->create(['created_at' => '2026-11-25', 'status' => 'processing']);

    $response = $this->get('/admin?month=2026-11');

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('AdminDashboard')
        ->where('month', '2026-11')
        ->where('report.totals', ['spend' => '0.0114', 'providerCost' => '0.0090', 'generations' => 5, 'missingPricing' => 1, 'profit' => '0.0024'])
        ->has('report.days', 3)
        ->where('report.days.0.date', '2026-11-24')
        ->where('report.days.0.spend', '0.0076')
        ->where('report.days.0.providerCost', '0.0060')
        ->where('report.days.0.profit', '0.0016')
        ->where('report.days.0.users.0.id', $ada->id)
        ->where('report.days.0.users.0.missingPricing', 1)
        ->where('report.days.0.users.0.spend', '0.0038')
        ->where('report.days.0.users.1.id', $bea->id)
        ->where('report.days.1.spend', '0.0000')
        ->where('report.days.2.date', '2026-11-01')
        ->where('report.users.0.spend', '0.0076')
        ->where('report.users.1.spend', '0.0038')
    );
    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

test('rejects an invalid month before running the report', function (string $query) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get('/admin?'.$query)->assertUnprocessable();
})->with(['month=2026-13', 'month=0000-01', 'month[]=2026-11']);
