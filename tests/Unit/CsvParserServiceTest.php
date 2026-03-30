<?php

namespace Tests\Unit;

use App\Services\CsvParserService;
use Tests\TestCase;

class CsvParserServiceTest extends TestCase
{
    private CsvParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParserService;
    }

    // ─── Nubank CSV ───────────────────────────────────────────────────────────

    public function test_parses_nubank_csv(): void
    {
        $csv = implode("\n", [
            'Data,Categoria,Título,Valor',
            '2026-03-01,Alimentação,iFood *Restaurante,-45.90',
            '2026-03-02,Transporte,Uber *Trip,-18.50',
            '2026-03-05,Receita,Salário,3000.00',
        ]);

        $path = $this->writeTempCsv($csv);
        $result = $this->parser->parse($path);

        $this->assertCount(3, $result['rows']);
        $this->assertSame('2026-03-01', $result['rows'][0]['date']);
        $this->assertSame('iFood *Restaurante', $result['rows'][0]['description']);
        $this->assertEqualsWithDelta(45.90, $result['rows'][0]['amount'], 0.001);
        $this->assertSame('debit', $result['rows'][0]['type']);
        $this->assertSame('credit', $result['rows'][2]['type']);
    }

    public function test_detects_nubank_bank(): void
    {
        $csv = implode("\n", [
            'Data,Categoria,Título,Valor',
            '2026-03-01,Alimentação,iFood,-45.90',
        ]);

        $result = $this->parser->parse($this->writeTempCsv($csv));
        $this->assertSame('nubank', $result['bank']);
    }

    // ─── Inter CSV ────────────────────────────────────────────────────────────

    public function test_parses_inter_csv(): void
    {
        $csv = implode("\n", [
            'Data Lançamento,Histórico,Descrição,Valor,Saldo',
            '01/03/2026,Compra,IFOOD PEDIDO,-45.90,954.10',
            '05/03/2026,Crédito,SALÁRIO,3000.00,3954.10',
        ]);

        $result = $this->parser->parse($this->writeTempCsv($csv));

        $this->assertCount(2, $result['rows']);
        $this->assertSame('inter', $result['bank']);
        $this->assertSame('2026-03-01', $result['rows'][0]['date']);
        $this->assertSame('debit', $result['rows'][0]['type']);
    }

    // ─── Semicolon delimiter ──────────────────────────────────────────────────

    public function test_detects_semicolon_delimiter(): void
    {
        $csv = implode("\n", [
            'Data;Descrição;Valor',
            '2026-03-01;Mercado;-150,00',
            '2026-03-02;Salário;2500,00',
        ]);

        $result = $this->parser->parse($this->writeTempCsv($csv));

        $this->assertCount(2, $result['rows']);
        $this->assertEqualsWithDelta(150.0, $result['rows'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(2500.0, $result['rows'][1]['amount'], 0.001);
    }

    // ─── BR number format ─────────────────────────────────────────────────────

    public function test_parses_brazilian_number_format(): void
    {
        $csv = implode("\n", [
            'Data,Descrição,Valor',
            '2026-03-01,Supermercado,"-1.234,56"',
        ]);

        $result = $this->parser->parse($this->writeTempCsv($csv));

        $this->assertEqualsWithDelta(1234.56, $result['rows'][0]['amount'], 0.001);
        $this->assertSame('debit', $result['rows'][0]['type']);
    }

    // ─── Skips empty/invalid rows ─────────────────────────────────────────────

    public function test_skips_rows_with_invalid_amount(): void
    {
        $csv = implode("\n", [
            'Data,Descrição,Valor',
            '2026-03-01,Compra normal,-50.00',
            '2026-03-02,Linha inválida,N/A',
            '2026-03-03,Outra compra,-30.00',
        ]);

        $result = $this->parser->parse($this->writeTempCsv($csv));
        $this->assertCount(2, $result['rows']);
    }

    // ─── cleanDescription ─────────────────────────────────────────────────────

    public function test_clean_description_capitalizes_and_strips_noise(): void
    {
        $result = $this->parser->cleanDescription('IFOOD *RESTAURANTE ITALIA 01/03');
        $this->assertStringNotContainsString('01/03', $result);
        $this->assertSame(mb_strtolower($result), mb_strtolower($result));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function writeTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_test_').'.csv';
        file_put_contents($path, $content);

        return $path;
    }
}
