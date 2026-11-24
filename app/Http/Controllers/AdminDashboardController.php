<?php

namespace App\Http\Controllers;

use App\Services\GenerationOperationsReport;
use App\Services\PixelForgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, GenerationOperationsReport $report): Response
    {
        $currentMonth = now('UTC');
        $selectedMonth = $request->query('month', $currentMonth->format('Y-m'));

        abort_unless(is_string($selectedMonth) && preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/', $selectedMonth), 422);

        $month = Date::createFromFormat('!Y-m', $selectedMonth, 'UTC')->toImmutable();

        Inertia::encryptHistory();

        return Inertia::render('AdminDashboard', [
            'report' => $report->forMonth($month),
            'month' => $selectedMonth,
            'markupPercent' => PixelForgeService::MARKUP_PERCENT,
            'reportUrl' => route('admin.index', absolute: false),
            'logoutUrl' => route('admin.logout', absolute: false),
        ]);
    }
}
