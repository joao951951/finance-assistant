<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;

class PdfParserService
{
    /**
     * Bank-specific line patterns.
     * Each entry: [bank_slug => regex with named groups date, description, amount]
     *
     * Groups required: date, description, amount
     */
    private const BANK_PATTERNS = [
        // Nubank: "12 ABR  iFood*Restaurante           -45,90"
        'nubank' => '/^(?P<date>\d{2}\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3})\s{2,}(?P<description>.+?)\s{2,}(?P<amount>-?\s*R?\$?\s*[\d.]+,\d{2})\s*$/iu',

        // Inter: "01/03/2026  COMPRA DEBITO IFOOD         -45,90"
        'inter'  => '/^(?P<date>\d{2}\/\d{2}\/\d{4})\s+(?P<description>.+?)\s{2,}(?P<amount>-?[\d.]+,\d{2})\s*$/u',

        // Bradesco: "15/03/2026  PIX RECEBIDO JOAO SILVA       +1.500,00"
        'bradesco' => '/^(?P<date>\d{2}\/\d{2}\/\d{4})\s+(?P<description>.+?)\s+(?P<amount>[+-]?[\d.]+,\d{2})\s*$/u',

        // C6: "2026-03-15  Compra Débito Amazon    -R$ 189,90"
        'c6'     => '/^(?P<date>\d{4}-\d{2}-\d{2})\s+(?P<description>.+?)\s+(?P<amount>-?R?\$?\s*[\d.]+,\d{2})\s*$/u',

        // Generic: any line with DD/MM/YYYY or DD/MM/YY + text + amount
        'generic' => '/^(?P<date>\d{2}[\/\-]\d{2}[\/\-]\d{2,4})\s+(?P<description>.{3,60}?)\s{2,}(?P<amount>[+-]?\s*R?\$?\s*[\d.]+,\d{2})\s*$/u',
    ];

    /**
     * PT-BR month abbreviation → month number.
     */
    private const PT_MONTHS = [
        'JAN' => 1, 'FEV' => 2, 'MAR' => 3, 'ABR' => 4,
        'MAI' => 5, 'JUN' => 6, 'JUL' => 7, 'AGO' => 8,
        'SET' => 9, 'OUT' => 10, 'NOV' => 11, 'DEZ' => 12,
    ];

    /**
     * Parse a PDF file and return normalized transaction rows.
     *
     * @return array{bank: string|null, rows: array<int, array{date: string, description: string, amount: float, type: string}>}
     */
    public function parse(string $filePath): array
    {
        $text = $this->extractText($filePath);
        $bank = $this->detectBank($text);

        $pattern = self::BANK_PATTERNS[$bank] ?? self::BANK_PATTERNS['generic'];
        $rows    = $this->parseLines($text, $pattern, $bank);

        // If bank-specific pattern found nothing, try generic
        if (empty($rows) && $bank !== null) {
            $rows = $this->parseLines($text, self::BANK_PATTERNS['generic'], null);
        }

        return ['bank' => $bank, 'rows' => $rows];
    }

    private function extractText(string $filePath): string
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);

        return $pdf->getText();
    }

    private function detectBank(string $text): ?string
    {
        $textUpper = mb_strtoupper($text);

        $signatures = [
            'nubank'   => ['NUBANK', 'NU PAGAMENTOS', 'ROXINHO'],
            'inter'    => ['BANCO INTER', 'INTER S.A.', 'CONTACERTA'],
            'bradesco' => ['BRADESCO', 'BANCO BRADESCO'],
            'itau'     => ['ITAÚ', 'ITAU UNIBANCO'],
            'c6'       => ['C6 BANK', 'C6S.A.'],
            'santander' => ['SANTANDER'],
            'caixa'    => ['CAIXA ECONÔMICA', 'CAIXA ECONOMICA'],
        ];

        foreach ($signatures as $bank => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textUpper, $keyword)) {
                    return $bank;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{date: string, description: string, amount: float, type: string}>
     */
    private function parseLines(string $text, string $pattern, ?string $bank): array
    {
        $rows  = [];
        $lines = explode("\n", $text);
        $year  = (int) date('Y');

        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 10) {
                continue;
            }

            if (! preg_match($pattern, $line, $m)) {
                continue;
            }

            $date   = $this->parseDate($m['date'], $year, $bank);
            $amount = $this->parseAmount($m['amount']);

            if ($date === null || $amount === null) {
                continue;
            }

            $rows[] = [
                'date'        => $date,
                'description' => trim(preg_replace('/\s+/', ' ', $m['description']) ?? $m['description']),
                'amount'      => abs($amount),
                'type'        => $amount < 0 ? 'debit' : 'credit',
            ];
        }

        return $rows;
    }

    private function parseDate(string $raw, int $defaultYear, ?string $bank): ?string
    {
        $raw = trim($raw);

        // "12 ABR" — Nubank style (day + PT month abbreviation)
        if (preg_match('/^(\d{1,2})\s+([A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3})$/iu', $raw, $m)) {
            $month = self::PT_MONTHS[mb_strtoupper($m[2])] ?? null;
            if ($month === null) {
                return null;
            }

            return Carbon::createFromDate($defaultYear, $month, (int) $m[1])->format('Y-m-d');
        }

        // "DD/MM/YYYY" or "DD/MM/YY"
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2,4})$/', $raw, $m)) {
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

            return Carbon::createFromDate($year, (int) $m[2], (int) $m[1])->format('Y-m-d');
        }

        // "YYYY-MM-DD"
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->format('Y-m-d');
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    private function parseAmount(string $raw): ?float
    {
        // Remove currency symbols, spaces
        $clean = preg_replace('/[R$\s]/', '', $raw);
        $clean = trim($clean ?? '');

        if ($clean === '' || $clean === '-' || $clean === '+') {
            return null;
        }

        // BR format: 1.234,56 → preserve sign
        $negative = str_starts_with($clean, '-');
        $clean    = ltrim($clean, '+-');

        // Remove thousand separator dots, convert decimal comma
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if (! is_numeric($clean)) {
            return null;
        }

        return $negative ? -(float) $clean : (float) $clean;
    }
}
