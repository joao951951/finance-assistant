<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    private const PER_PAGE = 25;

    public function list(int $userId, int $page, int $perPage = self::PER_PAGE): array
    {
        $query = $this->baseQuery($userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id');

        $total = $query->count();
        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($r) => $this->map($r))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
            'next_page' => ($page * $perPage) < $total ? $page + 1 : null,
        ];
    }

    public function categoriesForUser(int $userId): array
    {
        return Category::where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color])
            ->all();
    }

    private function baseQuery(int $userId)
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

    private function map(object $r): array
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
