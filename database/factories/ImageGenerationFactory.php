<?php

namespace Database\Factories;

use App\Models\ImageGeneration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImageGeneration>
 */
class ImageGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pixelforge_id' => 'gen_'.Str::ulid(),
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => fake()->sentence(),
            'size' => '1024x1024',
            'quality' => 'medium',
            'pixelforge_created_at' => now(),
        ];
    }
}
