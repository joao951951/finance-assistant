<?php

namespace App\Jobs;

use App\Services\CategorizationService;
use App\Services\OpenAiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategorizeTransactions implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // no retries — API errors should not retry

    public int $timeout = 300;

    public function __construct(
        public readonly int $userId,
        public readonly int $rawImportId,
    ) {}

    public function handle(): void
    {
        $openAi = OpenAiService::forUserId($this->userId);

        if (! $openAi->hasApiKey()) {
            Log::info('[CategorizeTransactions] Sem chave API — categorização ignorada', [
                'user_id' => $this->userId,
            ]);
            GenerateEmbeddings::dispatch($this->rawImportId);

            return;
        }

        try {
            $service = new CategorizationService($openAi);
            $service->categorizeForUser($this->userId, $this->rawImportId);
        } catch (Throwable $e) {
            Log::warning('[CategorizeTransactions] Erro na categorização — ignorado', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            // Do not rethrow — embeddings must still be generated
        }

        GenerateEmbeddings::dispatch($this->rawImportId);
    }
}
