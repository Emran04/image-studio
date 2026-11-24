<?php

namespace App\Models;

use Database\Factories\ImageGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pixelforge_id',
    'object',
    'status',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'metadata',
    'image_url',
    'image_url_expires_at',
    'usage',
    'rates',
    'error',
    'pixelforge_created_at',
    'started_at',
    'completed_at',
])]
class ImageGeneration extends Model
{
    /** @use HasFactory<ImageGenerationFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'metadata' => 'array',
            'image_url_expires_at' => 'immutable_datetime',
            'usage' => 'array',
            'rates' => 'array',
            'error' => 'json',
            'pixelforge_created_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
