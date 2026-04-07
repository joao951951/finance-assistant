<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function summary(int $userId, Carbon $from, Carbon $to): array
    {
        $rows = Transaction::where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw("
                SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) AS total_spent,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS total_income,
                COUNT(*) AS transactions_count
            ")
            ->first();

        $spent = (float) ($rows->total_spent ?? 0);
        $income = (float) ($rows->total_income ?? 0);

        return [
            'total_spent' => $spent,
            'total_income' => $income,
            'balance' => $income - $spent,
            'transactions_count' => (int) ($rows->transactions_count ?? 0),
            'month_label' => $from->translatedFormat('F Y'),
        ];
    }

    public function spendingByCategory(int $userId, Carbon $from, Carbon $to): array
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
                'name' => $r->name,
                'color' => $r->color,
                'total' => (float) $r->total,
            ])
            ->all();
    }

    public function trend(int $userId, Carbon $from, Carbon $to): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $rows = DB::select("
            SELECT
                TO_CHAR(d.day, 'YYYY-MM-DD')                                                      AS period,
                TO_CHAR(d.day, 'DD')                                                              AS label,
                COALESCE(SUM(CASE WHEN t.type = 'debit'  THEN t.amount ELSE 0 END), 0)           AS spent,
                COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.amount ELSE 0 END), 0)           AS income
            FROM generate_series(?::date, ?::date, '1 day'::interval) AS d(day)
            LEFT JOIN transactions t
                   ON t.date = d.day
                  AND t.user_id = ?
            GROUP BY d.day
            ORDER BY d.day
        ", [$from->toDateString(), $to->toDateString(), $userId]);

        return array_map(fn ($r) => [
            'period' => $r->period,
            'label' => $r->label,
            'spent' => (float) $r->spent,
            'income' => (float) $r->income,
        ], $rows);
    }

    public function monthTransactions(int $userId, Carbon $from, Carbon $to): array
    {
        return $this->transactionsWithCategory($userId)
            ->whereBetween('transactions.date', [$from, $to])
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->get()
            ->map(fn ($r) => $this->mapTransaction($r))
            ->all();
    }

    public function recentTransactions(int $userId, int $limit = 10): array
    {
        return $this->transactionsWithCategory($userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => $this->mapTransaction($r))
            ->all();
    }

    public function availableMonths(int $userId): array
    {
        $expr = DB::getDriverName() === 'pgsql'
            ? "TO_CHAR(date, 'YYYY-MM')"
            : "strftime('%Y-%m', date)";

        return Transaction::where('user_id', $userId)
            ->selectRaw("{$expr} AS value")
            ->groupByRaw($expr)
            ->orderByDesc('value')
            ->limit(24)
            ->get()
            ->map(fn ($r) => [
                'value' => $r->value,
                'label' => Carbon::createFromFormat('Y-m', $r->value)->translatedFormat('F Y'),
            ])
            ->all();
    }

    private function transactionsWithCategory(int $userId)
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
            );
    }

    private function mapTransaction(object $r): array
    {
        return [
            'id' => $r->id,
            'date' => $r->date,
            'description' => $r->description_clean ?? $r->description,
            'amount' => (float) $r->amount,
            'type' => $r->type,
            'category_name' => $r->category_name,
            'category_color' => $r->category_color,
        ];
    }
}
