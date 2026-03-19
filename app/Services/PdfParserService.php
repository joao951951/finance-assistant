<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
        Log::info('[PdfParser] Iniciando parse', ['file' => basename($filePath)]);

        $text = $this->extractText($filePath);

        Log::info('[PdfParser] Texto extraído', [
            'chars'      => mb_strlen($text),
            'lines'      => substr_count($text, "\n"),
            'preview'    => mb_substr($text, 0, 300),
        ]);

        $bank = $this->detectBank($text);
        Log::info('[PdfParser] Banco detectado', ['bank' => $bank ?? 'nenhum (usando generic)']);

        $pattern = self::BANK_PATTERNS[$bank] ?? self::BANK_PATTERNS['generic'];
        $rows    = $this->parseLines($text, $pattern, $bank);

        Log::info('[PdfParser] Resultado padrão do banco', ['rows' => count($rows)]);

        // If bank-specific pattern found nothing, try stateful parser (conta corrente / any bank)
        if (empty($rows)) {
            Log::warning('[PdfParser] Padrão regex sem resultado, tentando parser stateful');
            $rows = $this->parseStatefulLines($text, $bank);
            Log::info('[PdfParser] Resultado stateful', ['rows' => count($rows)]);
        }

        Log::info('[PdfParser] Parse finalizado', ['bank' => $bank, 'total_rows' => count($rows)]);

        return ['bank' => $bank, 'rows' => $rows];
    }

    private function extractText(string $filePath): string
    {
        Log::debug('[PdfParser] Extraindo texto do PDF', ['path' => $filePath]);

        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();

        if (empty(trim($text))) {
            Log::warning('[PdfParser] Texto extraído está vazio — PDF pode ser baseado em imagem (não OCR)');
        }

        return $text;
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
        $rows       = [];
        $lines      = explode("\n", $text);
        $year       = (int) date('Y');
        $skipped    = 0;
        $noMatch    = 0;
        $badDate    = 0;
        $badAmount  = 0;

        Log::debug('[PdfParser] parseLines iniciado', [
            'banco'   => $bank ?? 'generic',
            'linhas'  => count($lines),
            'pattern' => $pattern,
        ]);

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (mb_strlen($line) < 10) {
                $skipped++;
                continue;
            }

            if (! preg_match($pattern, $line, $m)) {
                $noMatch++;
                // Loga apenas as primeiras 20 linhas que não bateram para não poluir
                if ($noMatch <= 20) {
                    Log::debug('[PdfParser] Linha sem match', ['linha' => $i + 1, 'conteudo' => $line]);
                }
                continue;
            }

            $date   = $this->parseDate($m['date'], $year, $bank);
            $amount = $this->parseAmount($m['amount']);

            if ($date === null) {
                $badDate++;
                Log::warning('[PdfParser] Data inválida', ['linha' => $i + 1, 'raw_date' => $m['date'], 'conteudo' => $line]);
                continue;
            }

            if ($amount === null) {
                $badAmount++;
                Log::warning('[PdfParser] Valor inválido', ['linha' => $i + 1, 'raw_amount' => $m['amount'], 'conteudo' => $line]);
                continue;
            }

            $rows[] = [
                'date'        => $date,
                'description' => trim(preg_replace('/\s+/', ' ', $m['description']) ?? $m['description']),
                'amount'      => abs($amount),
                'type'        => $amount < 0 ? 'debit' : 'credit',
            ];
        }

        Log::info('[PdfParser] parseLines concluído', [
            'banco'          => $bank ?? 'generic',
            'encontradas'    => count($rows),
            'sem_match'      => $noMatch,
            'curtas_skip'    => $skipped,
            'data_invalida'  => $badDate,
            'valor_invalido' => $badAmount,
        ]);

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

    /**
     * Stateful parser — fallback genérico para extratos de conta corrente de qualquer banco.
     *
     * Estratégia:
     *  1. Quando uma linha começa com uma data reconhecível, essa data passa a ser a "data corrente".
     *  2. Linhas com "description TAB amount" ou "description  2+spaces  amount" geram transações.
     *  3. Seções "Total de entradas / saídas" determinam o tipo (credit/debit) para o Nubank conta.
     *  4. Linhas de cabeçalho/rodapé conhecidas são ignoradas.
     *
     * Formatos de data suportados no cabeçalho:
     *   DD MMM YYYY  (ex: "02 JAN 2026 ...")
     *   DD/MM/YYYY   (ex: "15/03/2026 ...")
     *   YYYY-MM-DD   (ex: "2026-03-15 ...")
     *
     * @return array<int, array{date: string, description: string, amount: float, type: string}>
     */
    private function parseStatefulLines(string $text, ?string $bank): array
    {
        $rows        = [];
        $lines       = explode("\n", $text);
        $currentDate = null;
        $currentType = null; // null = infer from amount sign

        $skipDescriptions = [
            'total de entradas', 'total de saídas', 'saldo inicial', 'saldo final',
            'saldo final do período', 'rendimento líquido', 'movimentações',
            'saldo anterior', 'saldo atual', 'saldo disponível',
        ];

        // Regex patterns to detect a date at the START of a line
        $datePatterns = [
            // "02 JAN 2026" (PT month abbreviation + year)
            'pt_month_year' => '/^(\d{2}\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3}\s+\d{4})/iu',
            // "15/03/2026" or "15/03/26"
            'dmy_slash'     => '/^(\d{2}\/\d{2}\/\d{2,4})/u',
            // "2026-03-15"
            'ymd_dash'      => '/^(\d{4}-\d{2}-\d{2})/u',
            // "15-03-2026"
            'dmy_dash'      => '/^(\d{2}-\d{2}-\d{4})/u',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 5) {
                continue;
            }

            // ── 1. Detect section switch (Nubank conta / similar banks) ──────────
            if (preg_match('/^(?:\d{2}\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3}\s+\d{4}\s+)?Total de (entradas|saídas)/iu', $line, $m)) {
                $currentType = str_contains(mb_strtolower($m[1]), 'entrada') ? 'credit' : 'debit';
                // Also try to extract date from this line
                if (preg_match('/^(\d{2})\s+([A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3})\s+(\d{4})/iu', $line, $dm)) {
                    $month = self::PT_MONTHS[mb_strtoupper($dm[2])] ?? null;
                    if ($month) {
                        $currentDate = Carbon::createFromDate((int) $dm[3], $month, (int) $dm[1])->format('Y-m-d');
                    }
                }
                continue;
            }

            // ── 2. Detect date header line ────────────────────────────────────────
            $detectedDate = null;
            foreach ($datePatterns as $pattern) {
                if (preg_match($pattern, $line, $dm)) {
                    $detectedDate = $this->parseDate($dm[1], (int) date('Y'), $bank);
                    break;
                }
            }
            if ($detectedDate !== null) {
                $currentDate = $detectedDate;
                // Do NOT continue — this line might also contain a transaction
            }

            // ── 3. Parse transaction line ─────────────────────────────────────────
            if ($currentDate === null) {
                continue;
            }

            // Split by TAB first, then fall back to 2+ spaces
            $parts = null;
            if (str_contains($line, "\t")) {
                $split = explode("\t", $line, 2);
                if (count($split) === 2) {
                    $parts = $split;
                }
            } elseif (preg_match('/^(.+?)\s{2,}([+\-]?\s*R?\$?\s*[\d.]+,\d{2})\s*$/u', $line, $sm)) {
                $parts = [$sm[1], $sm[2]];
            }

            if ($parts === null) {
                continue;
            }

            $description = trim($parts[0]);
            $rawAmount   = trim($parts[1]);

            // Skip known non-transaction lines
            if (in_array(mb_strtolower($description), $skipDescriptions, true)) {
                continue;
            }
            // Skip lines where "description" looks like a date-only header
            if (preg_match('/^\d{2}[\s\/\-]\w{2,3}[\s\/\-]\d{2,4}$/', $description)) {
                continue;
            }

            $amount = $this->parseAmount($rawAmount);
            if ($amount === null) {
                continue;
            }

            // Determine type: from section context or from amount sign
            $type = $currentType ?? ($amount < 0 ? 'debit' : 'credit');

            $rows[] = [
                'date'        => $currentDate,
                'description' => trim(preg_replace('/\s+/', ' ', $description) ?? $description),
                'amount'      => abs($amount),
                'type'        => $type,
            ];
        }

        Log::info('[PdfParser] parseStatefulLines concluído', [
            'banco'      => $bank ?? 'generic',
            'encontradas' => count($rows),
        ]);

        return $rows;
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
