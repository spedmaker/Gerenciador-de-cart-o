<?php

namespace App\Services\Parsers;

class CarrefourPdfParser implements PdfParserInterface
{
    private int $referenceYear  = 0;
    private int $referenceMonth = 0;

    public function supports(string $text): bool
    {
        return str_contains(strtoupper($text), 'CARREFOUR');
    }

    public function parse(string $pdfPath, string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $meta  = $this->extractMeta($lines);

        if (empty($meta['reference_month'])) {
            throw new \RuntimeException('Não foi possível identificar o mês de referência. Verifique se o PDF é uma fatura válida.');
        }

        if ($meta['due_date']) {
            $this->referenceYear  = (int) substr($meta['due_date'], 0, 4);
            $this->referenceMonth = (int) substr($meta['due_date'], 5, 2);
        } else {
            $this->referenceYear  = (int) date('Y');
            $this->referenceMonth = (int) date('m');
        }

        $blocks       = $this->splitByCardHolder($lines);
        $transactions = [];

        foreach ($lines as $line) {
            if (preg_match('/^SALDO FATURA ANTERIOR\t([\d.,]+)(-)?$/u', $line, $m)) {
                $transactions[] = [
                    'card_holder'           => 'TITULAR',
                    'card_last_digits'      => '0000',
                    'date'                  => null,
                    'description'           => 'SALDO FATURA ANTERIOR',
                    'amount'                => $this->parseAmount($m[1]),
                    'installment'           => null,
                    'installment_group_key' => null,
                    'is_payment'            => true,
                    'category'              => null,
                ];
                break;
            }
        }

        foreach ($blocks as $block) {
            foreach ($this->parseTransactions($block['lines'], $block['holder']['name'], $block['holder']['digits']) as $tx) {
                $transactions[] = $tx;
            }
        }

        return ['meta' => $meta, 'transactions' => $transactions];
    }

    // -------------------------------------------------------------------------

    private function splitByCardHolder(array $lines): array
    {
        $blocks  = [];
        $current = null;
        $buffer  = [];

        foreach ($lines as $line) {
            if (preg_match('/^([A-ZÁÀÂÃÉÊÍÓÔÕÚÜÇ ]+)\t\d{6}\*+(\d{4})$/u', $line, $m)) {
                if ($current !== null) {
                    $blocks[] = ['holder' => $current, 'lines' => $buffer];
                }
                $current = ['name' => trim($m[1]), 'digits' => $m[2]];
                $buffer  = [];
                continue;
            }

            if ($current !== null) {
                $buffer[] = $line;
            }
        }

        if ($current !== null && !empty($buffer)) {
            $blocks[] = ['holder' => $current, 'lines' => $buffer];
        }

        return $blocks;
    }

    private function parseTransactions(array $lines, string $cardHolder, string $cardLastDigits): array
    {
        $transactions = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) continue;
            if (str_contains($line, 'DATADESCRIÇÃO') || str_starts_with($line, 'LANÇAMENTOS')) continue;
            if (str_starts_with($line, 'TOTAL DA FATURA')) continue;

            if (preg_match('/^(SALDO FATURA ANTERIOR|PAGAMENTO .+?)\t([\d.,]+)(-)?$/u', $line, $m)) {
                $transactions[] = $this->buildTx(
                    cardHolder: $cardHolder,
                    cardLastDigits: $cardLastDigits,
                    date: null,
                    description: trim($m[1]),
                    amount: $this->parseAmount($m[2]),
                    isCredit: isset($m[3]),
                    installment: null,
                    isPayment: true,
                );
                continue;
            }

            if (!preg_match(
                '/^(\d{2}\/\d{2})(.+?)(?:\s*-\s*(\d+\/\d+))?\t([\d.,]+)(-)?$/',
                $line,
                $m
            )) {
                continue;
            }

            $rawDate     = $m[1];
            $description = trim($m[2]);
            $installStr  = $m[3] ?? null;
            $amount      = $this->parseAmount($m[4]);
            $isCredit    = isset($m[5]) && $m[5] === '-';

            $installment = null;
            if ($installStr && preg_match('/(\d+)\/(\d+)/', $installStr, $im)) {
                $installment = ['current' => (int)$im[1], 'total' => (int)$im[2]];
            }

            $transactions[] = $this->buildTx(
                cardHolder: $cardHolder,
                cardLastDigits: $cardLastDigits,
                date: $this->resolveDate($rawDate),
                description: $description,
                amount: $isCredit ? -$amount : $amount,
                isCredit: $isCredit,
                installment: $installment,
                isPayment: $this->isPaymentLine($description),
            );
        }

        return $transactions;
    }

    private function buildTx(
        string $cardHolder,
        string $cardLastDigits,
        ?string $date,
        string $description,
        float $amount,
        bool $isCredit,
        ?array $installment,
        bool $isPayment,
    ): array {
        return [
            'card_holder'           => $cardHolder,
            'card_last_digits'      => $cardLastDigits,
            'date'                  => $date,
            'description'           => $description,
            'amount'                => $amount,
            'installment'           => $installment,
            'installment_group_key' => $installment
                ? md5(strtolower($description) . '|' . $cardHolder)
                : null,
            'is_payment'            => $isPayment,
            'category'              => null,
        ];
    }

    // -------------------------------------------------------------------------

    private function resolveDate(string $ddmm): string
    {
        [$day, $month] = explode('/', $ddmm);
        $day   = (int)$day;
        $month = (int)$month;
        $year  = $this->referenceYear;

        if ($month > $this->referenceMonth + 2) {
            $year--;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function parseAmount(string $raw): float
    {
        $raw = preg_replace('/[^0-9,.]/', '', trim($raw));
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }
        return (float)$raw;
    }

    private function isPaymentLine(string $description): bool
    {
        $keywords = ['PAGAMENTO', 'SALDO FATURA ANTERIOR', 'SALDO ANTERIOR', 'CREDITO EM CONTA'];
        $upper    = strtoupper($description);
        foreach ($keywords as $kw) {
            if (str_contains($upper, $kw)) return true;
        }
        return false;
    }

    private function extractMeta(array $lines): array
    {
        $meta = [
            'bank_label'       => 'Carrefour Mastercard',
            'reference_month'  => null,
            'due_date'         => null,
            'closing_date'     => null,
            'total_amount'     => null,
            'previous_balance' => null,
        ];

        $fullText = implode("\n", $lines);

        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+R\$\s*[\d.,]+/', $fullText, $m)) {
            $meta['due_date'] = $this->parseDateFull($m[1]);
        }

        if (preg_match('/FECHAMENTO DA PRÓXIMA FATURA\s+(\d{2}\/\d{2}\/\d{4})/u', $fullText, $m)) {
            $meta['closing_date'] = $this->parseDateFull($m[1]);
        }

        if (preg_match('/TOTAL DA FATURA ATUAL:\s*R\$\s*([\d.,]+)/u', $fullText, $m)) {
            $meta['total_amount'] = $this->parseAmount($m[1]);
        }

        if (preg_match('/SALDO FATURA ANTERIOR\t([\d.,]+)/u', $fullText, $m)) {
            $meta['previous_balance'] = $this->parseAmount($m[1]);
        }

        if ($meta['due_date']) {
            $dt = \Carbon\Carbon::parse($meta['due_date'])->subMonth();
            $meta['reference_month'] = $dt->format('Y-m');
        }

        return $meta;
    }

    private function parseDateFull(string $d): string
    {
        [$day, $month, $year] = explode('/', $d);
        return "{$year}-{$month}-{$day}";
    }
}
