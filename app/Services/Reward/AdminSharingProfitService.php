<?php

namespace App\Services\Reward;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\SiteAdministrator;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class AdminSharingProfitService
{
    private const INVALID_TRANSACTION_STATUSES = [
        'waiting_payment',
        'waiting_payment_approval',
        'rejected',
        'cancelled',
    ];

    /** @param array<string, mixed> $params */
    public function list(array $params): array
    {
        $spreadTable = (new TrxSpreadPayment)->getTable();
        $memberTable = (new Member)->getTable();
        $trxTable = (new Trx)->getTable();

        $query = DataTable::select([
            "{$spreadTable}.trx_spread_payment_upline_id as upline_id",
            "{$memberTable}.member_code as upline_code",
            "{$memberTable}.member_name as upline_name",
            DB::raw("SUM({$trxTable}.trx_grand_total_price) as total_trx_price"),
            DB::raw("SUM({$spreadTable}.trx_spread_payment_amount) as total_amount"),
            DB::raw("COUNT({$spreadTable}.trx_spread_payment_id) as transaction_count"),
            DB::raw("SUM(CASE WHEN {$spreadTable}.trx_spread_payment_status = 'submitted' THEN 1 ELSE 0 END) as submitted_count"),
            DB::raw("SUM(CASE WHEN {$spreadTable}.trx_spread_payment_status = 'approved' THEN 1 ELSE 0 END) as approved_count"),
        ])
            ->from($spreadTable)
            ->leftJoin($memberTable, "{$memberTable}.member_id = {$spreadTable}.trx_spread_payment_upline_id")
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$spreadTable}.trx_spread_payment_trx_id")
            ->whereIn("{$spreadTable}.trx_spread_payment_status", ['submitted', 'approved'])
            ->whereRaw("{$spreadTable}.trx_spread_payment_receipt_file IS NOT NULL")
            ->where("{$spreadTable}.trx_spread_payment_receipt_file", '<>', '')
            ->whereNotIn("{$trxTable}.trx_status", self::INVALID_TRANSACTION_STATUSES)
            ->groupBy([
                "{$spreadTable}.trx_spread_payment_upline_id",
                "{$memberTable}.member_code",
                "{$memberTable}.member_name",
            ])
            ->search(['upline_name', 'upline_code'])
            ->defaultSort('-upline_id');

        if (isset($params['pagination_bool']) && in_array($params['pagination_bool'], ['false', '0', false, 0], true)) {
            $query->allowUnpaginated();
        }

