<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        return Inertia::render('dashboard', [
            'summary'            => $this->summary($userId, $startOfMonth, $endOfMonth),
            'spendingByCategory' => $this->spendingByCategory($userId, $startOfMonth, $endOfMonth),
            'monthlyTrend'       => $this->monthlyTrend($userId, $now),
            'recentTransactions' => $this->recentTransactions($userId),
        ]);
    }

    private function summary(int $userId, Carbon $from, Carbon $to): array
    {
        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) AS total_spent,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS total_income,
                COUNT(*) AS transactions_count
            ")
            ->first();

        $spent  = (float) ($rows->total_spent ?? 0);
        $income = (float) ($rows->total_income ?? 0);

        return [
            'total_spent'         => $spent,
            'total_income'        => $income,
            'balance'             => $income - $spent,
            'transactions_count'  => (int) ($rows->transactions_count ?? 0),
            'month_label'         => $from->translatedFormat('F Y'),
        ];
    }

    private function spendingByCategory(int $userId, Carbon $from, Carbon $to): array
    {
        return Transaction::where('transactions.user_id', $userId)
            ->where('transactions.type', 'debit')
            ->whereBetween('transactions.date', [$from, $to])
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw("
                COALESCE(categories.name, 'Sem categoria') AS name,
                COALESCE(categories.color, '#94a3b8')      AS color,
                SUM(transactions.amount)                   AS total
            ")
            ->groupBy('categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->name,
                'color' => $r->color,
                'total' => (float) $r->total,
            ])
            ->all();
    }

    private function monthlyTrend(int $userId, Carbon $now): array
    {
        $from = $now->copy()->subMonths(5)->startOfMonth();

        return Transaction::where('user_id', $userId)
            ->where('date', '>=', $from)
            ->selectRaw("
                TO_CHAR(date, 'YYYY-MM')                                    AS month,
                TO_CHAR(date, 'Mon')                                        AS month_label,
                SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END)      AS spent,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END)      AS income
            ")
            ->groupByRaw("TO_CHAR(date, 'YYYY-MM'), TO_CHAR(date, 'Mon')")
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month'       => $r->month,
                'month_label' => $r->month_label,
                'spent'       => (float) $r->spent,
                'income'      => (float) $r->income,
            ])
            ->all();
    }

    private function recentTransactions(int $userId): array
    {
        return Transaction::where('transactions.user_id', $userId)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->select(
                'transactions.id',
                'transactions.date',
                'transactions.description_clean',
                'transactions.description',
                'transactions.amount',
                'transactions.type',
                DB::raw("COALESCE(categories.name, 'Sem categoria') AS category_name"),
                DB::raw("COALESCE(categories.color, '#94a3b8') AS category_color"),
            )
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'date'           => $r->date,
                'description'    => $r->description_clean ?? $r->description,
                'amount'         => (float) $r->amount,
                'type'           => $r->type,
                'category_name'  => $r->category_name,
                'category_color' => $r->category_color,
            ])
            ->all();
    }
}
