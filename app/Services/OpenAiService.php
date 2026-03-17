<?php

namespace App\Services;

use App\Models\User;
use OpenAI; // root-namespace class from openai-php/client — IDE false positive, works at runtime
use OpenAI\Client;

class OpenAiService
{
    private Client $client;

    private string $chatModel;

    private string $embeddingModel;

    public function __construct(
        ?string $apiKey = null,
        ?string $chatModel = null,
        ?string $embeddingModel = null,
    ) {
        $this->client         = OpenAI::client($apiKey ?? config('services.openai.api_key', ''));
        $this->chatModel      = $chatModel ?? config('services.openai.chat_model', 'gpt-4o');
        $this->embeddingModel = $embeddingModel ?? config('services.openai.embedding_model', 'text-embedding-3-small');
    }

    /**
     * Build an instance configured with the given user's API credentials.
     * Falls back to global config values if the user has not set their own.
     */
    public static function forUser(User $user): self
    {
        return new self(
            apiKey:         $user->openai_api_key ?: null,
            chatModel:      $user->openai_chat_model ?: null,
            embeddingModel: $user->openai_embedding_model ?: null,
        );
    }

    /**
     * Convenience factory: load user by ID then call forUser().
     */
    public static function forUserId(int $userId): self
    {
        $user = User::find($userId);

        return $user ? self::forUser($user) : new self();
    }

    /**
     * Categorize a batch of transactions in a single API call.
     *
     * @param  array<int, array{id: int, description: string}>  $transactions
     * @param  array<int, string>  $categoryNames
     * @return array<int, string> Map of transaction ID → category name
     */
    public function categorizeTransactions(array $transactions, array $categoryNames): array
    {
        if (empty($transactions)) {
            return [];
        }

        $categoriesList = implode(', ', $categoryNames);

        $lines = array_map(
            fn ($t) => "{$t['id']}: {$t['description']}",
            $transactions
        );
        $transactionsList = implode("\n", $lines);

        $prompt = <<<PROMPT
            Você é um assistente de finanças pessoais. Categorize cada transação abaixo em uma das categorias fornecidas.
            Se nenhuma categoria se encaixar, use "Outros".

            Categorias disponíveis: {$categoriesList}

            Transações (id: descrição):
            {$transactionsList}

            Responda SOMENTE com um JSON no formato: {"id": "categoria", ...}
            Exemplo: {"1": "Alimentação", "2": "Transporte"}
            PROMPT;

        $response = $this->client->chat()->create([
            'model'           => $this->chatModel,
            'messages'        => [
                ['role' => 'user', 'content' => trim($prompt)],
            ],
            'temperature'     => 0,
            'response_format' => ['type' => 'json_object'],
        ]);

        $content = $response->choices[0]->message->content ?? '{}';

        /** @var array<string, string> $decoded */
        $decoded = json_decode($content, true) ?? [];

        // Normalize keys to int
        $result = [];
        foreach ($decoded as $id => $category) {
            $result[(int) $id] = (string) $category;
        }

        return $result;
    }

    /**
     * Generate embeddings for a batch of texts (up to 2048 inputs per call).
     *
     * @param  array<int, string>  $texts
     * @return array<int, float[]>  Ordered list of embedding vectors
     */
    public function embeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = $this->client->embeddings()->create([
            'model' => $this->embeddingModel,
            'input' => array_values($texts),
        ]);

        return array_map(
            fn ($item) => $item->embedding,
            $response->embeddings
        );
    }

    /**
     * Generic chat completion.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, float $temperature = 0.7): string
    {
        $response = $this->client->chat()->create([
            'model'       => $this->chatModel,
            'messages'    => $messages,
            'temperature' => $temperature,
        ]);

        return $response->choices[0]->message->content ?? '';
    }
}
