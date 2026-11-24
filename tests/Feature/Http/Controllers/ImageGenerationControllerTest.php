<?php

use App\Models\ImageGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['services.pixelforge.key' => 'test-key']);
    Http::preventStrayRequests();
});

function pixelForgePricingPayload(): array
{
    return [
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => ['medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650]],
            'reasoning' => ['medium' => ['min' => 200, 'max' => 2000]],
        ],
    ];
}

test('renders the image generation workspace with size options', function () {
    $this->actingAs(User::factory()->make());
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false, 'services.pixelforge.key' => null]);
    $pricingUrl = 'https://pixelforge.promptedit.dev/v1/pricing';
    Http::fake([$pricingUrl => Http::response(pixelForgePricingPayload())]);

    $response = $this->get(route('generations.create'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ImageGeneration')
        ->has('imageSizes', 3)
        ->where('imageSizes.0.value', '1024x1024')
        ->where('promptMaxLength', 4000)
        ->where('quality', 'medium')
        ->where('credits.balance', 0)
        ->where('markupPercent', 25)
        ->where('pricing.rates.reasoning_tokens', '12.50')
        ->where('pricing.tokens.output.medium.1536x1024', 1650)
        ->where('indexUrl', '/app/generations')
        ->where('submitUrl', '/app/generations')
        ->where('logoutUrl', '/logout')
    );
    Http::assertSent(fn (Request $request): bool => $request->url() === $pricingUrl
        && $request->method() === 'GET' && ! $request->hasHeader('Authorization'));
});

test('keeps the workspace usable when pricing is unavailable', function (array $body, int $status) {
    $this->actingAs(User::factory()->make());
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response($body, $status)]);

    $response = $this->get(route('generations.create'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ImageGeneration')
        ->where('pricing', null)
        ->where('submitUrl', '/app/generations')
    );
    Http::assertSentCount(1);
})->with([
    'pricing request failed' => [['error' => 'Unavailable'], 503],
    'pricing response was incomplete' => [['currency' => 'usd'], 200],
]);

test('shows the available balance separately from credits used and reserved for the signed-in user', function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelForgePricingPayload())]);
    $user = User::factory()->create(['credit_balance' => 7]);
    ImageGeneration::factory()->for($user)->create(['status' => 'succeeded', 'credit_cost' => 2]);
    ImageGeneration::factory()->for($user)->create(['status' => 'processing', 'credit_cost' => 1]);
    ImageGeneration::factory()->for($user)->create(['status' => 'failed', 'credit_cost' => 1]);
    ImageGeneration::factory()->for($user)->create(['status' => 'succeeded']);
    ImageGeneration::factory()->create(['status' => 'succeeded', 'credit_cost' => 1]);

    $response = $this->actingAs($user)->get(route('generations.create'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('credits.balance', 7)
        ->where('credits.used', 2)
        ->where('credits.reserved', 1)
    );
});

test('shows only the signed-in user’s generations, newest first, with available image links', function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    $this->freezeTime();
    $pricingUrl = 'https://pixelforge.promptedit.dev/v1/pricing';
    Http::fake([$pricingUrl => Http::response(pixelForgePricingPayload())]);
    $user = User::factory()->create();
    $expired = ImageGeneration::factory()->for($user)->create([
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.promptedit.dev/images/expired.png',
        'image_url_expires_at' => now()->subHour(),
    ]);
    $ready = ImageGeneration::factory()->for($user)->create([
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.promptedit.dev/images/ready.png',
        'image_url_expires_at' => now()->addHour(),
    ]);
    $pending = ImageGeneration::factory()->for($user)->create([
        'reference_images' => ['https://example.com/reference.png'],
        'rates' => ['output_tokens' => '41.00'],
    ]);
    ImageGeneration::factory()->create();

    $response = $this->actingAs($user)->get(route('generations.index'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('GenerationList')
        ->where('markupPercent', 25)
        ->where('pricing.rates.output_tokens', '40.00')
        ->has('generations.data', 3)
        ->where('generations.data.0.id', $pending->id)
        ->where('generations.data.0.refreshUrl', "/app/generations/{$pending->id}/refresh?page=1")
        ->where('generations.data.0.quality', 'medium')
        ->where('generations.data.0.referenceCount', 1)
        ->where('generations.data.0.rates.output_tokens', '41.00')
        ->where('generations.data.0.usage', null)
        ->where('generations.data.1.id', $ready->id)
        ->where('generations.data.1.imageUrl', $ready->image_url)
        ->where('generations.data.2.id', $expired->id)
        ->where('generations.data.2.imageUrl', null)
        ->where('generations.data.2.expired', true)
    );
    Http::assertSentCount(1);
});

test('requires sign-in to view the generation list', function () {
    $this->get(route('generations.index'))->assertRedirectToRoute('login');
});

