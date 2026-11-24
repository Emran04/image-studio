<?php

namespace App\Services;

use App\Models\ImageGeneration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class PixelForgeGenerationSync
{
    public const array PENDING_STATUSES = ['queued', 'processing'];

    /** @return array<string, mixed> */
    public function attributes(mixed $payload, ?string $expectedStatus = null): array
    {
        $rules = [
            'id' => ['required', 'string', 'max:255'],
            'object' => ['required', 'in:generation'],
            'status' => ['required', 'string', Rule::in($expectedStatus === null
                ? [...self::PENDING_STATUSES, 'succeeded', 'failed']
                : [$expectedStatus])],
            'prompt' => ['required', 'string'],
            'size' => ['required', 'string', 'max:40'],
            'quality' => ['required', 'string', 'max:40'],
            'reference_images' => ['nullable', 'array', 'list'],
            'metadata' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string', 'url:https'],
            'image_url_expires_at' => ['nullable', 'date'],
            'usage' => ['nullable', 'array'],
            'rates' => ['nullable', 'array'],
            'error' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];

        if ($expectedStatus === 'succeeded') {
            $rules['image_url'][] = 'required';
            $rules['usage'][] = 'required';

            foreach (['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens', 'total_tokens'] as $tokenType) {
                $rules["usage.$tokenType"] = ['required', 'integer:strict', 'min:0'];
            }
        }

        $validator = Validator::make(is_array($payload) ? $payload : [], $rules);

        if ($validator->fails()) {
            throw new UnexpectedValueException('PixelForge returned an invalid generation payload.');
        }

        $attributes = $validator->validated();
        $attributes['pixelforge_id'] = $attributes['id'];
        $attributes['pixelforge_created_at'] = $attributes['created_at'] ?? null;
        unset($attributes['id'], $attributes['created_at']);

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    public function sync(array $attributes, ?string $eventId = null): void
    {
        DB::transaction(function () use ($attributes, $eventId): void {
            if ($eventId !== null && DB::table('pixelforge_webhook_receipts')->insertOrIgnore([
                'event_id' => $eventId,
                'received_at' => now(),
            ]) === 0) {
                return;
            }

            // An early delivery must roll back its receipt so PixelForge can retry it.
            $generation = ImageGeneration::query()
                ->where('pixelforge_id', $attributes['pixelforge_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($generation->status, self::PENDING_STATUSES, true)
                || ($generation->status === 'processing' && $attributes['status'] === 'queued')) {
                return;
            }

            $generation->fill(Arr::only($attributes, [
                'status', 'image_url', 'image_url_expires_at', 'usage', 'error', 'started_at', 'completed_at',
            ]));

            if (isset($attributes['rates']) && ($generation->rates === null
                || ($generation->status === 'succeeded' && GenerationPricing::providerCost($generation->usage, $attributes['rates']) !== null))) {
                $generation->rates = $attributes['rates'];
            }

            $reserved = $generation->credit_cost;
            $generation->credit_cost = GenerationPricing::settledCost($generation->status, $generation->usage, $generation->rates, $reserved);
            $generation->save();

            if ($reserved !== $generation->credit_cost) {
                $generation->user()->increment('credit_balance', $reserved - $generation->credit_cost);
            }
        }, attempts: 3);
    }
}
