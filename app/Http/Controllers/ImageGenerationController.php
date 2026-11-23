<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
            'submitUrl' => route('generations.store', absolute: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $submission = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'size' => ['required', Rule::in(array_column(self::IMAGE_SIZES, 'value'))],
            'image_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ], attributes: ['size' => 'image size', 'image_url' => 'reference image URL']);

        Inertia::flash('submission', $submission);

        return to_route('generations.create');
    }
}