test('checking a pending generation saves the finished image and makes usage and rates available in the list', function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    $user = User::factory()->create();
    $generation = ImageGeneration::factory()->for($user)->create();
    $endpoint = "https://pixelforge.promptedit.dev/v1/generations/{$generation->pixelforge_id}";
    $imageUrl = "https://pixelforge.promptedit.dev/images/{$generation->pixelforge_id}.png?signature=example";
    $usage = ['input_text_tokens' => 20, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 380, 'total_tokens' => 1500];
    $rates = pixelForgePricingPayload()['rates'];
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response([], 503), $endpoint => Http::response([
        'id' => $generation->pixelforge_id,
        'object' => 'generation',
        'status' => 'succeeded',
        'prompt' => $generation->prompt,
        'size' => $generation->size,
        'quality' => $generation->quality,
        'image_url' => $imageUrl,
        'image_url_expires_at' => '2026-11-25T14:03:29Z',
        'completed_at' => '2026-11-24T14:03:29Z',
        'usage' => $usage,
        'rates' => $rates,
    ])]);

    $response = $this->actingAs($user)->post(route('generations.refresh', $generation));

    $response->assertRedirectToRoute('generations.index')->assertSessionHasNoErrors();
    Http::assertSent(fn (Request $request): bool => $request->url() === $endpoint
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer test-key'));
    $this->assertDatabaseHas(ImageGeneration::class, [
        'id' => $generation->id,
        'status' => 'succeeded',
        'image_url' => $imageUrl,
        'image_url_expires_at' => '2026-11-25 14:03:29',
        'completed_at' => '2026-11-24 14:03:29',
    ]);
    $this->get(route('generations.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pricing', null)
        ->where('generations.data.0.status', 'succeeded')
        ->where('generations.data.0.usage', $usage)
        ->where('generations.data.0.rates', $rates)
        ->where('generations.data.0.refreshUrl', null)
    );
});

test('does not check another user’s generation', function () {
    $generation = ImageGeneration::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('generations.refresh', $generation))
        ->assertNotFound();
    Http::assertNothingSent();
});

test('keeps the last known status if PixelForge cannot be reached', function () {
    $user = User::factory()->create();
    $generation = ImageGeneration::factory()->for($user)->create();
    $endpoint = "https://pixelforge.promptedit.dev/v1/generations/{$generation->pixelforge_id}";
    Http::fake([$endpoint => Http::response(['error' => 'Unavailable'], 503)]);

    $response = $this->actingAs($user)->post(route('generations.refresh', $generation));

    $response->assertRedirectToRoute('generations.index')->assertSessionHasErrors('refresh');
    Http::assertSentCount(1);
    $this->assertDatabaseHas(ImageGeneration::class, ['id' => $generation->id, 'status' => 'queued']);
});

test('submits and stores a generation for the authenticated user', function (?string $imageUrl, string $prompt, int $reserved) {
    $user = User::factory()->create(['credit_balance' => $reserved]);
    $this->actingAs($user);
    $submission = [
        'prompt' => $prompt,
        'size' => '1536x1024',
        'image_url' => $imageUrl,
    ];
    $providerResponse = [
        'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => $submission['prompt'],
        'size' => '1536x1024',
        'quality' => 'medium',
        'reference_images' => $imageUrl === null ? [] : [$imageUrl],
        'metadata' => ['user_id' => (string) $user->getKey()],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => null,
        'completed_at' => null,
    ];
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelForgePricingPayload()),
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response($providerResponse, 202),
    ]);

    $response = $this->post(route('generations.store'), [
        ...$submission,
        'metadata' => ['user_id' => 'spoofed'],
    ]);

    $response->assertRedirectToRoute('generations.create')
        ->assertInertiaFlash('submission', [...$submission, 'status' => 'queued']);

    $expectedPayload = [
        'prompt' => $submission['prompt'],
        'size' => '1536x1024',
        'quality' => 'medium',
    ];
    if ($imageUrl !== null) {
        $expectedPayload['reference_images'] = [$imageUrl];
    }
    $expectedPayload['metadata'] = ['user_id' => (string) $user->getKey()];
    ksort($expectedPayload);
    Http::assertSent(function (Request $request) use ($expectedPayload): bool {
        if ($request->method() !== 'POST') {
            return false;
        }

        $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        ksort($payload);

        return $payload === $expectedPayload;
    });

    $this->assertDatabaseHas(ImageGeneration::class, [
        'user_id' => $user->getKey(),
        'credit_cost' => $reserved,
        'pixelforge_id' => $providerResponse['id'],
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => $submission['prompt'],
        'size' => '1536x1024',
        'quality' => 'medium',
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'error' => null,
        'started_at' => null,
        'completed_at' => null,
    ]);
    $this->assertDatabaseHas(User::class, ['id' => $user->id, 'credit_balance' => 0]);
    $generation = $user->imageGenerations()->sole();
    expect($generation->reference_images)->toBe($providerResponse['reference_images']);
    expect($generation->metadata)->toBe($providerResponse['metadata']);
    expect($generation->rates)->toBe($providerResponse['rates']);
    expect($generation->pixelforge_created_at->toIso8601String())->toBe('2026-11-04T14:03:11+00:00');

    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    $this->get(route('generations.create'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('credits.balance', 0)
            ->where('credits.reserved', $reserved)
        );
})->with([
    'with a reference' => ['https://example.com/reference.png', 'A paper moon floating over a quiet sea.', 1234],
    'without a reference at the prompt limit' => [null, str_repeat('🌙', 4000), 1209],
]);

