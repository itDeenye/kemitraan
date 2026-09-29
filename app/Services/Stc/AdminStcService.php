<?php

namespace App\Services\Stc;

use App\Contracts\Integrations\StcGateway;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AdminStcService
{
    public function __construct(private readonly StcGateway $gateway) {}

    /** @return array{balance: int|float} */
    public function balance(): array
    {
        $data = $this->gateway->balance();

        return [
            'balance' => $this->number(Arr::get($data, 'ewallet_last_balance', 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: array<int, array<string, mixed>>, pagination?: array<string, mixed>}
     */
    public function mutations(array $params): array
    {
        $data = $this->gateway->mutations($params);

        return $this->normalizedList($data, fn (array $row): array => $this->mutation($row));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: array<int, array<string, mixed>>, pagination?: array<string, mixed>}
     */
    public function topUps(array $params): array
    {
        $data = $this->gateway->topUps($params);

        return $this->normalizedList($data, fn (array $row): array => [
            ...$this->mutation($row),
            'sender_name' => null,
            'status' => 'completed',
            'payment_reference' => $this->paymentReference((string) ($row['ewallet_log_note'] ?? '')),
        ]);
    }

    /** @return array{banks: array<int, array<string, mixed>>, top_up_code: string|null} */
    public function topUpOptions(): array
    {
        $data = $this->gateway->topUpOptions();
        $banks = Arr::get($data, 'bank', []);

        return [
            'banks' => collect(is_array($banks) ? $banks : [])->map(fn (mixed $bank): array => [
                'id' => (int) Arr::get((array) $bank, 'stc_topup_va_id'),
                'bank_id' => (int) Arr::get((array) $bank, 'stc_topup_va_bank_id'),
                'bank_name' => Arr::get((array) $bank, 'stc_topup_va_bank_name'),
                'bank_code' => Arr::get((array) $bank, 'stc_topup_va_bank_code'),
                'virtual_account' => Arr::get((array) $bank, 'stc_topup_va_number'),
                'is_active' => (bool) Arr::get((array) $bank, 'stc_topup_va_is_active'),
            ])->values()->all(),
            'top_up_code' => ($code = Arr::get($data, 'code_topup_va')) !== null ? (string) $code : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(array<string, mixed>): array<string, mixed>  $normalizer
     * @return array{results: array<int, array<string, mixed>>, pagination?: array<string, mixed>}
     */
    private function normalizedList(array $data, callable $normalizer): array
    {
        $rows = Arr::get($data, 'results', []);
        $result = [
            'results' => collect(is_array($rows) ? $rows : [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(fn (array $row): array => $normalizer($row))
                ->values()
                ->all(),
        ];

        if (is_array($data['pagination'] ?? null)) {
            $result['pagination'] = $data['pagination'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mutation(array $row): array
    {
        $note = (string) ($row['ewallet_log_note'] ?? '');

        return [
            'id' => (int) ($row['ewallet_log_id'] ?? 0),
            'transaction_id' => ($transactionId = (int) ($row['ewallet_log_transaction_id'] ?? 0)) > 0
                ? $transactionId
                : null,
            'category' => $row['ewallet_log_category'] ?? null,
            'type' => $row['ewallet_log_type'] ?? null,
            'bank_name' => $this->bankName($note),
            'amount' => $this->number($row['ewallet_log_value'] ?? 0),
            'service_fee' => null,
            'recorded_balance' => null,
            'ending_balance' => null,
            'note' => $note,
            'datetime' => $row['ewallet_log_datetime'] ?? null,
        ];
    }

    private function bankName(string $note): ?string
    {
        if (! preg_match('/\b(Bank\s+.+?)\s+payment\s+id\b/i', $note, $matches)) {
            return null;
        }

        return Str::squish($matches[1]);
    }

    private function paymentReference(string $note): ?string
    {
        if (! preg_match('/\bpayment\s+id\s+(\S+)/i', $note, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function number(mixed $value): int|float
    {
        $numeric = abs((float) $value);

        return floor($numeric) === $numeric ? (int) $numeric : $numeric;
    }
}
