<?php

namespace App\Jobs;

use App\Models\RawImport;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
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

    public function handle(): void
    {
        $import = RawImport::find($this->rawImportId);
        $openAi = $import ? OpenAiService::forUserId($import->user_id) : new OpenAiService;
        $service = new EmbeddingService($openAi);

        $service->generateForImport($this->rawImportId);
    }
}
