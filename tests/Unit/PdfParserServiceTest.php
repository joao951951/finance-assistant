<?php

namespace Tests\Unit;

use App\Services\PdfParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class PdfParserServiceTest extends TestCase
{
    private PdfParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PdfParserService;
    }

    // ─── Amount parsing (via reflection on private method) ────────────────────

    #[DataProvider('amountProvider')]
    public function test_parse_amount(string $input, ?float $expected): void
    {
        $result = $this->callPrivate('parseAmount', [$input]);
        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertEqualsWithDelta($expected, $result, 0.001);
        }
    }

    public static function amountProvider(): array
    {
        return [
            'negative BR' => ['-45,90',      -45.90],
            'positive BR' => ['150,00',      150.00],
            'with thousand sep' => ['-1.234,56',   -1234.56],
            'with R$ prefix' => ['R$ 200,00',   200.00],
            'negative with R$' => ['-R$ 99,90',   -99.90],
            'with spaces' => ['  -  50,00 ', -50.00],
            'empty string' => ['',            null],
            'just minus' => ['-',           null],
        ];
    }

    // ─── Date parsing ─────────────────────────────────────────────────────────

    #[DataProvider('dateProvider')]
    public function test_parse_date(string $input, string $expected): void
    {
        $result = $this->callPrivate('parseDate', [$input, 2026, null]);
        $this->assertSame($expected, $result);
    }

    public static function dateProvider(): array
    {
        return [
            'DD/MM/YYYY' => ['15/03/2026', '2026-03-15'],
            'DD/MM/YY' => ['15/03/26',   '2026-03-15'],
            'ISO' => ['2026-03-15', '2026-03-15'],
            'PT month ABR' => ['12 ABR',     '2026-04-12'],
            'PT month MAR' => ['05 MAR',     '2026-03-05'],
            'PT month DEZ' => ['31 DEZ',     '2026-12-31'],
        ];
    }

    // ─── Bank detection ───────────────────────────────────────────────────────

    #[DataProvider('bankDetectionProvider')]
    public function test_detect_bank(string $text, ?string $expectedBank): void
    {
        $result = $this->callPrivate('detectBank', [$text]);
        $this->assertSame($expectedBank, $result);
    }

    public static function bankDetectionProvider(): array
    {
        return [
            'nubank text' => ['Extrato NUBANK - Fatura',      'nubank'],
            'inter text' => ['BANCO INTER S.A. Extrato',     'inter'],
            'bradesco text' => ['Banco Bradesco S.A.',          'bradesco'],
            'c6 text' => ['C6 BANK Extrato Digital',      'c6'],
            'unknown text' => ['Banco Genérico XPTO',          null],
        ];
    }

    // ─── Integration: parseLines ──────────────────────────────────────────────

    public function test_parses_inter_format_lines(): void
    {
        $text = implode("\n", [
            'BANCO INTER S.A.',
            'Extrato de conta corrente',
            '01/03/2026  COMPRA DEBITO IFOOD         -45,90',
            '05/03/2026  PIX RECEBIDO SALARIO         3000,00',
            'Linha ignorada sem data',
        ]);

        $result = $this->callPrivate('parseLines', [
            $text,
            '/^(?P<date>\d{2}\/\d{2}\/\d{4})\s+(?P<description>.+?)\s{2,}(?P<amount>-?[\d.]+,\d{2})\s*$/u',
            'inter',
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('debit', $result[0]['type']);
        $this->assertSame('credit', $result[1]['type']);
    }

    // ─── Helper: call private methods via reflection ──────────────────────────

    private function callPrivate(string $method, array $args): mixed
    {
        $ref = new ReflectionClass($this->parser);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->parser, $args);
    }
}
