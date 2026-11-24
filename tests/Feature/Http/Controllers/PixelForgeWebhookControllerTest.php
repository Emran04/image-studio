<?php

use App\Models\ImageGeneration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_test']);
    $this->freezeTime();
});

function pixelForgeEvent(string $eventId, string $generationId, string $status, array $attributes = []): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => "generation.$status",
        'created_at' => now()->toIso8601String(),
        'data' => [
            'object' => [
                'id' => $generationId,
                'object' => 'generation',
                'status' => $status,
                'prompt' => 'A paper moon',
                'size' => '1024x1024',
                'quality' => 'medium',
                ...$attributes,
            ],
        ],
    ];
}

function deliverPixelForgeEvent(
    TestCase $test,
    array|string $payload,
    ?int $timestamp = null,
    ?string $secret = 'whsec_test',
    string $bodySuffix = '',
): TestResponse {
    $body = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    $timestamp ??= now()->timestamp;
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($secret !== null) {
        $digest = hash_hmac('sha256', "$timestamp.$body", $secret);
        $headers['HTTP_PIXELFORGE_SIGNATURE'] = "t=$timestamp,v1=$digest";
    }

    return $test->call('POST', '/webhooks/pixelforge', [], [], [], $headers, $body.$bodySuffix);
}

test('accepts a signed dashboard ping without a browser session', function () {
    deliverPixelForgeEvent($this, ['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping'])
        ->assertNoContent();

    $this->assertDatabaseEmpty('pixelforge_webhook_receipts');
});

test('rejects an invalid signed delivery with 400 before changing any records', function (int $offset, ?string $secret, string $suffix) {
    $generation = ImageGeneration::factory()->create();

    deliverPixelForgeEvent($this, pixelForgeEvent('evt_processing', $generation->pixelforge_id, 'processing'), now()->timestamp + $offset, $secret, $suffix)
        ->assertBadRequest();

    expect($generation->refresh()->status)->toBe('queued');
    $this->assertDatabaseEmpty('pixelforge_webhook_receipts');
})->with([
    'missing signature' => [0, null, ''],
    'wrong secret' => [0, 'whsec_wrong', ''],
    'raw body changed' => [0, 'whsec_test', "\n"],
    'stale signature' => [-301, 'whsec_test', ''],
    'future signature' => [301, 'whsec_test', ''],
]);

test('fails closed with 503 when the signing secret is not configured', function () {
    config(['services.pixelforge.webhook_secret' => null]);

    deliverPixelForgeEvent($this, ['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping'])
        ->assertServiceUnavailable();
});

test('rejects malformed signed JSON with 400', function () {
    deliverPixelForgeEvent($this, '{')->assertBadRequest();
});

test('rejects a signed generation event with 422 when the status conflicts or usage is missing', function (array $attributes) {
    $generation = ImageGeneration::factory()->create();

    deliverPixelForgeEvent($this, pixelForgeEvent('evt_invalid', $generation->pixelforge_id, 'succeeded', $attributes))
        ->assertUnprocessable();

    expect($generation->refresh()->status)->toBe('queued');
    $this->assertDatabaseEmpty('pixelforge_webhook_receipts');
})->with([
    'conflicting status' => [['status' => 'failed']],
    'missing usage' => [['image_url' => 'https://example.com/generated.png']],
]);

