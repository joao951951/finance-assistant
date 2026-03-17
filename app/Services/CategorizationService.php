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

        if ($categories->isEmpty()) {
            return;
        }

        $uncategorized = Transaction::where('user_id', $userId)
            ->where('raw_import_id', $rawImportId)
            ->whereNull('category_id')
            ->get();

        // Pass 1: keyword matching (fast, free)
        $stillUncategorized = $this->applyKeywordMatching($uncategorized, $categories);

        // Pass 2: AI categorization for what remains
        if ($stillUncategorized->isNotEmpty()) {
            $this->applyAiCategorization($stillUncategorized, $categories);
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
    private function applyAiCategorization(Collection $transactions, Collection $categories): void
    {
        $categoryNames = $categories->pluck('name')->all();
        // Build name → id map for quick lookup
        $nameToId = $categories->pluck('id', 'name')->all();

        // Process in batches to stay within token limits
        foreach ($transactions->chunk(self::BATCH_SIZE) as $batch) {
            $payload = $batch->map(fn (Transaction $t) => [
                'id'          => $t->id,
                'description' => $t->description_clean ?? $t->description,
            ])->values()->all();

            $results = $this->openAi->categorizeTransactions($payload, $categoryNames);

            foreach ($results as $transactionId => $categoryName) {
                $categoryId = $nameToId[$categoryName] ?? null;

                if ($categoryId !== null) {
                    Transaction::where('id', $transactionId)->update(['category_id' => $categoryId]);
                }
            }
        }
    }
}
