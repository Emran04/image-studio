<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use UnexpectedValueException;

class PixelForgeService
{
    public const int PROMPT_MAX_LENGTH = 4000;

    public const string DEFAULT_QUALITY = 'medium';

    public const int MARKUP_PERCENT = 25;

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
        string $quality = self::DEFAULT_QUALITY,
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

    /** @return array{total: string, days: array<string, array{cost_usd: string, generations: int}>}|null */
    public function usageForMonth(CarbonImmutable $month): ?array
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();
        $money = ['required', 'string', 'regex:/\A[0-9]+\.[0-9]{9}\z/'];

        try {
            $payload = $this->successfulResponse($this->client()
                ->connectTimeout(2)
                ->timeout(4)
                ->get(self::BASE_URL.'/usage', ['start_date' => $start, 'end_date' => $end]))->json();

            $validator = Validator::make(is_array($payload) ? $payload : [], [
                'object' => ['required', 'in:usage'],
                'start_date' => ['required', Rule::in([$start])],
                'end_date' => ['required', Rule::in([$end])],
                'total.cost_usd' => $money,
                'data' => ['required', 'array', 'list', 'size:'.$month->daysInMonth],
                'data.*.date' => ['required', 'date_format:Y-m-d', 'distinct', 'after_or_equal:'.$start, 'before_or_equal:'.$end],
                'data.*.cost_usd' => $money,
                'data.*.generations' => ['required', 'integer:strict', 'min:0'],
            ]);

            if ($validator->fails()) {
                throw new UnexpectedValueException('PixelForge returned invalid monthly usage data.');
            }

            $usage = $validator->validated();
            $days = [];
            foreach ($usage['data'] as $day) {
                $days[$day['date']] = [
                    'cost_usd' => $day['cost_usd'],
                    'generations' => $day['generations'],
                ];
            }

            return ['total' => $usage['total']['cost_usd'], 'days' => $days];
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public function pricing(): ?array
    {
        return Cache::remember('pixelforge.pricing', now()->addMinutes(10), function (): ?array {
            try {
                $payload = $this->successfulResponse($this->publicClient()
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->get(self::BASE_URL.'/pricing'))->json();

                return $this->pricingAttributes($payload);
            } catch (HttpClientException|RuntimeException $exception) {
                report($exception);

                return null;
            }
        });
    }

    /** @return array<string, mixed> */
    private function pricingAttributes(mixed $payload): array
    {
        $quality = self::DEFAULT_QUALITY;
        $rateRules = ['required', 'string', 'regex:/\A[0-9]+(?:\.[0-9]{1,9})?\z/'];
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'currency' => ['required', 'in:usd'],
            'unit' => ['required', 'in:per_1m_tokens'],
            'rates.input_text_tokens' => $rateRules,
            'rates.input_image_tokens' => $rateRules,
            'rates.output_tokens' => $rateRules,
            'rates.reasoning_tokens' => $rateRules,
            'tokens.characters_per_text_token' => ['required', 'integer:strict', 'min:1'],
            'tokens.text_token_variance' => ['required', 'numeric:strict', 'between:0,1'],
            'tokens.per_reference_image' => ['required', 'integer:strict', 'min:0'],
            "tokens.output.$quality.1024x1024" => ['required', 'integer:strict', 'min:0'],
            "tokens.output.$quality.1536x1024" => ['required', 'integer:strict', 'min:0'],
            "tokens.output.$quality.1024x1536" => ['required', 'integer:strict', 'min:0'],
            "tokens.reasoning.$quality.min" => ['required', 'integer:strict', 'min:0'],
            "tokens.reasoning.$quality.max" => ['required', 'integer:strict', "gte:tokens.reasoning.$quality.min"],
        ]);

        if ($validator->fails()) {
            throw new UnexpectedValueException('PixelForge returned invalid pricing data.');
        }

        return $validator->validated();
    }

    private function client(): PendingRequest
    {
        $apiKey = config('services.pixelforge.key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('Configure PIXELFORGE_API_KEY before requesting image generation.');
        }

        return $this->publicClient()->withToken($apiKey);
    }

    private function publicClient(): PendingRequest
    {
        return $this->http->acceptJson()
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
