<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PixelForgeService
{
    public const int PROMPT_MAX_LENGTH = 4000;

    private const string BASE_URL = 'https://pixelforge.promptedit.dev/v1';

    public function __construct(private Factory $http) {}

    /**
     * Submit an image generation request. Reuse the same idempotency key when retrying the same request.
     *
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     * @throws ValidationException
     */
    public function generate(
        string $prompt,
        string $size = '1024x1024',
        string $quality = 'medium',
        array $referenceImages = [],
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): Response {
        $request = $this->client();

        $payload = Validator::make([
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
            'metadata' => $metadata,
        ], [
            'prompt' => ['required', 'string', 'max:'.self::PROMPT_MAX_LENGTH],
            'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['array', 'list', 'max:4'],
            'reference_images.*' => ['bail', 'required', 'string', 'url:http,https'],
            'metadata' => ['bail', 'array', 'max:20', function (string $attribute, array $values, Closure $fail): void {
                foreach ($values as $value) {
                    if (! is_string($value) || mb_strlen($value, 'UTF-8') > 500) {
                        $fail('Each metadata value must be a string of at most 500 characters.');

                        return;
                    }
                }
            }],
        ])->validate();

        if ($referenceImages === []) {
            unset($payload['reference_images']);
        }

        if ($metadata === []) {
            unset($payload['metadata']);
        } else {
            $payload['metadata'] = (object) $metadata;
        }

        return $this->successfulResponse($request->asJson()
            ->withHeader('Idempotency-Key', $idempotencyKey ?? (string) Str::uuid())
            ->timeout(60)
            ->post(self::BASE_URL.'/generations', $payload));
    }

    public function retrieve(string $pixelforgeId): Response
    {
        return $this->successfulResponse($this->client()
            ->timeout(8)
            ->get(self::BASE_URL.'/generations/'.rawurlencode($pixelforgeId)));
    }

    private function client(): PendingRequest
    {
        $apiKey = config('services.pixelforge.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Configure PIXELFORGE_API_KEY before requesting image generation.');
        }

        return $this->http->acceptJson()
            ->withToken($apiKey)
            ->connectTimeout(5)
            ->withoutRedirecting();
    }

    private function successfulResponse(Response $response): Response
    {
        $response->throw();
        if (! $response->successful()) {
            throw new RuntimeException("PixelForge returned unexpected HTTP status {$response->status()}.");
        }

        return $response;
    }
}
