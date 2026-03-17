<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEmbeddings implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Allow longer timeout — OpenAI embeddings for 100+ transactions can take time.
     */
    public int $timeout = 600;

    public function __construct(public readonly int $rawImportId) {}

    public function handle(EmbeddingService $service): void
    {
        $service->generateForImport($this->rawImportId);
    }
}
