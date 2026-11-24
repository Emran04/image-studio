<?php

namespace App\Services;

use App\Models\ImageGeneration;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

class GenerationOperationsReport
{
    private const array TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    /** @return array{totals: array, days: list<array>, users: list<array>} */
    public function forMonth(CarbonImmutable $month): array
    {
        $totals = $this->emptyTotals();
        $days = [];
        $users = [];

        $generations = ImageGeneration::query()
            ->select('id', 'user_id', 'status', 'usage', 'rates', 'created_at')
            ->with('user:id,name,email')
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $month->addMonth())
            ->whereIn('status', ['succeeded', 'failed'])
            ->lazyById();

        foreach ($generations as $generation) {
            $date = $generation->created_at->toDateString();
            $userId = $generation->user_id;
            $account = [
                'id' => $userId,
                'name' => $generation->user->name,
                'email' => $generation->user->email,
            ];
            $days[$date] ??= ['totals' => $this->emptyTotals(), 'users' => []];
            $days[$date]['users'][$userId] ??= [...$account, ...$this->emptyTotals()];
            $users[$userId] ??= [...$account, ...$this->emptyTotals()];

            $provider = $this->providerCost($generation);
            $spend = $provider?->multipliedBy(100 + PixelForgeService::MARKUP_PERCENT)
                ->dividedBy(100, 4, RoundingMode::HalfUp);

            $this->addGeneration($totals, $provider, $spend);
            $this->addGeneration($days[$date]['totals'], $provider, $spend);
            $this->addGeneration($days[$date]['users'][$userId], $provider, $spend);
            $this->addGeneration($users[$userId], $provider, $spend);
        }

        krsort($days);

        $dailyRows = [];
        foreach ($days as $date => $day) {
            $dailyRows[] = [
                'date' => $date,
                ...$this->formatTotals($day['totals']),
                'users' => $this->formatUsers($day['users']),
            ];
        }

        return [
            'totals' => $this->formatTotals($totals),
            'days' => $dailyRows,
            'users' => $this->formatUsers($users),
        ];
    }

    private function providerCost(ImageGeneration $generation): ?BigDecimal
    {
        $cost = BigDecimal::zero();

        if ($generation->status === 'failed') {
            return $cost;
        }

        foreach (self::TOKEN_TYPES as $type) {
            $tokens = $generation->usage[$type] ?? null;
            $rate = $generation->rates[$type] ?? null;

            if (! is_int($tokens) || $tokens < 0
                || ! is_string($rate) || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,9})?\z/', $rate)) {
                return null;
            }

            $cost = $cost->plus(BigDecimal::of($rate)->multipliedBy($tokens));
        }

        return $cost->dividedByExact(1_000_000);
    }

    /** @return array{spend: BigDecimal, providerCost: BigDecimal, generations: int, missingPricing: int} */
    private function emptyTotals(): array
    {
        return ['spend' => BigDecimal::zero(), 'providerCost' => BigDecimal::zero(), 'generations' => 0, 'missingPricing' => 0];
    }

    /** @param array{spend: BigDecimal, providerCost: BigDecimal, generations: int, missingPricing: int} $totals */
    private function addGeneration(array &$totals, ?BigDecimal $provider, ?BigDecimal $spend): void
    {
        $totals['generations']++;

        if ($provider === null || $spend === null) {
            $totals['missingPricing']++;

            return;
        }

        $totals['providerCost'] = $totals['providerCost']->plus($provider);
        $totals['spend'] = $totals['spend']->plus($spend);
    }

    /** @param array{spend: BigDecimal, providerCost: BigDecimal, generations: int, missingPricing: int} $totals */
    private function formatTotals(array $totals): array
    {
        $provider = $totals['providerCost']->toScale(4, RoundingMode::HalfUp);

        return [
            ...$totals,
            'spend' => (string) $totals['spend']->toScale(4),
            'providerCost' => (string) $provider,
            'profit' => (string) $totals['spend']->minus($provider),
        ];
    }

    /** @param array<int, array> $users */
    private function formatUsers(array $users): array
    {
        uasort($users, fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']) ?: $first['id'] <=> $second['id']);

        return array_values(array_map($this->formatTotals(...), $users));
    }
}
