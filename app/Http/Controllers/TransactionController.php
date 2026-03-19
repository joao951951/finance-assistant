<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $page   = max(1, (int) $request->query('page', 1));

        $query = Transaction::where('transactions.user_id', $userId)
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
            ->orderByDesc('transactions.id');

        $total = $query->count();
        $items = $query
            ->offset(($page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE)
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

        return Inertia::render('transactions/index', [
            'transactions' => $items,
            'current_page' => $page,
            'has_more'     => ($page * self::PER_PAGE) < $total,
            'next_page'    => ($page * self::PER_PAGE) < $total ? $page + 1 : null,
            'total'        => $total,
        ]);
    }
}
