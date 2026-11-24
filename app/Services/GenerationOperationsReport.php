<?php

namespace App\Services;

use App\Models\ImageGeneration;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

class GenerationOperationsReport
{
    public function __construct(private PixelForgeService $pixelForge) {}

    /** @return array{totals: array, days: list<array>, users: list<array>, providerCostsDiffer: bool} */
    public function forMonth(CarbonImmutable $month): array
    {
        $totals = $this->emptyTotals();
        $days = [];
        $users = [];
        $usage = $this->pixelForge->usageForMonth($month);

        $generations = ImageGeneration::query()
            ->select('id', 'user_id', 'status', 'usage', 'rates', 'completed_at')
            ->with('user:id,name,email')
            ->where('completed_at', '>=', $month)
            ->where('completed_at', '<', $month->addMonth())
            ->whereIn('status', ['succeeded', 'failed'])
            ->lazyById();

        foreach ($generations as $generation) {
            $date = $generation->completed_at->toDateString();
            $userId = $generation->user_id;
            $account = [
                'id' => $userId,
                'name' => $generation->user->name,
                'email' => $generation->user->email,
            ];
            $days[$date] ??= ['totals' => $this->emptyTotals(), 'users' => []];
            $days[$date]['users'][$userId] ??= [...$account, ...$this->emptyTotals()];
            $users[$userId] ??= [...$account, ...$this->emptyTotals()];

            $provider = $generation->status === 'failed'
                ? BigDecimal::zero()
                : GenerationPricing::providerCost($generation->usage, $generation->rates);
            $spend = $provider === null ? null : GenerationPricing::markedUpCost($provider);

            $this->addGeneration($totals, $provider, $spend);
            $this->addGeneration($days[$date]['totals'], $provider, $spend);
            $this->addGeneration($days[$date]['users'][$userId], $provider, $spend);
            $this->addGeneration($users[$userId], $provider, $spend);
        }

        foreach ($usage['days'] ?? [] as $date => $day) {
            if ($day['generations'] > 0 || ! BigDecimal::of($day['cost_usd'])->isZero()) {
                $days[$date] ??= ['totals' => $this->emptyTotals(), 'users' => []];
            }
        }

        krsort($days);
        $monthlyProvider = $usage === null ? null : BigDecimal::of($usage['total']);
        $providerCostsDiffer = $monthlyProvider !== null && ! $monthlyProvider->isEqualTo($totals['providerCost']->toScale(9, RoundingMode::HalfUp));
        $dailyRows = [];
        foreach ($days as $date => $day) {
            $dailyProvider = $usage === null ? null : BigDecimal::of($usage['days'][$date]['cost_usd']);
            $providerCostsDiffer = $providerCostsDiffer
                || ($dailyProvider !== null && ! $dailyProvider->isEqualTo($day['totals']['providerCost']->toScale(9, RoundingMode::HalfUp)));

            $dailyRows[] = [
                'date' => $date,
                ...$this->formatTotals($day['totals'], $dailyProvider),
                'users' => $this->formatUsers($day['users']),
            ];
        }

        return [
            'totals' => $this->formatTotals($totals, $monthlyProvider),
            'days' => $dailyRows,
            'users' => $this->formatUsers($users),
            'providerCostsDiffer' => $providerCostsDiffer,
        ];
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
    private function formatTotals(array $totals, ?BigDecimal $provider): array
    {
        $provider = $provider?->toScale(9, RoundingMode::HalfUp);

        return [
            ...$totals,
            'spend' => (string) $totals['spend']->toScale(4),
            'providerCost' => $provider === null ? null : (string) $provider,
            'profit' => $provider === null || $totals['missingPricing'] > 0 ? null : (string) $totals['spend']->minus($provider),
        ];
    }

    /** @param array<int, array> $users */
    private function formatUsers(array $users): array
    {
        uasort($users, fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']) ?: $first['id'] <=> $second['id']);

        return array_values(array_map(function (array $user): array {
            $provider = $user['generations'] === $user['missingPricing'] ? null : $user['providerCost'];

            return $this->formatTotals($user, $provider);
        }, $users));
    }
}
