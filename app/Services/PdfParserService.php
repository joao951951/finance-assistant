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
        'inter' => '/^(?P<date>\d{2}\/\d{2}\/\d{4})\s+(?P<description>.+?)\s{2,}(?P<amount>-?[\d.]+,\d{2})\s*$/u',

        // Bradesco: "15/03/2026  PIX RECEBIDO JOAO SILVA       +1.500,00"
        'bradesco' => '/^(?P<date>\d{2}\/\d{2}\/\d{4})\s+(?P<description>.+?)\s+(?P<amount>[+-]?[\d.]+,\d{2})\s*$/u',

        // C6: "2026-03-15  Compra Débito Amazon    -R$ 189,90"
        'c6' => '/^(?P<date>\d{4}-\d{2}-\d{2})\s+(?P<description>.+?)\s+(?P<amount>-?R?\$?\s*[\d.]+,\d{2})\s*$/u',

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

    /** Banks/types that should use the generic credit-card state-machine parser. */
    private const CC_BANKS = ['bradesco_cc', 'nubank_cc', 'inter_cc', 'itau_cc', 'santander_cc', 'c6_cc', 'credit_card'];

    /**
     * @return array{bank: string|null, rows: array<int, array{date: string, description: string, amount: float, type: string}>}
     */
    public function parse(string $filePath): array
    {
        Log::info('[PdfParser] Iniciando parse', ['file' => basename($filePath)]);

        $text = $this->extractText($filePath);

        Log::info('[PdfParser] Texto extraído', [
            'chars' => mb_strlen($text),
            'lines' => substr_count($text, "\n"),
            'preview' => mb_substr($text, 0, 300),
        ]);

        $bank = $this->detectBank($text);
        Log::info('[PdfParser] Banco detectado', ['bank' => $bank ?? 'nenhum (usando generic)']);

        // Any credit card statement uses the generic CC state-machine parser
        if (in_array($bank, self::CC_BANKS, true)) {
            $rows = $this->parseCreditCardLines($text, $bank);
            Log::info('[PdfParser] Resultado credit_card', ['bank' => $bank, 'rows' => count($rows)]);

            return ['bank' => $bank, 'rows' => $rows];
        }

        $pattern = self::BANK_PATTERNS[$bank] ?? self::BANK_PATTERNS['generic'];
        $rows = $this->parseLines($text, $pattern, $bank);

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

        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        if (empty(trim($text))) {
            Log::warning('[PdfParser] Texto extraído está vazio — PDF pode ser baseado em imagem (não OCR)');
        }

        return $text;
    }

    private function detectBank(string $text): ?string
    {
        $textUpper = mb_strtoupper($text);

        // Credit card statements must be checked before their general bank slug
        // so "NUBANK" + "FATURA" → nubank_cc, not nubank (conta corrente)
        $signatures = [
            // ── Credit card statements ────────────────────────────────────────
            'bradesco_cc' => ['HISTÓRICO DE LANÇAMENTOS', 'HISTORICO DE LANCAMENTOS', 'FATURA MENSAL'],
            'nubank_cc' => ['FATURA NUBANK', 'FATURA DO CARTÃO NUBANK', 'RESUMO DA FATURA'],
            'inter_cc' => ['FATURA INTER', 'FATURA CARTÃO INTER', 'FATURA DO CARTÃO INTER'],
            'itau_cc' => ['ITAUCARD', 'FATURA ITAÚ', 'FATURA ITAU', 'FATURA DO CARTÃO ITAÚ'],
            'santander_cc' => ['FATURA SANTANDER', 'FATURA DO CARTÃO SANTANDER'],
            'c6_cc' => ['FATURA C6', 'C6 CARD'],
            // Generic CC fallback — "FATURA" alone, combined with credit-card keywords
            'credit_card' => ['LIMITE DE CRÉDITO', 'LIMITE DE CREDITO', 'LIMITE DISPONÍVEL'],

            // ── Current-account / debit statements ────────────────────────────
            'nubank' => ['NUBANK', 'NU PAGAMENTOS', 'ROXINHO'],
            'inter' => ['BANCO INTER', 'INTER S.A.', 'CONTACERTA'],
            'bradesco' => ['BRADESCO', 'BANCO BRADESCO'],
            'itau' => ['ITAÚ', 'ITAU UNIBANCO'],
            'c6' => ['C6 BANK', 'C6S.A.'],
            'santander' => ['SANTANDER'],
            'caixa' => ['CAIXA ECONÔMICA', 'CAIXA ECONOMICA'],
        ];

        foreach ($signatures as $bank => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($textUpper, mb_strtoupper($keyword))) {
                    return $bank;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Generic credit-card parser (works for any Brazilian bank CC statement)
    // =========================================================================

    /**
     * Generic state-machine parser for Brazilian bank credit card statements.
     *
     * Supported formats across banks:
     *   Bradesco  — "DD/MMDESC\tCITY\tAMOUNT"  (date glued to description, TAB-separated)
     *   Nubank CC — "DD/MM  DESCRIPTION  AMOUNT" (spaces)
     *   Inter CC  — "DD/MM  DESCRIPTION  AMOUNT" or "DD/MM/YYYY  DESC  AMOUNT"
     *   Itaú CC   — "DD/MM  DESCRIPTION  AMOUNT"
     *
     * Strategy:
     *   1. Infer statement year/month from due-date patterns ("Vencimento", "Vencto", etc.)
     *   2. Detect transaction-section boundaries (many common keywords).
     *   3. Any line starting with DD/MM (with or without space before description) is a transaction.
     *   4. Amount is either on the same line (TAB or 2+ spaces) or on the immediately following line.
     *   5. Trailing "-" on amount = credit; leading "-" = debit; default = debit (CC purchase).
     *
     * @return array<int, array{date: string, description: string, amount: float, type: string}>
     */
    private function parseCreditCardLines(string $text, ?string $bank): array
    {
        $rows = [];
        $lines = explode("\n", $text);

        // ── 1. Infer statement year and closing month ─────────────────────────
        $stmtYear = (int) date('Y');
        $stmtMonth = (int) date('m');

        $dueDatePatterns = [
            '/(?:Vencimento|Vencto\.?|Data\s+de\s+Vencimento|Vence\s+em)[:\s]+\d{2}\/(\d{2})\/(\d{4})/iu',
            '/(?:Período|Periodo|Referência)[:\s]+\d{2}\/\d{2}\/\d{4}\s*(?:a|até|-)\s*\d{2}\/(\d{2})\/(\d{4})/iu',
            '/(?:Fatura|Competência)[:\s]+(\d{2})\/(\d{4})/iu',  // "Fatura 03/2026"
        ];
        foreach ($dueDatePatterns as $p) {
            if (preg_match($p, $text, $m)) {
                // Last two captures are always month, year (or month/year)
                $stmtMonth = (int) $m[1];
                $stmtYear = (int) $m[2];
                break;
            }
        }

        Log::debug('[PdfParser] CC — data da fatura inferida', [
            'bank' => $bank,
            'stmtMonth' => $stmtMonth,
            'stmtYear' => $stmtYear,
        ]);

        // ── 2. Section-boundary keywords ──────────────────────────────────────
        // Start: entering a transaction section
        $sectionStartRe = '/^(?:'
            .'Lançamentos'
            .'|Compras\s+no\s+per[ií]odo'
            .'|Movimenta[cç][oõ]es'
            .'|Hist[oó]rico\s+de\s+lan[cç]amentos'
            .'|Suas\s+compras'
            .'|Extrato\s+detalhado'
            .'|Transa[cç][oõ]es'
            .')$/iu';

        // Stop: leaving the transaction section
        $sectionStopRe = '/^(?:'
            .'Total\s+da\s+fatura'
            .'|Total\s+de\s+compras'
            .'|Resumo\s+da\s+fatura'
            .'|Encargos\s+e\s+cobran[cç]as'
            .'|Pagamentos\s+e\s+cr[eé]ditos'
            .'|Mensagem'
            .'|Observa[cç][oõ]es'
            .')/iu';

        // Lines to always skip inside a section
        $skipLineRe = '/^(?:'
            .'Data\s*Hist[oó]rico'       // column header
            .'|Cart[aã]o\s+\d{4}'        // "Cartão XXXX XXXX XXXX 1234"
            .'|Total\s+para\s+'          // "Total para NOME"
            .'|Fatura\s+Mensal'
            .'|Limite\s+de\s+cr[eé]dito'
            .'|Saldo\s+anterior'
            .'|Pagamento\s+efetuado'
            .')/iu';

        // ── 3. Walk the lines ─────────────────────────────────────────────────
        $inSection = false;
        $pendingDate = null;
        $pendingDescription = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Section boundaries
            if (preg_match($sectionStartRe, $line)) {
                $inSection = true;

                continue;
            }
            if (preg_match($sectionStopRe, $line)) {
                $inSection = false;
                $pendingDate = $pendingDescription = null;

                continue;
            }

            // If no section detected yet, still try to parse (some PDFs have no explicit marker)
            // but skip obvious non-transaction lines when outside a known section
            if (! $inSection && ! preg_match('/^\d{2}\/\d{2}/', $line)) {
                continue;
            }

            // Skip known header/footer lines
            if (preg_match($skipLineRe, $line)) {
                $pendingDate = $pendingDescription = null;

                continue;
            }

            // ── Try to match a transaction line starting with DD/MM ────────────
            // Handles:
            //   "25/01SUPERMERCADO\tSAO PAULO\t189,90"  (Bradesco — no space)
            //   "25/01  Supermercado iFood    189,90"    (space-separated)
            //   "25/01/2026  Supermercado     189,90"    (full date)
            if (preg_match('/^(\d{2})\/(\d{2})(?:\/(\d{4}))?(.*)$/u', $line, $m)) {
                $txDay = (int) $m[1];
                $txMonth = (int) $m[2];
                $txYear = $m[3] !== '' ? (int) $m[3]
                    : (($txMonth > $stmtMonth) ? $stmtYear - 1 : $stmtYear);

                try {
                    $date = Carbon::createFromDate($txYear, $txMonth, $txDay)->format('Y-m-d');
                } catch (\Exception) {
                    continue;
                }

                $rest = trim($m[4]);

                // Remove installment suffix like " 04/04" or " 01/12"
                $rest = preg_replace('/\s+\d{2}\/\d{2}\s*$/', '', $rest) ?? $rest;
                $rest = trim($rest);

                [$description, $amount, $type] = $this->splitDescriptionAmount($rest);

                if ($amount !== null && mb_strlen($description) >= 2) {
                    $rows[] = compact('date', 'description', 'amount', 'type');
                    $pendingDate = null;
                    $pendingDescription = null;
                } elseif (mb_strlen($description) >= 2) {
                    // Amount on next line(s)
                    $pendingDate = $date;
                    $pendingDescription = $description;
                }

                continue;
            }

            // ── Standalone amount line (pending multi-line transaction) ────────
            if ($pendingDate !== null) {
                $isCredit = str_ends_with($line, '-');
                $cleaned = rtrim($line, '-');
                $amount = $this->parseAmount($cleaned);

                if ($amount !== null && $amount > 0) {
                    $rows[] = [
                        'date' => $pendingDate,
                        'description' => $pendingDescription,
                        'amount' => $amount,
                        'type' => $isCredit ? 'credit' : 'debit',
                    ];
                    $pendingDate = null;
                    $pendingDescription = null;
                }
                // If not a valid amount it's likely a city/detail continuation — keep pending
            }
        }

        Log::info('[PdfParser] parseCreditCardLines concluído', [
            'bank' => $bank,
            'encontradas' => count($rows),
        ]);

        return $rows;
    }

    /**
     * Split a "rest" string (everything after DD/MM) into description, amount, type.
     * Handles TAB-separated (Bradesco style) and multi-space separated.
     *
     * @return array{0: string, 1: float|null, 2: string}
     */
    private function splitDescriptionAmount(string $rest): array
    {
        $description = $rest;
        $amount = null;
        $type = 'debit';

        if ($rest === '') {
            return [$description, $amount, $type];
        }

        // TAB-separated: DESC\tCITY\tAMOUNT  or  DESC\tAMOUNT
        if (str_contains($rest, "\t")) {
            $parts = explode("\t", $rest);
            $description = trim($parts[0]);

            // Last part might be the amount
            $last = trim(end($parts));
            $isCredit = str_ends_with($last, '-');
            $parsed = $this->parseAmount(rtrim($last, '-'));

            if ($parsed !== null && count($parts) >= 2) {
                $amount = $parsed;
                $type = $isCredit ? 'credit' : 'debit';
            }

            return [$description, $amount, $type];
        }

        // Space-separated: look for amount at end of string
        // Amount pattern: optional leading/trailing sign, digits, comma, 2 decimals
        if (preg_match('/^(.+?)\s{2,}([+\-]?[\d.]+,\d{2}-?)\s*$/u', $rest, $sm)) {
            $description = trim($sm[1]);
            $rawAmount = $sm[2];
            $isCredit = str_ends_with($rawAmount, '-');
            $parsed = $this->parseAmount(rtrim($rawAmount, '-'));

            if ($parsed !== null) {
                $amount = $parsed;
                $type = ($isCredit || $parsed < 0) ? 'credit' : 'debit';
                $amount = abs($amount);
            }
        }

        return [$description, $amount, $type];
    }

    // =========================================================================
    // Regex-based line parser (conta corrente — known simple formats)
    // =========================================================================

    /**
     * @return array<int, array{date: string, description: string, amount: float, type: string}>
     */
    private function parseLines(string $text, string $pattern, ?string $bank): array
    {
        $rows = [];
        $lines = explode("\n", $text);
        $year = (int) date('Y');
        $skipped = 0;
        $noMatch = 0;
        $badDate = 0;
        $badAmount = 0;

        Log::debug('[PdfParser] parseLines iniciado', [
            'banco' => $bank ?? 'generic',
            'linhas' => count($lines),
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
                if ($noMatch <= 20) {
                    Log::debug('[PdfParser] Linha sem match', ['linha' => $i + 1, 'conteudo' => $line]);
                }

                continue;
            }

            $date = $this->parseDate($m['date'], $year);
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
                'date' => $date,
                'description' => trim(preg_replace('/\s+/', ' ', $m['description']) ?? $m['description']),
                'amount' => abs($amount),
                'type' => $amount < 0 ? 'debit' : 'credit',
            ];
        }

        Log::info('[PdfParser] parseLines concluído', [
            'banco' => $bank ?? 'generic',
            'encontradas' => count($rows),
            'sem_match' => $noMatch,
            'curtas_skip' => $skipped,
            'data_invalida' => $badDate,
            'valor_invalido' => $badAmount,
        ]);

        return $rows;
    }

    private function parseDate(string $raw, int $defaultYear): ?string
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

    // =========================================================================
    // Stateful parser — generic fallback for current-account statements
    // =========================================================================

    /**
     * Stateful parser — fallback genérico para extratos de conta corrente de qualquer banco.
     *
     * @return array<int, array{date: string, description: string, amount: float, type: string}>
     */
    private function parseStatefulLines(string $text, ?string $bank): array
    {
        $rows = [];
        $lines = explode("\n", $text);
        $currentDate = null;
        $currentType = null;

        $skipDescriptions = [
            'total de entradas', 'total de saídas', 'saldo inicial', 'saldo final',
            'saldo final do período', 'rendimento líquido', 'movimentações',
            'saldo anterior', 'saldo atual', 'saldo disponível',
        ];

        $datePatterns = [
            'pt_month_year' => '/^(\d{2}\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3}\s+\d{4})/iu',
            'dmy_slash' => '/^(\d{2}\/\d{2}\/\d{2,4})/u',
            'ymd_dash' => '/^(\d{4}-\d{2}-\d{2})/u',
            'dmy_dash' => '/^(\d{2}-\d{2}-\d{4})/u',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 5) {
                continue;
            }

            // Section switch (Nubank conta / similar banks)
            if (preg_match('/^(?:\d{2}\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3}\s+\d{4}\s+)?Total de (entradas|saídas)/iu', $line, $m)) {
                $currentType = str_contains(mb_strtolower($m[1]), 'entrada') ? 'credit' : 'debit';
                if (preg_match('/^(\d{2})\s+([A-ZÁÉÍÓÚÂÊÔÃÕÇ]{3})\s+(\d{4})/iu', $line, $dm)) {
                    $month = self::PT_MONTHS[mb_strtoupper($dm[2])] ?? null;
                    if ($month) {
                        $currentDate = Carbon::createFromDate((int) $dm[3], $month, (int) $dm[1])->format('Y-m-d');
                    }
                }

                continue;
            }

            // Detect date header line
            $detectedDate = null;
            foreach ($datePatterns as $pattern) {
                if (preg_match($pattern, $line, $dm)) {
                    $detectedDate = $this->parseDate($dm[1], (int) date('Y'));
                    break;
                }
            }
            if ($detectedDate !== null) {
                $currentDate = $detectedDate;
            }

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
            $rawAmount = trim($parts[1]);

            if (in_array(mb_strtolower($description), $skipDescriptions, true)) {
                continue;
            }
            if (preg_match('/^\d{2}[\s\/\-]\w{2,3}[\s\/\-]\d{2,4}$/', $description)) {
                continue;
            }

            $amount = $this->parseAmount($rawAmount);
            if ($amount === null) {
                continue;
            }

            $type = $currentType ?? ($amount < 0 ? 'debit' : 'credit');

            $rows[] = [
                'date' => $currentDate,
                'description' => trim(preg_replace('/\s+/', ' ', $description) ?? $description),
                'amount' => abs($amount),
                'type' => $type,
            ];
        }

        Log::info('[PdfParser] parseStatefulLines concluído', [
            'banco' => $bank ?? 'generic',
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
        $clean = ltrim($clean, '+-');

        // Remove thousand separator dots, convert decimal comma
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if (! is_numeric($clean)) {
            return null;
        }

        return $negative ? -(float) $clean : (float) $clean;
    }
}
