<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->transactions->list($userId, $page);

        return Inertia::render('transactions/index', [
            'transactions' => $result['items'],
            'current_page' => $page,
            'has_more' => $result['has_more'],
            'next_page' => $result['next_page'],
            'total' => $result['total'],
            'categories' => $this->transactions->categoriesForUser($userId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:debit,credit'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $request->user()->id)],
        ]);

        Transaction::create([
            'user_id' => $request->user()->id,
            'date' => $data['date'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'category_id' => $data['category_id'] ?? null,
            'raw_import_id' => null,
        ]);

        return back();
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);
        $transaction->delete();

        return back();
    }
}
