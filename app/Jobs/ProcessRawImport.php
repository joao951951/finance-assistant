<?php

namespace App\Jobs;

use App\Models\RawImport;
use App\Models\Transaction;
use App\Services\CsvParserService;
use App\Services\PdfParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessRawImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly RawImport $rawImport) {}

    public function handle(CsvParserService $csvParser, PdfParserService $pdfParser): void
    {
        $this->rawImport->markProcessing();

        $path   = Storage::path($this->rawImport->path);
        $result = $this->rawImport->type === 'pdf'
            ? $pdfParser->parse($path)
            : $csvParser->parse($path);

        if ($this->rawImport->bank === null && $result['bank'] !== null) {
            $this->rawImport->update(['bank' => $result['bank']]);
        }

        $count       = 0;
        $userId      = $this->rawImport->user_id;
        $rawImportId = $this->rawImport->id;

        foreach ($result['rows'] as $row) {
            Transaction::create([
                'user_id'           => $userId,
                'raw_import_id'     => $rawImportId,
                'date'              => $row['date'],
                'description'       => $row['description'],
                'description_clean' => $csvParser->cleanDescription($row['description']),
                'amount'            => $row['amount'],
                'type'              => $row['type'],
                'bank'              => $this->rawImport->bank,
            ]);

            $count++;
        }

        $this->rawImport->markDone($count);

        CategorizeTransactions::dispatch($userId, $rawImportId);
    }

    public function failed(Throwable $e): void
    {
        $this->rawImport->markFailed($e->getMessage());
    }
}
