<?php

namespace Tests\Feature;

use App\Jobs\CategorizeTransactions;
use App\Jobs\ProcessRawImport;
use App\Models\RawImport;
use App\Models\User;
use App\Services\CsvParserService;
use App\Services\PdfParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessRawImportJobTest extends TestCase
{
    use RefreshDatabase;

    // ─── CSV processing ───────────────────────────────────────────────────────

    public function test_csv_import_creates_transactions(): void
    {
        Bus::fake([CategorizeTransactions::class]);
        Storage::fake('local');

        $user = User::factory()->create();

        $csv = implode("\n", [
            'Data,Descrição,Valor',
            '2026-03-01,iFood,-45.90',
            '2026-03-05,Salário,3000.00',
        ]);
        Storage::disk('local')->put('imports/test.csv', $csv);

        $import = RawImport::create([
            'user_id' => $user->id,
            'filename' => 'test.csv',
            'type' => 'csv',
            'path' => 'imports/test.csv',
            'status' => 'pending',
        ]);

        (new ProcessRawImport($import))->handle(
            app(CsvParserService::class),
            app(PdfParserService::class),
        );

        $this->assertDatabaseHas('raw_imports', [
            'id' => $import->id,
            'status' => 'done',
            'transactions_count' => 2,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'raw_import_id' => $import->id,
            'description' => 'iFood',
            'type' => 'debit',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
        ]);
    }

    public function test_csv_import_dispatches_categorize_job(): void
    {
        Bus::fake([CategorizeTransactions::class]);
        Storage::fake('local');

        $user = User::factory()->create();

        $csv = implode("\n", [
            'Data,Descrição,Valor',
            '2026-03-01,Mercado,-100.00',
        ]);
        Storage::disk('local')->put('imports/job.csv', $csv);

        $import = RawImport::create([
            'user_id' => $user->id,
            'filename' => 'job.csv',
            'type' => 'csv',
            'path' => 'imports/job.csv',
            'status' => 'pending',
        ]);

        (new ProcessRawImport($import))->handle(
            app(CsvParserService::class),
            app(PdfParserService::class),
        );

        Bus::assertDispatched(CategorizeTransactions::class);
    }

    // ─── Bank detection ───────────────────────────────────────────────────────

    public function test_bank_is_saved_from_csv_parser(): void
    {
        Bus::fake([CategorizeTransactions::class]);
        Storage::fake('local');

        $user = User::factory()->create();

        // Nubank header triggers bank detection
        $csv = implode("\n", [
            'Data,Categoria,Título,Valor',
            '2026-03-01,Alimentação,iFood,-45.90',
        ]);
        Storage::disk('local')->put('imports/nubank.csv', $csv);

        $import = RawImport::create([
            'user_id' => $user->id,
            'filename' => 'nubank.csv',
            'type' => 'csv',
            'path' => 'imports/nubank.csv',
            'status' => 'pending',
        ]);

        (new ProcessRawImport($import))->handle(
            app(CsvParserService::class),
            app(PdfParserService::class),
        );

        $this->assertDatabaseHas('raw_imports', [
            'id' => $import->id,
            'bank' => 'nubank',
        ]);
    }

    // ─── PDF routing ──────────────────────────────────────────────────────────

    public function test_pdf_import_routes_to_pdf_parser(): void
    {
        Bus::fake([CategorizeTransactions::class]);
        Storage::fake('local');

        $user = User::factory()->create();

        // Use a mock PdfParserService returning pre-defined rows
        $pdfParser = $this->createMock(PdfParserService::class);
        $pdfParser->method('parse')->willReturn([
            'bank' => 'inter',
            'rows' => [
                [
                    'date' => '2026-03-01',
                    'description' => 'COMPRA DEBITO IFOOD',
                    'amount' => 45.90,
                    'type' => 'debit',
                ],
            ],
        ]);

        Storage::disk('local')->put('imports/extrato.pdf', '%PDF-1.4 fake');

        $import = RawImport::create([
            'user_id' => $user->id,
            'filename' => 'extrato.pdf',
            'type' => 'pdf',
            'path' => 'imports/extrato.pdf',
            'status' => 'pending',
        ]);

        (new ProcessRawImport($import))->handle(
            app(CsvParserService::class),
            $pdfParser,
        );

        $this->assertDatabaseHas('raw_imports', [
            'id' => $import->id,
            'status' => 'done',
            'bank' => 'inter',
            'transactions_count' => 1,
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'COMPRA DEBITO IFOOD',
            'type' => 'debit',
        ]);
    }

    // ─── Failure handling ─────────────────────────────────────────────────────

    public function test_failed_marks_import_as_failed(): void
    {
        $user = User::factory()->create();

        $import = RawImport::create([
            'user_id' => $user->id,
            'filename' => 'bad.csv',
            'type' => 'csv',
            'path' => 'imports/bad.csv',
            'status' => 'pending',
        ]);

        $job = new ProcessRawImport($import);
        $job->failed(new \RuntimeException('Disk error'));

        $this->assertDatabaseHas('raw_imports', [
            'id' => $import->id,
            'status' => 'failed',
            'error_message' => 'Disk error',
        ]);
    }
}
