<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategorizationService
{
    /**
     * How many uncategorized transactions to send to AI in one batch.
     */
    private const BATCH_SIZE = 50;

    public function __construct(private readonly OpenAiService $openAi) {}

    /**
     * Categorize all uncategorized transactions for a given user.
     * Strategy: keyword match first, then AI for the remainder.
     */
    public function categorizeForUser(int $userId, int $rawImportId): void
    {
        /** @var Collection<int, Category> $categories */
        $categories = Category::where('user_id', $userId)->get();

        $uncategorized = Transaction::where('user_id', $userId)
            ->where('raw_import_id', $rawImportId)
            ->whereNull('category_id')
            ->get();

        if ($uncategorized->isEmpty()) {
            return;
        }

        // Pass 1: keyword matching (fast, free) — only if user has categories
        $stillUncategorized = $categories->isNotEmpty()
            ? $this->applyKeywordMatching($uncategorized, $categories)
            : $uncategorized;

        // Pass 2: AI categorization for what remains
        if ($stillUncategorized->isNotEmpty()) {
            $this->applyAiCategorization($stillUncategorized, $categories, $userId);
        }
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Transaction> Transactions that didn't match any keyword
     */
    private function applyKeywordMatching(Collection $transactions, Collection $categories): Collection
    {
        $unmatched = collect();

        foreach ($transactions as $transaction) {
            $matched = false;
            $descUpper = mb_strtoupper($transaction->description_clean ?? $transaction->description);

            foreach ($categories as $category) {
                foreach ($category->keywords as $keyword) {
                    if (Str::contains($descUpper, mb_strtoupper($keyword))) {
                        $transaction->update(['category_id' => $category->id]);
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (! $matched) {
                $unmatched->push($transaction);
            }
        }

        return $unmatched;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @param  Collection<int, Category>  $categories
     */
    private function applyAiCategorization(Collection $transactions, Collection $categories, int $userId): void
    {
        $categoryNames = $categories->pluck('name')->all();
        // Build name → id map (mutable — new categories added during processing)
        $nameToId = $categories->pluck('id', 'name')->all();

        // Process in batches to stay within token limits
        foreach ($transactions->chunk(self::BATCH_SIZE) as $batch) {
            $payload = $batch->map(fn (Transaction $t) => [
                'id' => $t->id,
                'description' => $t->description_clean ?? $t->description,
            ])->values()->all();

            $results = $this->openAi->categorizeTransactions($payload, $categoryNames);

            foreach ($results as $transactionId => $categoryName) {
                $categoryName = trim($categoryName);

                if (! isset($nameToId[$categoryName])) {
                    // GPT suggested a new (or "Desconhecido") category — create it
                    $color = $categoryName === 'Desconhecido'
                        ? '#94a3b8'
                        : $this->randomColor();

                    $newCategory = Category::firstOrCreate(
                        ['user_id' => $userId, 'name' => $categoryName],
                        ['color' => $color, 'keywords' => []],
                    );

                    $nameToId[$categoryName] = $newCategory->id;
                    $categoryNames[] = $categoryName;
                }

                Transaction::where('id', $transactionId)
                    ->update(['category_id' => $nameToId[$categoryName]]);
            }
        }
    }

    /**
     * Returns a random color from a curated financial palette.
     */
    private function randomColor(): string
    {
        $colors = [
            '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
            '#f97316', '#eab308', '#22c55e', '#14b8a6',
            '#0ea5e9', '#64748b',
        ];

        return $colors[array_rand($colors)];
    }
}