test('records a processing event only once even when the retry is signed again', function () {
    $generation = ImageGeneration::factory()->create();
    $event = pixelForgeEvent('evt_processing', $generation->pixelforge_id, 'processing', [
        'started_at' => now()->toIso8601String(),
    ]);

    deliverPixelForgeEvent($this, $event)->assertNoContent();
    $this->assertDatabaseHas(ImageGeneration::class, [
        'id' => $generation->id, 'status' => 'processing', 'started_at' => now()->toDateTimeString(),
    ]);
    $updatedAt = $generation->refresh()->updated_at;

    $this->travel(10)->seconds();
    deliverPixelForgeEvent($this, $event)->assertNoContent();

    expect($generation->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
    $this->assertDatabaseCount('pixelforge_webhook_receipts', 1);
});

test('saves successful image and token usage without letting later events overwrite terminal state or ownership', function () {
    $generation = ImageGeneration::factory()->create();
    $usage = ['input_text_tokens' => 12, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 388, 'total_tokens' => 1500];
    $rates = ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'];
    $imageUrl = 'https://pixelforge.promptedit.dev/images/generated.png?signature=example';
    $event = pixelForgeEvent('evt_succeeded', $generation->pixelforge_id, 'succeeded', [
        'usage' => $usage,
        'rates' => $rates,
        'image_url' => $imageUrl,
        'image_url_expires_at' => now()->addDay()->toIso8601String(),
        'completed_at' => now()->toIso8601String(),
        'metadata' => ['user_id' => (string) ($generation->user_id + 1)],
    ]);

    deliverPixelForgeEvent($this, $event)->assertNoContent();
    deliverPixelForgeEvent($this, pixelForgeEvent('evt_late', $generation->pixelforge_id, 'processing'))->assertNoContent();
    deliverPixelForgeEvent($this, pixelForgeEvent('evt_conflict', $generation->pixelforge_id, 'failed'))->assertNoContent();

    $this->assertDatabaseHas(ImageGeneration::class, [
        'id' => $generation->id,
        'status' => 'succeeded',
        'image_url' => $imageUrl,
        'image_url_expires_at' => now()->addDay()->toDateTimeString(),
        'completed_at' => now()->toDateTimeString(),
        'user_id' => $generation->user_id,
        'prompt' => $generation->prompt,
    ]);
    expect($generation->refresh()->usage)->toEqual($usage);
    expect($generation->rates)->toBe($rates);
    $this->assertDatabaseCount('pixelforge_webhook_receipts', 3);
});

test('records a failed generation and its error', function () {
    $generation = ImageGeneration::factory()->create(['status' => 'processing']);
    $error = ['code' => 'generation_failed', 'message' => 'No image was generated.'];

    deliverPixelForgeEvent($this, pixelForgeEvent('evt_failed', $generation->pixelforge_id, 'failed', ['error' => $error]))
        ->assertNoContent();

    expect($generation->refresh()->status)->toBe('failed');
    expect($generation->error)->toBe($error);
    expect($generation->usage)->toBeNull();
});

test('returns 503 for an early event and applies the same event after the generation is saved', function () {
    $event = pixelForgeEvent('evt_early', 'gen_early', 'processing');

    deliverPixelForgeEvent($this, $event)->assertServiceUnavailable();
    $this->assertDatabaseEmpty('pixelforge_webhook_receipts');
    $this->assertDatabaseEmpty(ImageGeneration::class);

    $generation = ImageGeneration::factory()->create(['pixelforge_id' => 'gen_early']);

    deliverPixelForgeEvent($this, $event)->assertNoContent();

    expect($generation->refresh()->status)->toBe('processing');
    $this->assertDatabaseHas('pixelforge_webhook_receipts', ['event_id' => 'evt_early']);
});

test('a manual status check cannot overwrite a terminal webhook delivered during the provider request', function () {
    config(['services.pixelforge.key' => 'test-key']);
    $generation = ImageGeneration::factory()->create();
    $failure = pixelForgeEvent('evt_failed', $generation->pixelforge_id, 'failed', [
        'error' => ['code' => 'generation_failed'],
    ]);
    $processing = pixelForgeEvent('evt_processing', $generation->pixelforge_id, 'processing');
    Http::preventStrayRequests();
    Http::fake([
        "https://pixelforge.promptedit.dev/v1/generations/{$generation->pixelforge_id}" => function () use ($failure, $processing) {
            deliverPixelForgeEvent($this, $failure)->assertNoContent();

            return Http::response($processing['data']['object']);
        },
    ]);

    $this->actingAs($generation->user)->post(route('generations.refresh', $generation))
        ->assertRedirectToRoute('generations.index');

    expect($generation->refresh()->status)->toBe('failed');
    Http::assertSentCount(1);
});
