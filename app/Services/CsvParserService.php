<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use League\Csv\Reader;

class CsvParserService
{
    /**
     * Known bank CSV header signatures.
     * Each entry maps header keywords → bank slug.
     */
    private const BANK_SIGNATURES = [
        'nubank' => ['data', 'categoria', 'título', 'valor'],
        'inter'  => ['data lançamento', 'histórico', 'valor'],
        'c6'     => ['data', 'descrição', 'valor', 'parcela'],
    ];

    /**
     * Column name aliases to normalize to internal keys.
     */
    private const COLUMN_MAP = [
        'date'        => ['data', 'data lançamento', 'date', 'data pagamento'],
        'description' => ['descrição', 'título', 'histórico', 'description', 'memo', 'detalhes'],
        'amount'      => ['valor', 'amount', 'value'],
    ];

    /**
     * Parse a CSV file and return normalized transaction rows.
     *
     * @return array{bank: string|null, rows: array<int, array{date: string, description: string, amount: float, type: string}>}
     */
    public function parse(string $filePath): array
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        // Try different delimiters — some banks use semicolons
        $delimiter = $this->detectDelimiter($filePath);
        $csv->setDelimiter($delimiter);

        $headers = array_map(
            fn (string $h) => mb_strtolower(trim($h)),
            $csv->getHeader()
        );

        $bank = $this->detectBank($headers);
        $columnMap = $this->resolveColumns($headers);

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $row = $this->normalizeRecord($record, $columnMap);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return ['bank' => $bank, 'rows' => $rows];
    }

    /**
     * @return array{date: string, description: string, amount: float, type: string}|null
     */
    private function normalizeRecord(array $record, array $columnMap): ?array
    {
        $date        = $this->extractValue($record, $columnMap['date'] ?? null);
        $description = $this->extractValue($record, $columnMap['description'] ?? null);
        $rawAmount   = $this->extractValue($record, $columnMap['amount'] ?? null);

        if ($date === null || $description === null || $rawAmount === null) {
            return null;
        }

        $amount = $this->parseAmount($rawAmount);

        if ($amount === null) {
            return null;
        }

        return [
            'date'        => $this->parseDate($date),
            'description' => trim($description),
            'amount'      => abs($amount),
            'type'        => $amount >= 0 ? 'credit' : 'debit',
        ];
    }

    private function detectBank(array $headers): ?string
    {
        foreach (self::BANK_SIGNATURES as $bank => $keywords) {
            $matched = array_filter($keywords, fn ($k) => in_array($k, $headers));
            if (count($matched) >= 2) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * Map internal keys (date, description, amount) to actual header names found in file.
     *
     * @return array<string, string>
     */
    private function resolveColumns(array $headers): array
    {
        $resolved = [];

        foreach (self::COLUMN_MAP as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (in_array($alias, $headers)) {
                    $resolved[$key] = $alias;
                    break;
                }
            }
        }

        return $resolved;
    }

    private function extractValue(array $record, ?string $column): ?string
    {
        if ($column === null) {
            return null;
        }

        // Records may use original-case keys — find case-insensitively
        foreach ($record as $key => $value) {
            if (mb_strtolower(trim($key)) === $column) {
                $v = trim($value);

                return $v !== '' ? $v : null;
            }
        }

        return null;
    }

    private function parseAmount(string $raw): ?float
    {
        // Remove currency symbols and spaces
        $clean = preg_replace('/[^\d,.\-]/', '', $raw);

        if ($clean === null || $clean === '' || $clean === '-') {
            return null;
        }

        // Handle BR format: 1.234,56 → 1234.56
        if (preg_match('/^\-?\d{1,3}(\.\d{3})*(,\d{2})?$/', $clean)) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } else {
            // EN format: remove thousand separator comma
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseDate(string $raw): string
    {
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'd/m/y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim($raw))->format('Y-m-d');
            } catch (\Exception) {
                continue;
            }
        }

        // Last resort — let Carbon figure it out
        return Carbon::parse($raw)->format('Y-m-d');
    }

    private function detectDelimiter(string $filePath): string
    {
        $line = fgets(fopen($filePath, 'r'));

        if ($line === false) {
            return ',';
        }

        $commas     = substr_count($line, ',');
        $semicolons = substr_count($line, ';');

        return $semicolons > $commas ? ';' : ',';
    }

    /**
     * Clean a transaction description for better readability and categorization.
     */
    public function cleanDescription(string $description): string
    {
        // Remove common noise patterns (transaction IDs, dates embedded in description)
        $clean = preg_replace('/\s+/', ' ', $description);
        $clean = preg_replace('/\d{2}\/\d{2}$/', '', $clean); // trailing date
        $clean = preg_replace('/\*{2,}/', '', $clean);         // asterisks
        $clean = trim($clean ?? $description);

        return Str::title(mb_strtolower($clean));
    }
}
