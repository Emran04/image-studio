<?php

use App\Models\ImageGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
    fakeNovemberUsage(novemberUsage());

    $this->get('/admin')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('AdminDashboard')
        ->where('month', '2026-11')
        ->where('report.totals.spend', '0.0000')
        ->where('report.totals.providerCost', '0.000000000')
        ->where('report.providerCostsDiffer', false)
        ->has('report.days', 0)
        ->has('report.users', 0)
    );
});

test('uses provider costs for the billing period and calculates user costs from local completions', function () {
    $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));
    fakeNovemberUsage(novemberUsage([
        1 => ['0.003000000', 1],
        23 => ['0.002000000', 1],
        24 => ['0.006123456', 2],
        27 => ['0.001000001', 1],
    ], '0.012123457'));
    $ada = User::factory()->create(['name' => 'Ada']);
    $bea = User::factory()->create(['name' => 'Bea']);
    $priced = ImageGeneration::factory()->state([
        'status' => 'succeeded',
        'usage' => ['input_text_tokens' => 100, 'input_image_tokens' => 200, 'output_tokens' => 300, 'reasoning_tokens' => 400],
        'rates' => ['input_text_tokens' => '1.00', 'input_image_tokens' => '2.00', 'output_tokens' => '3.00', 'reasoning_tokens' => '4.00'],
    ]);
    $priced->for($ada)->create(['created_at' => '2026-11-23 23:59:59', 'completed_at' => '2026-11-24 00:00:00']);
    $priced->for($bea)->create(['created_at' => '2026-11-24 23:59:00', 'completed_at' => '2026-11-24 23:59:59']);
    $priced->for($ada)->create(['created_at' => '2026-11-01 00:00:00', 'completed_at' => '2026-12-01 00:00:00']);
    $priced->for($ada)->create(['created_at' => '2026-10-31 23:59:59', 'completed_at' => '2026-11-01 00:00:00']);
    $priced->for($bea)->create(['completed_at' => '2026-10-31 23:59:59']);
    ImageGeneration::factory()->for($ada)->create(['completed_at' => '2026-11-23', 'status' => 'succeeded']);
    ImageGeneration::factory()->for($bea)->create(['completed_at' => '2026-11-22', 'status' => 'failed']);
    ImageGeneration::factory()->for($bea)->create(['created_at' => '2026-11-25', 'status' => 'processing']);

    $response = $this->get('/admin?month=2026-11');

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('AdminDashboard')
        ->where('month', '2026-11')
        ->where('report.totals', ['spend' => '0.0114', 'providerCost' => '0.012123457', 'generations' => 5, 'missingPricing' => 1, 'profit' => null])
        ->where('report.providerCostsDiffer', true)
        ->has('report.days', 5)
        ->where('report.days.0.date', '2026-11-27')
        ->where('report.days.0.providerCost', '0.001000001')
        ->has('report.days.0.users', 0)
        ->where('report.days.1.date', '2026-11-24')
        ->where('report.days.1.spend', '0.0076')
        ->where('report.days.1.providerCost', '0.006123456')
        ->where('report.days.1.profit', '0.001476544')
        ->where('report.days.1.users.0.id', $ada->id)
        ->where('report.days.1.users.0.providerCost', '0.003000000')
        ->where('report.days.1.users.1.id', $bea->id)
        ->where('report.days.2.users.0.missingPricing', 1)
        ->where('report.days.2.providerCost', '0.002000000')
        ->where('report.days.2.profit', null)
        ->where('report.days.3.providerCost', '0.000000000')
        ->where('report.days.4.date', '2026-11-01')
        ->where('report.users.0.spend', '0.0076')
        ->where('report.users.0.providerCost', '0.006000000')
        ->where('report.users.1.spend', '0.0038')
        ->where('report.users.1.profit', '0.000800000')
    );
    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://pixelforge.promptedit.dev/v1/usage?start_date=2026-11-01&end_date=2026-11-30'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
});

test('marks billed costs unavailable without replacing them with local figures', function (int $status, bool $invalidCost) {
    $this->withoutVite()->actingAs(User::factory()->create(['is_admin' => true]));
    $payload = novemberUsage();
    if ($invalidCost) {
        $payload['data'][0]['cost_usd'] = 0;
    }
    fakeNovemberUsage($payload, $status);
    ImageGeneration::factory()->create(['completed_at' => '2026-11-24', 'status' => 'failed']);

    $this->get('/admin?month=2026-11')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('report.totals.providerCost', null)
        ->where('report.totals.profit', null)
        ->where('report.days.0.providerCost', null)
        ->where('report.days.0.users.0.providerCost', '0.000000000')
    );
})->with([
    'provider outage' => [503, false],
    'invalid decimal response' => [200, true],
]);

test('rejects an invalid month before running the report', function (string $query) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->get('/admin?'.$query)->assertUnprocessable();
})->with(['month=2026-13', 'month=0000-01', 'month[]=2026-11']);

/** @param array<int, array{string, int}> $days */
function novemberUsage(array $days = [], string $total = '0.000000000'): array
{
    return [
        'object' => 'usage',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
        'total' => ['cost_usd' => $total],
        'data' => array_map(function (int $day) use ($days): array {
            [$cost, $generations] = $days[$day] ?? ['0.000000000', 0];

            return ['date' => sprintf('2026-11-%02d', $day), 'cost_usd' => $cost, 'generations' => $generations];
        }, range(1, 30)),
    ];
}

function fakeNovemberUsage(array $payload, int $status = 200): void
{
    config(['inertia.ssr.enabled' => false, 'services.pixelforge.key' => 'test-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/usage?start_date=2026-11-01&end_date=2026-11-30' => Http::response($payload, $status),
    ]);
}