test('does not report success or save a generation when PixelForge does not confirm it', function (array $body, int $status) {
    $user = User::factory()->create(['credit_balance' => 10_000]);
    $this->actingAs($user);
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelForgePricingPayload()),
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response($body, $status),
    ]);

    $response = $this->post(route('generations.store'), [
        'prompt' => 'A paper moon',
        'size' => '1024x1024',
    ]);

    $response->assertRedirectToRoute('generations.create')
        ->assertSessionHasErrors(['generation' => 'Could not confirm the image generation request with PixelForge.'])
        ->assertInertiaFlashMissing('submission');
    Http::assertSentCount(2);
    $this->assertDatabaseEmpty(ImageGeneration::class);
    $this->assertDatabaseHas(User::class, ['id' => $user->id, 'credit_balance' => 10_000]);
})->with([
    'provider error' => [['error' => 'Unavailable'], 503],
    'missing provider ID' => [[
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => 'A paper moon',
        'size' => '1024x1024',
        'quality' => 'medium',
    ], 202],
]);

test('blocks valid submissions before calling PixelForge when the balance is too low', function () {
    $user = User::factory()->create(['credit_balance' => 862]);
    $this->actingAs($user);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelForgePricingPayload())]);

    $this->post(route('generations.store'), ['prompt' => 'A paper moon', 'size' => '1024x1024'])
        ->assertRedirectToRoute('generations.create')
        ->assertSessionHasErrors(['credits' => 'Not enough credits. Buy credits to submit a prompt.']);

    Http::assertSentCount(1);
    $this->assertDatabaseEmpty(ImageGeneration::class);
    $this->assertDatabaseHas(User::class, ['id' => $user->id, 'credit_balance' => 862]);
});

test('returns the credit when PixelForge immediately reports failure', function () {
    $user = User::factory()->create(['credit_balance' => 10_000]);
    $submission = ['prompt' => 'A paper moon', 'size' => '1024x1024'];
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelForgePricingPayload()),
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response([
            ...$submission,
            'id' => 'gen_failed',
            'object' => 'generation',
            'status' => 'failed',
            'quality' => 'medium',
        ]),
    ]);

    $this->actingAs($user)->post(route('generations.store'), $submission)
        ->assertRedirectToRoute('generations.create')->assertSessionHasNoErrors();

    Http::assertSentCount(2);
    $this->assertDatabaseHas(ImageGeneration::class, ['user_id' => $user->id, 'status' => 'failed']);
    $this->assertDatabaseHas(User::class, ['id' => $user->id, 'credit_balance' => 10_000]);
});

test('requires sign-in before submitting to PixelForge', function () {
    $response = $this->post(route('generations.store'), [
        'prompt' => 'A paper moon',
        'size' => '1024x1024',
    ]);

    $response->assertRedirectToRoute('login');
    Http::assertNothingSent();
    $this->assertDatabaseEmpty(ImageGeneration::class);
});

test('returns field errors without accepting invalid submissions', function (array $input, array $errors) {
    $this->actingAs(User::factory()->make());

    $response = $this->from(route('generations.create'))->post(route('generations.store'), $input);

    $response->assertRedirectToRoute('generations.create')
        ->assertSessionHasErrors($errors)
        ->assertInertiaFlashMissing('submission');
    Http::assertNothingSent();
})->with([
    'required fields' => [[], ['prompt' => 'The prompt field is required.', 'size' => 'The image size field is required.']],
    'unsupported size and non-web URL' => [
        ['prompt' => 'Paper moon', 'size' => '32x32', 'image_url' => 'file:///reference.png'],
        ['size' => 'The selected image size is invalid.', 'image_url' => 'The reference image URL field must be a valid URL.'],
    ],
    'prompt length limit' => [
        ['prompt' => str_repeat('🌙', 4001), 'size' => '1024x1024'],
        ['prompt' => 'The prompt field must not be greater than 4000 characters.'],
    ],
    'reference URL length limit' => [
        ['prompt' => 'Paper moon', 'size' => '1024x1024', 'image_url' => 'https://example.com/'.str_repeat('a', 2048)],
        ['image_url' => 'The reference image URL field must not be greater than 2048 characters.'],
    ],
]);
