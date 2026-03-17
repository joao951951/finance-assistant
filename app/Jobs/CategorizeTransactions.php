<?php

namespace App\Jobs;

use App\Services\CategorizationService;
use App\Services\OpenAiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CategorizeTransactions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $userId,
        public readonly int $rawImportId,
    ) {}

    public function handle(): void
    {
        $openAi  = OpenAiService::forUserId($this->userId);
        $service = new CategorizationService($openAi);

        $service->categorizeForUser($this->userId, $this->rawImportId);

        GenerateEmbeddings::dispatch($this->rawImportId);
    }
}
