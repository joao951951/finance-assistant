<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmbeddingService
{
    /**
     * Max texts per OpenAI embeddings call.
     * OpenAI supports up to 2048 — keeping lower to control token usage.
     */
    private const BATCH_SIZE = 100;

    public function __construct(private readonly OpenAiService $openAi) {}

    /**
     * Generate and persist embeddings for all transactions of a given import
     * that do not yet have an embedding.
     */
    public function generateForImport(int $rawImportId): void
    {
        Transaction::where('raw_import_id', $rawImportId)
            ->whereNull('embedding')
            ->select(['id', 'description_clean', 'description'])
            ->chunkById(self::BATCH_SIZE, function (Collection $chunk) {
                $this->processBatch($chunk);
            });
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function processBatch(Collection $transactions): void
    {
        $texts = $transactions->map(
            fn (Transaction $t) => $t->description_clean ?? $t->description
        )->all();

        $vectors = $this->openAi->embeddings($texts);

        foreach ($transactions as $index => $transaction) {
            if (! isset($vectors[$index])) {
                continue;
            }

            $this->storeEmbedding($transaction->id, $vectors[$index]);
        }
    }

    /**
     * Store a single embedding vector using raw SQL.
     * Eloquent has no native pgvector support — must cast to ::vector.
     *
     * @param  float[]  $vector
     */
    public function storeEmbedding(int $transactionId, array $vector): void
    {
        $literal = '[' . implode(',', $vector) . ']';

        DB::statement(
            'UPDATE transactions SET embedding = ?::vector WHERE id = ?',
            [$literal, $transactionId]
        );
    }

    /**
     * Generate an embedding for a single query string (used by RAG search).
     *
     * @return float[]
     */
    public function embedQuery(string $text): array
    {
        $vectors = $this->openAi->embeddings([$text]);

        return $vectors[0] ?? [];
    }
}
