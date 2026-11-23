<?php

use Inertia\Testing\AssertableInertia;

test('renders the image generation workspace with size options', function () {
    $this->withoutVite();

    $response = $this->get(route('generations.create'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('ImageGeneration')
        ->has('imageSizes', 3)
        ->where('imageSizes.0.value', '1024x1024')
        ->where('submitUrl', '/app/generations')
    );
});

test('returns the accepted prompt to the workspace', function (?string $imageUrl) {
    $submission = [
        'prompt' => 'A paper moon floating over a quiet sea.',
        'size' => '1536x1024',
        'image_url' => $imageUrl,
    ];

    $response = $this->post(route('generations.store'), $submission);

    $response->assertRedirectToRoute('generations.create')
        ->assertInertiaFlash('submission', $submission);
})->with([
    'with a reference' => ['https://example.com/reference.png'],
    'without a reference' => [null],
]);

test('returns field errors without accepting invalid submissions', function (array $input, array $errors) {
    $response = $this->from(route('generations.create'))->post(route('generations.store'), $input);

    $response->assertRedirectToRoute('generations.create')
        ->assertSessionHasErrors($errors)
        ->assertInertiaFlashMissing('submission');
})->with([
    'required fields' => [[], ['prompt' => 'The prompt field is required.', 'size' => 'The image size field is required.']],
    'unsupported size and non-web URL' => [
        ['prompt' => 'Paper moon', 'size' => '32x32', 'image_url' => 'file:///reference.png'],
        ['size' => 'The selected image size is invalid.', 'image_url' => 'The reference image URL field must be a valid URL.'],
    ],
    'prompt length limit' => [
        ['prompt' => str_repeat('a', 2001), 'size' => '1024x1024'],
        ['prompt' => 'The prompt field must not be greater than 2000 characters.'],
    ],
    'reference URL length limit' => [
        ['prompt' => 'Paper moon', 'size' => '1024x1024', 'image_url' => 'https://example.com/'.str_repeat('a', 2048)],
        ['image_url' => 'The reference image URL field must not be greater than 2048 characters.'],
    ],
]);
