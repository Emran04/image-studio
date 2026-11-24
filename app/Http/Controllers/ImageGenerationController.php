<?php

namespace App\Http\Controllers;

use App\Models\ImageGeneration;
use App\Models\User;
use App\Services\GenerationPricing;
use App\Services\PixelForgeGenerationSync;
use App\Services\PixelForgeService;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function create(Request $request, PixelForgeService $pixelForge): Response
    {
        return Inertia::render('ImageGeneration', [
            ...$this->workspaceLinks(),
            'credits' => fn (): array => $this->workspaceCredits($request->user()),
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
            'credits' => fn (): array => $this->workspaceCredits($request->user()),
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
        $account = User::query()->whereKey($user->getKey());

        $pricing = $pixelForge->pricing();
        if ($pricing === null) {
            return to_route('generations.create')->withErrors([
                'generation' => 'Could not estimate the cost. Try again when pricing is available.',
            ]);
        }

        $reserved = GenerationPricing::reserve($pricing, $submission['prompt'], $submission['size'], $referenceImage !== null);

        // Reserve credits before calling PixelForge without holding a database lock during the request.
        if ((clone $account)->where('credit_balance', '>=', $reserved)->decrement('credit_balance', $reserved) === 0) {
            return to_route('generations.create')->withErrors([
                'credits' => 'Not enough credits. Buy credits to submit a prompt.',
            ]);
        }

        try {
            $response = $pixelForge->generate(
                prompt: $submission['prompt'],
                size: $submission['size'],
                referenceImages: $referenceImage === null ? [] : [$referenceImage],
                metadata: ['user_id' => (string) $user->getKey()],
            );
            $attributes = $generations->attributes($response->json());
            $attributes['rates'] ??= $pricing['rates'];
            $cost = GenerationPricing::settledCost($attributes['status'], $attributes['usage'] ?? null, $attributes['rates'], $reserved);
        } catch (HttpClientException|RuntimeException $exception) {
            $account->increment('credit_balance', $reserved);
            report($exception);

            return to_route('generations.create')->withErrors([
                'generation' => 'Could not confirm the image generation request with PixelForge.',
            ]);
        }

        $generation = DB::transaction(function () use ($user, $account, $reserved, $cost, $attributes): ImageGeneration {
            $generation = $user->imageGenerations()->make($attributes);
            $generation->credit_cost = $cost;
            $generation->save();

            if ($reserved !== $cost) {
                $account->increment('credit_balance', $reserved - $cost);
            }

            return $generation;
        }, attempts: 3);

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
            $generations->sync($attributes);
        } catch (HttpClientException|RuntimeException $exception) {
            report($exception);

            return $redirect->withErrors(['refresh' => 'Could not check the status with PixelForge. Try again shortly.']);
        }

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

    /** @return array{balance: int, used: int, reserved: int, packSize: int, purchaseUrl: string} */
    private function workspaceCredits(User $user): array
    {
        $account = User::query()
            ->select(['id', 'credit_balance'])
            ->withSum([
                'imageGenerations as credits_used' => fn ($query) => $query->where('status', 'succeeded'),
                'imageGenerations as credits_reserved' => fn ($query) => $query->whereIn('status', PixelForgeGenerationSync::PENDING_STATUSES),
            ], 'credit_cost')
            ->find($user->getKey());

        return [
            'balance' => $account?->credit_balance ?? 0,
            'used' => (int) $account?->credits_used,
            'reserved' => (int) $account?->credits_reserved,
            'packSize' => CreditPurchaseController::DEMO_PACK_SIZE,
            'purchaseUrl' => route('credits.store', absolute: false),
        ];
    }
}
