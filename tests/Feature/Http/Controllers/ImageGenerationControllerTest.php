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

test('renders the image generation workspace with size options', function () {
    $this->actingAs(User::factory()->make());
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);

    $response = $this->get(route('generations.create'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ImageGeneration')
        ->has('imageSizes', 3)
        ->where('imageSizes.0.value', '1024x1024')
        ->where('promptMaxLength', 4000)
        ->where('submitUrl', '/app/generations')
        ->where('logoutUrl', '/logout')
    );
});

test('submits and stores a generation for the authenticated user', function (?string $imageUrl, string $prompt) {
    $user = User::factory()->create();
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
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response($providerResponse, 202)]);

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
        $payload = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        ksort($payload);

        return $payload === $expectedPayload;
    });

    $this->assertDatabaseHas(ImageGeneration::class, [
        'user_id' => $user->getKey(),
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
    $generation = $user->imageGenerations()->sole();
    expect($generation->reference_images)->toBe($providerResponse['reference_images']);
    expect($generation->metadata)->toBe($providerResponse['metadata']);
    expect($generation->rates)->toBe($providerResponse['rates']);
    expect($generation->pixelforge_created_at->toIso8601String())->toBe('2026-11-04T14:03:11+00:00');
})->with([
    'with a reference' => ['https://example.com/reference.png', 'A paper moon floating over a quiet sea.'],
    'without a reference at the prompt limit' => [null, str_repeat('🌙', 4000)],
]);

test('does not report success or save a generation when PixelForge does not confirm it', function (array $body, int $status) {
    $this->actingAs(User::factory()->create());
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response($body, $status)]);

    $response = $this->post(route('generations.store'), [
        'prompt' => 'A paper moon',
        'size' => '1024x1024',
    ]);

    $response->assertRedirectToRoute('generations.create')
        ->assertSessionHasErrors(['generation' => 'Could not confirm the image generation request with PixelForge.'])
        ->assertInertiaFlashMissing('submission');
    Http::assertSentCount(1);
    $this->assertDatabaseEmpty(ImageGeneration::class);
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
