<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RagService
{
    /**
     * How many similar transactions to retrieve as context.
     */
    private const DEFAULT_LIMIT = 10;

    public function __construct(private readonly EmbeddingService $embeddings) {}

    /**
     * Find the most semantically similar transactions to a query string.
     * Uses pgvector cosine distance (<=>).
     *
     * @return array<int, array{
     *   id: int,
     *   date: string,
     *   description: string,
     *   amount: float,
     *   type: string,
     *   category_name: string|null,
     *   distance: float
     * }>
     */
    public function search(int $userId, string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $vector = $this->embeddings->embedQuery($query);

        if (empty($vector)) {
            return [];
        }

        $literal = '[' . implode(',', $vector) . ']';

        $rows = DB::select(
            <<<SQL
                SELECT
                    t.id,
                    t.date::text,
                    COALESCE(t.description_clean, t.description) AS description,
                    t.amount::float,
                    t.type,
                    c.name AS category_name,
                    (t.embedding <=> ?::vector)::float AS distance
                FROM transactions t
                LEFT JOIN categories c ON c.id = t.category_id
                WHERE t.user_id = ?
                  AND t.embedding IS NOT NULL
                ORDER BY t.embedding <=> ?::vector
                LIMIT ?
            SQL,
            [$literal, $userId, $literal, $limit]
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }

    /**
     * Build a context string from search results to inject into the AI prompt.
     *
     * @param  array<int, array{date: string, description: string, amount: float, type: string, category_name: string|null}>  $results
     */
    public function buildContext(array $results): string
    {
        if (empty($results)) {
            return 'Nenhuma transação relevante encontrada.';
        }

        $lines = array_map(function (array $t) {
            $sign     = $t['type'] === 'credit' ? '+' : '-';
            $category = $t['category_name'] ?? 'Sem categoria';

            return "- {$t['date']} | {$t['description']} | {$sign}R$ {$t['amount']} | {$category}";
        }, $results);

        return implode("\n", $lines);
    }

    /**
     * Search and return ready-to-use context string in one call.
     */
    public function contextFor(int $userId, string $query, int $limit = self::DEFAULT_LIMIT): string
    {
        $results = $this->search($userId, $query, $limit);

        return $this->buildContext($results);
    }
}
