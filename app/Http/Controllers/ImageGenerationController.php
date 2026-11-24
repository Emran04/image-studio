<?php

namespace App\Http\Controllers;

use App\Models\ImageGeneration;
use App\Services\PixelForgeService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use UnexpectedValueException;

class ImageGenerationController extends Controller
{
    private const array PENDING_STATUSES = ['queued', 'processing'];

    private const array IMAGE_SIZES = [
        ['value' => '1024x1024', 'label' => 'Square', 'dimensions' => '1024 × 1024'],
        ['value' => '1536x1024', 'label' => 'Landscape', 'dimensions' => '1536 × 1024'],
        ['value' => '1024x1536', 'label' => 'Portrait', 'dimensions' => '1024 × 1536'],
    ];

    public function create(): Response
    {
        return Inertia::render('ImageGeneration', [
            ...$this->workspaceLinks(),
            'imageSizes' => self::IMAGE_SIZES,
            'promptMaxLength' => PixelForgeService::PROMPT_MAX_LENGTH,
            'submitUrl' => route('generations.store', absolute: false),
        ]);
    }

    public function index(Request $request): Response
    {
        $generations = $request->user()->imageGenerations()
            ->latest()
            ->orderByDesc('id')
            ->simplePaginate(12, ['id', 'prompt', 'status', 'size', 'image_url', 'image_url_expires_at', 'created_at']);

        $page = $generations->currentPage();

        return Inertia::render('GenerationList', [
            ...$this->workspaceLinks(),
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
                    'status' => $generation->status,
                    'imageUrl' => $imageUrl,
                    'expired' => $expired,
                    'createdAt' => $generation->created_at?->toDateString(),
                    'refreshUrl' => in_array($generation->status, self::PENDING_STATUSES, true)
                        ? route('generations.refresh', ['generation' => $generation->id, 'page' => $page], absolute: false)
                        : null,
                ];
            }),
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

    public function refresh(Request $request, string $generation, PixelForgeService $pixelForge): RedirectResponse
    {
        $ownedGenerations = $request->user()->imageGenerations();
        $record = $ownedGenerations->findOrFail($generation);
        $page = $request->integer('page', 1);
        $redirect = to_route('generations.index', $page > 1 ? ['page' => $page] : []);

        if (! in_array($record->status, self::PENDING_STATUSES, true)) {
            return $redirect;
        }

        try {
            $attributes = $this->generationAttributes($pixelForge->retrieve($record->pixelforge_id)->json());

            if ($attributes['pixelforge_id'] !== $record->pixelforge_id) {
                throw new UnexpectedValueException('PixelForge returned the wrong generation.');
            }
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            return $redirect->withErrors(['refresh' => 'Could not check the status with PixelForge. Try again shortly.']);
        }

        if ($record->status === 'processing' && $attributes['status'] === 'queued') {
            return $redirect;
        }

        $record->fill(Arr::only($attributes, [
            'status', 'image_url', 'image_url_expires_at', 'usage', 'error', 'started_at', 'completed_at',
        ]));

        $ownedGenerations->whereKey($record->getKey())->where('status', $record->getOriginal('status'))
            ->update($record->getDirty());

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

    /** @return array<string, mixed> */
    private function generationAttributes(mixed $payload): array
    {
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'id' => ['required', 'string', 'max:255'],
            'object' => ['required', 'in:generation'],
            'status' => ['required', 'string', Rule::in(['queued', 'processing', 'succeeded', 'failed'])],
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