        return $query->get($params);
    }

    /** @param array<string, mixed> $params */
    public function detail(Member $upline, array $params): array
    {
        $spreadTable = (new TrxSpreadPayment)->getTable();
        $memberTable = (new Member)->getTable();
        $trxTable = (new Trx)->getTable();

        $query = DataTable::select([
            "{$spreadTable}.trx_spread_payment_id as id",
            "{$spreadTable}.trx_spread_payment_transfer_datetime as submitted_datetime",
            "{$spreadTable}.trx_spread_payment_approved_datetime as approved_datetime",
            "{$spreadTable}.trx_spread_payment_paid_datetime as paid_datetime",
            "{$trxTable}.trx_code as trx_code",
            'buyer_member.member_name as buyer_name',
            "{$trxTable}.trx_grand_total_price as trx_price",
            "{$spreadTable}.trx_spread_payment_amount as amount",
            "{$spreadTable}.trx_spread_payment_receipt_file as receipt_url",
            "{$spreadTable}.trx_spread_payment_note as note",
            "{$spreadTable}.trx_spread_payment_status as status",
        ])
            ->from($spreadTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$spreadTable}.trx_spread_payment_trx_id")
            ->leftJoin("{$memberTable} as buyer_member", "buyer_member.member_id = {$spreadTable}.trx_spread_payment_member_id")
            ->where("{$spreadTable}.trx_spread_payment_upline_id", $upline->member_id)
            ->whereIn("{$spreadTable}.trx_spread_payment_status", ['submitted', 'approved'])
            ->whereRaw("{$spreadTable}.trx_spread_payment_receipt_file IS NOT NULL")
            ->where("{$spreadTable}.trx_spread_payment_receipt_file", '<>', '')
            ->whereNotIn("{$trxTable}.trx_status", self::INVALID_TRANSACTION_STATUSES)
            ->search(['trx_code', 'buyer_name'])
            ->defaultSort('-id');

        if (isset($params['pagination_bool']) && in_array($params['pagination_bool'], ['false', '0', false, 0], true)) {
            $query->allowUnpaginated();
        }

        return $query->get($params);
    }

    /** @param array<int> $uplineIds */
    public function approve(array $uplineIds, SiteAdministrator $administrator): int
    {
        if (empty($uplineIds)) {
            return 0;
        }

        $processed = TrxSpreadPayment::query()
            ->whereIn('trx_spread_payment_upline_id', $uplineIds)
            ->where('trx_spread_payment_status', 'submitted')
            ->whereNotNull('trx_spread_payment_receipt_file')
            ->where('trx_spread_payment_receipt_file', '<>', '')
            ->whereHas('trx', fn ($query) => $query->whereNotIn('trx_status', self::INVALID_TRANSACTION_STATUSES))
            ->update([
                'trx_spread_payment_status' => 'approved',
                'trx_spread_payment_approved_by' => $administrator->getKey(),
                'trx_spread_payment_approved_datetime' => now(),
            ]);

        if ($processed === 0) {
            throw new ProcessException('Tidak ada bukti spread payment yang menunggu persetujuan.');
        }

        return $processed;
    }

    /** @param array<int> $uplineIds */
    public function transfer(
        array $uplineIds,
        SiteAdministrator $administrator,
        ?string $note,
    ): int {
        return DB::transaction(function () use ($uplineIds, $administrator, $note): int {
            $payments = TrxSpreadPayment::query()
                ->whereIn('trx_spread_payment_upline_id', $uplineIds)
                ->where('trx_spread_payment_status', 'approved')
                ->whereNotNull('trx_spread_payment_receipt_file')
                ->where('trx_spread_payment_receipt_file', '<>', '')
                ->whereHas('trx', fn ($query) => $query->whereNotIn('trx_status', self::INVALID_TRANSACTION_STATUSES))
                ->lockForUpdate()
                ->get(['trx_spread_payment_id']);

            if ($payments->isEmpty()) {
                throw new ProcessException('Tidak ada sharing profit yang sudah disetujui untuk ditransfer.');
            }

            $attributes = [
                'trx_spread_payment_status' => 'paid',
                'trx_spread_payment_paid_by' => $administrator->getKey(),
                'trx_spread_payment_paid_datetime' => now(),
            ];

            if ($note !== null) {
                $attributes['trx_spread_payment_note'] = $note;
            }

            return TrxSpreadPayment::query()
                ->whereIn(
                    'trx_spread_payment_id',
                    $payments->pluck('trx_spread_payment_id')->all(),
                )
                ->update($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function historyList(array $params): array
    {
        $spreadTable = 'trx_spread_payment';
        $trxTable = 'trx';
        $memberTable = 'member';

        return DataTable::select([
            "{$spreadTable}.trx_spread_payment_id as id",
            "{$spreadTable}.trx_spread_payment_paid_datetime as paid_datetime",
            "{$trxTable}.trx_code",
            "{$trxTable}.trx_grand_total_price as trx_price",
            'upline.member_name as mitra_name',
            "{$spreadTable}.trx_spread_payment_amount as amount",
            "{$spreadTable}.trx_spread_payment_note as note",
            "{$spreadTable}.trx_spread_payment_status as status",
        ])
            ->from($spreadTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$spreadTable}.trx_spread_payment_trx_id")
            ->leftJoin("{$memberTable} as upline", "upline.member_id = {$spreadTable}.trx_spread_payment_upline_id")
            ->where("{$spreadTable}.trx_spread_payment_status", 'paid')
            ->search(['trx_code', 'mitra_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function historyDetail(int $id): object
    {
        $spreadTable = 'trx_spread_payment';
        $trxTable = 'trx';
        $memberTable = 'member';

        $detail = DataTable::select([
            "{$spreadTable}.trx_spread_payment_id as id",
            "{$spreadTable}.trx_spread_payment_paid_datetime as paid_datetime",
            "{$trxTable}.trx_code",
            "{$trxTable}.trx_grand_total_price as trx_price",
            'upline.member_name as mitra_name',
            "{$spreadTable}.trx_spread_payment_amount as amount",
            "{$spreadTable}.trx_spread_payment_receipt_file as receipt_url",
            "{$spreadTable}.trx_spread_payment_note as note",
            "{$spreadTable}.trx_spread_payment_status as status",
        ])
            ->from($spreadTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$spreadTable}.trx_spread_payment_trx_id")
            ->leftJoin("{$memberTable} as upline", "upline.member_id = {$spreadTable}.trx_spread_payment_upline_id")
            ->where("{$spreadTable}.trx_spread_payment_id", $id)
            ->get(['page' => 1, 'limit' => 1]);

        if (empty($detail['results'])) {
            throw new ModelNotFoundException('Sharing profit history not found.');
        }

        return (object) $detail['results'][0];
    }
}
