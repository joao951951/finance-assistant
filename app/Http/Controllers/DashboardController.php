<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;
        $availableMonths = $this->dashboard->availableMonths($userId);

        $defaultMonth = $availableMonths[0]['value'] ?? Carbon::now()->format('Y-m');
        $selectedDate = $this->parseMonthParam($request->query('month'), $defaultMonth);
        $from = $selectedDate->copy()->startOfMonth();
        $to = $selectedDate->copy()->endOfMonth();

        return Inertia::render('dashboard', [
            'summary' => $this->dashboard->summary($userId, $from, $to),
            'spendingByCategory' => $this->dashboard->spendingByCategory($userId, $from, $to),
            'monthTransactions' => $this->dashboard->monthTransactions($userId, $from, $to),
            'trend' => $this->dashboard->trend($userId, $from, $to),
            'recentTransactions' => $this->dashboard->recentTransactions($userId),
            'selectedMonth' => $selectedDate->format('Y-m'),
            'availableMonths' => $availableMonths,
        ]);
    }

    private function parseMonthParam(?string $month, string $default): Carbon
    {
        $target = ($month && preg_match('/^\d{4}-\d{2}$/', $month)) ? $month : $default;

        try {
            return Carbon::createFromFormat('Y-m', $target)->startOfMonth();
        } catch (\Exception) {
            return Carbon::now()->startOfMonth();
        }
    }
}
