<?php

namespace App\Jobs;

use App\Services\CategorizationService;
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

    public function handle(CategorizationService $service): void
    {
        $service->categorizeForUser($this->userId, $this->rawImportId);

        GenerateEmbeddings::dispatch($this->rawImportId);
    }
}
