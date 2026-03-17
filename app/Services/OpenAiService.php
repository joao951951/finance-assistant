<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
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
        $resolvedKey          = $apiKey ?? config('services.openai.api_key', '');
        $this->chatModel      = $chatModel ?? config('services.openai.chat_model', 'gpt-4o');
        $this->embeddingModel = $embeddingModel ?? config('services.openai.embedding_model', 'text-embedding-3-small');

        $certPath  = storage_path('cacert.pem');
        $guzzle    = new GuzzleClient([
            'verify' => file_exists($certPath) ? $certPath : true,
        ]);

        $this->client = OpenAI::factory()
            ->withApiKey($resolvedKey)
            ->withHttpClient($guzzle)
            ->make();

        Log::debug('[OpenAI] Service instantiated', [
            'has_key'         => ! empty($resolvedKey),
            'key_prefix'      => ! empty($resolvedKey) ? substr($resolvedKey, 0, 10) . '...' : 'none',
            'chat_model'      => $this->chatModel,
            'embedding_model' => $this->embeddingModel,
        ]);
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

        Log::info('[OpenAI] categorizeTransactions → request', [
            'model'             => $this->chatModel,
            'transactions_count' => count($transactions),
        ]);

        $start = microtime(true);

        try {
            $response = $this->client->chat()->create([
                'model'           => $this->chatModel,
                'messages'        => [
                    ['role' => 'user', 'content' => trim($prompt)],
                ],
                'temperature'     => 0,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[OpenAI] categorizeTransactions → FAILED', [
                'error'   => $e->getMessage(),
                'elapsed' => round(microtime(true) - $start, 3) . 's',
            ]);
            throw $e;
        }

        $elapsed = round(microtime(true) - $start, 3);
        $usage   = $response->usage;

        Log::info('[OpenAI] categorizeTransactions → response', [
            'elapsed'          => $elapsed . 's',
            'prompt_tokens'    => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
            'total_tokens'     => $usage?->totalTokens,
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

        Log::info('[OpenAI] embeddings → request', [
            'model' => $this->embeddingModel,
            'count' => count($texts),
        ]);

        $start = microtime(true);

        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->embeddingModel,
                'input' => array_values($texts),
            ]);
        } catch (\Throwable $e) {
            Log::error('[OpenAI] embeddings → FAILED', [
                'error'   => $e->getMessage(),
                'elapsed' => round(microtime(true) - $start, 3) . 's',
            ]);
            throw $e;
        }

        $elapsed = round(microtime(true) - $start, 3);
        $usage   = $response->usage;

        Log::info('[OpenAI] embeddings → response', [
            'elapsed'       => $elapsed . 's',
            'prompt_tokens' => $usage?->promptTokens,
            'total_tokens'  => $usage?->totalTokens,
            'vectors_count' => count($response->embeddings),
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
        Log::info('[OpenAI] chat → request', [
            'model'           => $this->chatModel,
            'messages_count'  => count($messages),
            'temperature'     => $temperature,
        ]);

        $start = microtime(true);

        try {
            $response = $this->client->chat()->create([
                'model'       => $this->chatModel,
                'messages'    => $messages,
                'temperature' => $temperature,
            ]);
        } catch (\Throwable $e) {
            Log::error('[OpenAI] chat → FAILED', [
                'error'   => $e->getMessage(),
                'elapsed' => round(microtime(true) - $start, 3) . 's',
            ]);
            throw $e;
        }

        $elapsed = round(microtime(true) - $start, 3);
        $usage   = $response->usage;

        Log::info('[OpenAI] chat → response', [
            'elapsed'           => $elapsed . 's',
            'prompt_tokens'     => $usage?->promptTokens,
            'completion_tokens' => $usage?->completionTokens,
            'total_tokens'      => $usage?->totalTokens,
        ]);

        return $response->choices[0]->message->content ?? '';
    }
}
