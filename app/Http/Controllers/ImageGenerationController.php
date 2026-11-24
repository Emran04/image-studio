<?php

namespace App\Http\Controllers;

use App\Services\PixelForgeService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use UnexpectedValueException;

class ImageGenerationController extends Controller
{
    private const array IMAGE_SIZES = [
        ['value' => '1024x1024', 'label' => 'Square', 'dimensions' => '1024 × 1024'],
        ['value' => '1536x1024', 'label' => 'Landscape', 'dimensions' => '1536 × 1024'],
        ['value' => '1024x1536', 'label' => 'Portrait', 'dimensions' => '1024 × 1536'],
    ];

    public function create(): Response
    {
        return Inertia::render('ImageGeneration', [
            'imageSizes' => self::IMAGE_SIZES,
            'promptMaxLength' => PixelForgeService::PROMPT_MAX_LENGTH,
            'submitUrl' => route('generations.store', absolute: false),
            'logoutUrl' => route('logout', absolute: false),
        ]);
    }

    public function store(Request $request, PixelForgeService $pixelForge): RedirectResponse
    {
        $submission = $request->validate([
            'prompt' => ['required', 'string', 'max:'.PixelForgeService::PROMPT_MAX_LENGTH],
            'size' => ['required', Rule::in(array_column(self::IMAGE_SIZES, 'value'))],
            'image_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ], attributes: ['size' => 'image size', 'image_url' => 'reference image URL']);

        $user = $request->user();
        $referenceImage = $submission['image_url'] ?? null;

        try {
            $response = $pixelForge->generate(
                prompt: $submission['prompt'],
                size: $submission['size'],
                referenceImages: $referenceImage === null ? [] : [$referenceImage],
                metadata: ['user_id' => (string) $user->getKey()],
            );
            $attributes = $this->generationAttributes($response->json());
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            return to_route('generations.create')->withErrors([
                'generation' => 'Could not confirm the image generation request with PixelForge.',
            ]);
        }

        $generation = $user->imageGenerations()->create($attributes);

        Inertia::flash('submission', [...$submission, 'status' => $generation->status]);

        return to_route('generations.create');
    }

    /** @return array<string, mixed> */
    private function generationAttributes(mixed $payload): array
    {
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'id' => ['required', 'string', 'max:255'],
            'object' => ['required', 'in:generation'],
            'status' => ['required', 'string', 'max:255'],
            'prompt' => ['required', 'string'],
            'size' => ['required', 'string', 'max:255'],
            'quality' => ['required', 'string', 'max:255'],
            'reference_images' => ['nullable', 'array', 'list'],
            'metadata' => ['nullable', 'array'],
            'image_url' => ['nullable', 'string'],
            'image_url_expires_at' => ['nullable', 'date'],
            'usage' => ['nullable', 'array'],
            'rates' => ['nullable', 'array'],
            'error' => ['nullable'],
            'created_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            throw new UnexpectedValueException('PixelForge returned an invalid generation payload.');
        }

        $attributes = $validator->validated();
        $attributes['pixelforge_id'] = $attributes['id'];
        $attributes['pixelforge_created_at'] = $attributes['created_at'] ?? null;
        unset($attributes['id'], $attributes['created_at']);

        return $attributes;
    }
}
