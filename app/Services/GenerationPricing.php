<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use UnexpectedValueException;

class GenerationPricing
{
    public const int UNITS_PER_DOLLAR = 10_000;

    private const array TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /** @param array<string, mixed> $pricing */
    public static function reserve(array $pricing, string $prompt, string $size, bool $hasReference): int
    {
        $tokens = $pricing['tokens'];
        $quality = PixelForgeService::DEFAULT_QUALITY;
        $typicalTextTokens = ceil(mb_strlen($prompt) / $tokens['characters_per_text_token']);

        $providerCost = self::providerCost([
            'input_text_tokens' => (int) ceil($typicalTextTokens * (1 + $tokens['text_token_variance'])),
            'input_image_tokens' => $hasReference ? $tokens['per_reference_image'] : 0,
            'output_tokens' => $tokens['output'][$quality][$size],
            'reasoning_tokens' => $tokens['reasoning'][$quality]['max'],
        ], $pricing['rates']);

        return self::usdUnits($providerCost);
    }

    public static function settledCost(string $status, ?array $usage, ?array $rates, int $reserved): int
    {
        if ($status === 'failed') {
            return 0;
        }

        if ($status !== 'succeeded') {
            return $reserved;
        }

        $cost = self::providerCost($usage, $rates);

        if ($cost === null && $reserved > 0) {
            throw new UnexpectedValueException('PixelForge returned a successful generation without billable usage and rates.');
        }

        return $cost === null ? 0 : self::usdUnits($cost);
    }

    public static function providerCost(?array $usage, ?array $rates): ?BigDecimal
    {
        $cost = BigDecimal::zero();

        foreach (self::TOKEN_TYPES as $type) {
            $tokens = $usage[$type] ?? null;
            $rate = $rates[$type] ?? null;

            if (! is_int($tokens) || $tokens < 0
                || ! is_string($rate) || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,9})?\z/', $rate)) {
                return null;
            }

            $cost = $cost->plus(BigDecimal::of($rate)->multipliedBy($tokens));
        }

        return $cost->dividedByExact(1_000_000);
    }

    public static function markedUpCost(BigDecimal $providerCost): BigDecimal
    {
        return $providerCost->multipliedBy(100 + PixelForgeService::MARKUP_PERCENT)
            ->dividedBy(100, 4, RoundingMode::HalfUp);
    }

    public static function usdUnits(BigDecimal $providerCost): int
    {
        return self::markedUpCost($providerCost)->multipliedBy(self::UNITS_PER_DOLLAR)->toInt();
    }
}
