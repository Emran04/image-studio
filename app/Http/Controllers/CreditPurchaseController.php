<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GenerationPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CreditPurchaseController extends Controller
{
    public const int DEMO_PACK_SIZE = 10 * GenerationPricing::UNITS_PER_DOLLAR;

    public function __invoke(Request $request): RedirectResponse
    {
        User::query()->whereKey($request->user()->getKey())
            ->increment('credit_balance', self::DEMO_PACK_SIZE);

        return back(fallback: route('generations.create'));
    }
}
