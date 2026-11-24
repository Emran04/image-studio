<?php

namespace App\Http\Controllers;

use App\Models\ImageGeneration;
use App\Services\PixelForgeGenerationSync;
use App\Services\PixelForgeService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(PixelForgeService $pixelForge): Response
    {
        return Inertia::render('ImageGeneration', [
            ...$this->workspaceLinks(),
            'imageSizes' => self::IMAGE_SIZES,
            'promptMaxLength' => PixelForgeService::PROMPT_MAX_LENGTH,
            'quality' => PixelForgeService::DEFAULT_QUALITY,
            'pricing' => fn (): ?array => $pixelForge->pricing(),
            'markupPercent' => PixelForgeService::MARKUP_PERCENT,
            'submitUrl' => route('generations.store', absolute: false),
        ]);
    }

    public function index(Request $request, PixelForgeService $pixelForge): Response
    {
        $generations = $request->user()->imageGenerations()
            ->latest()
            ->orderByDesc('id')
            ->simplePaginate(12, [
                'id', 'prompt', 'status', 'size', 'quality', 'reference_images', 'usage', 'rates',
                'image_url', 'image_url_expires_at', 'created_at',
            ]);

        $page = $generations->currentPage();

        return Inertia::render('GenerationList', [
            ...$this->workspaceLinks(),
            'pricing' => fn (): ?array => $generations->isEmpty() ? null : $pixelForge->pricing(),
            'markupPercent' => PixelForgeService::MARKUP_PERCENT,
            'generations' => $generations->through(function (ImageGeneration $generation) use ($page): array {
                $expired = $generation->image_url_expires_at?->isPast() ?? false;
                $imageUrl = $generation->status === 'succeeded' && ! $expired
                    && str_starts_with($generation->image_url ?? '', 'https://')
                    ? $generation->image_url
                    : null;

                return [
                    'id' => $generation->id,
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'referenceCount' => count($generation->reference_images ?? []),
                    'usage' => $generation->usage,
                    'rates' => $generation->rates,
                    'status' => $generation->status,
                    'imageUrl' => $imageUrl,
                    'expired' => $expired,
                    'createdAt' => $generation->created_at?->toDateString(),
                    'refreshUrl' => in_array($generation->status, PixelForgeGenerationSync::PENDING_STATUSES, true)
                        ? route('generations.refresh', ['generation' => $generation->id, 'page' => $page], absolute: false)
                        : null,
                ];
            }),
        ]);
    }

    public function store(Request $request, PixelForgeService $pixelForge, PixelForgeGenerationSync $generations): RedirectResponse
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
            $attributes = $generations->attributes($response->json());
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

    public function refresh(Request $request, string $generation, PixelForgeService $pixelForge, PixelForgeGenerationSync $generations): RedirectResponse
    {
        $ownedGenerations = $request->user()->imageGenerations();
        $record = $ownedGenerations->findOrFail($generation);
        $page = $request->integer('page', 1);
        $redirect = to_route('generations.index', $page > 1 ? ['page' => $page] : []);

        if (! in_array($record->status, PixelForgeGenerationSync::PENDING_STATUSES, true)) {
            return $redirect;
        }

        try {
            $attributes = $generations->attributes($pixelForge->retrieve($record->pixelforge_id)->json());

            if ($attributes['pixelforge_id'] !== $record->pixelforge_id) {
                throw new UnexpectedValueException('PixelForge returned the wrong generation.');
            }
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            return $redirect->withErrors(['refresh' => 'Could not check the status with PixelForge. Try again shortly.']);
        }

        $generations->sync($attributes);

        return $redirect;
    }

    /** @return array<string, string> */
    private function workspaceLinks(): array
    {
        return [
            'createUrl' => route('generations.create', absolute: false),
            'indexUrl' => route('generations.index', absolute: false),
            'logoutUrl' => route('logout', absolute: false),
        ];
    }
}
