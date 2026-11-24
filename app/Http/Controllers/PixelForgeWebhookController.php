<?php

namespace App\Http\Controllers;

use App\Services\PixelForgeGenerationSync;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use JsonException;
use UnexpectedValueException;

class PixelForgeWebhookController extends Controller
{
    private const int SIGNATURE_TOLERANCE_SECONDS = 300;

    private const array GENERATION_EVENTS = [
        'generation.processing' => 'processing',
        'generation.succeeded' => 'succeeded',
        'generation.failed' => 'failed',
    ];

    public function __invoke(Request $request, PixelForgeGenerationSync $generations): Response
    {
        $secret = config('services.pixelforge.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            return response()->noContent(503);
        }

        $body = $request->getContent();
        if (! $this->validSignature($request->header('Pixelforge-Signature', ''), $body, $secret)) {
            return response()->noContent(400);
        }

        try {
            $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->noContent(400);
        }

        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'id' => ['required', 'string', 'max:255'],
            'object' => ['required', 'in:event'],
            'type' => ['required', 'string', 'max:255'],
            'data.object' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->noContent(422);
        }

        $event = $validator->validated();
        $status = self::GENERATION_EVENTS[$event['type']] ?? null;
        if ($status === null) {
            return response()->noContent();
        }

        try {
            $attributes = $generations->attributes($event['data']['object'] ?? null, $status);
            $generations->sync($attributes, $event['id']);
        } catch (UnexpectedValueException) {
            return response()->noContent(422);
        } catch (ModelNotFoundException) {
            return response()->noContent(503);
        }

        return response()->noContent();
    }

    private function validSignature(string $header, string $body, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if (isset($parts[$key])) {
                return false;
            }

            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        if (! preg_match('/\A[0-9]{1,12}\z/', $timestamp)
            || abs(now()->timestamp - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', "{$timestamp}.{$body}", $secret), $parts['v1'] ?? '');
    }
}
