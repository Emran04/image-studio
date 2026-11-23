<?php

use App\Services\PixelForgeService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['services.pixelforge.key' => 'test-key']);
    Http::preventStrayRequests();
});

test('submits an authenticated generation request with reference images and metadata', function () {
    $endpoint = 'https://pixelforge.promptedit.dev/v1/generations';
    $key = '6f1c2a9e-3b7d-4e0a-9c55-2d8f1b4e7a10';
    Http::fake([$endpoint => Http::response(['id' => 'generation-1'], 202)]);

    $response = app(PixelForgeService::class)->generate(
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1536x1024',
        quality: 'high',
        referenceImages: ['https://example.com/style.png'],
        metadata: ['order_id' => '1042'],
        idempotencyKey: $key,
    );

    Http::assertSent(function (Request $request) use ($endpoint, $key): bool {
        $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);
        ksort($body);

        return $request->url() === $endpoint
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('Idempotency-Key', $key)
            && $body === [
                'metadata' => ['order_id' => '1042'],
                'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
                'quality' => 'high',
                'reference_images' => ['https://example.com/style.png'],
                'size' => '1536x1024',
            ];
    });
    expect($response->json())->toBe(['id' => 'generation-1']);
});

test('uses the provider defaults and leaves empty optional fields out of the request', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response([], 202)]);

    app(PixelForgeService::class)->generate('A paper moon');

    Http::assertSent(fn (Request $request) => Str::isUuid($request->header('Idempotency-Key')[0] ?? '')
        && $request->data() === [
            'prompt' => 'A paper moon',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);
});

test('accepts the documented limits and encodes metadata as a JSON object', function () {
    $prompt = str_repeat('é', 4000);
    $references = [
        'http://example.com/1.png',
        'https://example.com/2.png',
        'https://example.com/3.png',
        'https://example.com/4.png',
    ];
    $metadata = array_fill(0, 20, str_repeat('é', 500));
    $metadata[1] = '';
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['status' => 'queued'], 202)]);

    app(PixelForgeService::class)->generate($prompt, '1024x1536', 'low', $references, $metadata);

    Http::assertSent(function (Request $request) use ($prompt, $references, $metadata): bool {
        $body = json_decode($request->body(), flags: JSON_THROW_ON_ERROR);

        return $body->prompt === $prompt
            && $body->size === '1024x1536'
            && $body->quality === 'low'
            && $body->reference_images === $references
            && $body->metadata instanceof stdClass
            && (array) $body->metadata === $metadata;
    });
});

test('rejects invalid generation parameters before sending a request', function (array $arguments, string $field) {
    $arguments += ['prompt' => 'A paper moon'];

    expect(fn () => app(PixelForgeService::class)->generate(...$arguments))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));

    Http::assertNothingSent();
})->with([
    'empty prompt' => [['prompt' => ''], 'prompt'],
    'prompt too long' => [['prompt' => str_repeat('é', 4001)], 'prompt'],
    'unsupported size' => [['size' => '512x512'], 'size'],
    'unsupported quality' => [['quality' => 'ultra'], 'quality'],
    'too many references' => [['referenceImages' => array_fill(0, 5, 'https://example.com/style.png')], 'reference_images'],
    'references not a list' => [['referenceImages' => ['cover' => 'https://example.com/style.png']], 'reference_images'],
    'unsupported reference scheme' => [['referenceImages' => ['ftp://example.com/style.png']], 'reference_images.0'],
    'empty reference URL' => [['referenceImages' => ['']], 'reference_images.0'],
    'too many metadata entries' => [['metadata' => array_fill(0, 21, 'value')], 'metadata'],
    'non-string metadata value' => [['metadata' => ['order_id' => 1042]], 'metadata'],
    'metadata value too long' => [['metadata' => ['note' => str_repeat(' ', 501)]], 'metadata'],
]);

test('does not send a request when the API key is missing', function () {
    config(['services.pixelforge.key' => null]);

    expect(fn () => app(PixelForgeService::class)->generate('A paper moon', '1024x1024'))
        ->toThrow(RuntimeException::class, 'Configure PIXELFORGE_API_KEY');

    Http::assertNothingSent();
});

test('surfaces a provider error without resubmitting the generation', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => 'Unavailable'], 503)]);

    expect(fn () => app(PixelForgeService::class)->generate('A paper moon', '1024x1024'))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

test('surfaces a connection failure', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/generations' => Http::failedConnection()]);

    expect(fn () => app(PixelForgeService::class)->generate('A paper moon', '1024x1024'))
        ->toThrow(ConnectionException::class);
});

test('does not accept a redirect as a generation response', function () {
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response('', 302, ['Location' => 'https://example.com/']),
    ]);

    expect(fn () => app(PixelForgeService::class)->generate('A paper moon', '1024x1024'))
        ->toThrow(RuntimeException::class, 'PixelForge returned unexpected HTTP status 302.');
});
