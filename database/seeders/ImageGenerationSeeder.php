<?php

namespace Database\Seeders;

use App\Models\ImageGeneration;
use Illuminate\Database\Seeder;

class ImageGenerationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ImageGeneration::factory()->create([
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting.',
            'size' => '1536x1024',
            'quality' => 'high',
        ]);
    }
}
