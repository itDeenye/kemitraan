<?php

namespace App\Services\Reward;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\Trx;
use App\Models\TrxSpreadPayment;

class MemberSharingProfitService
{
    /** @param array<string, mixed> $params */
    public function sharingProfits(int $memberId, array $params): array
    {
        $spreadTable = (new TrxSpreadPayment)->getTable();
        $trxTable = (new Trx)->getTable();
        $memberTable = (new Member)->getTable();

        return DataTable::select([
            "{$spreadTable}.trx_spread_payment_id as id",
            "{$spreadTable}.trx_spread_payment_trx_id as trx_id",
            "{$trxTable}.trx_code as trx_code",
            "{$trxTable}.trx_grand_total_price as trx_price",
            "{$spreadTable}.trx_spread_payment_member_id as buyer_id",
            "buyer.member_code as buyer_code",
            "buyer.member_name as buyer_name",
            "{$spreadTable}.trx_spread_payment_percentage as percentage",
            "{$spreadTable}.trx_spread_payment_amount as amount",
            "{$spreadTable}.trx_spread_payment_status as status",
            "{$spreadTable}.trx_spread_payment_bank_id as bank_id",
            "{$spreadTable}.trx_spread_payment_account_name as account_name",
            "{$spreadTable}.trx_spread_payment_account_number as account_number",
            "{$spreadTable}.trx_spread_payment_note as note",
            "{$spreadTable}.trx_spread_payment_created_datetime as created_at",
            "{$spreadTable}.trx_spread_payment_approved_datetime as approved_at",
            "{$spreadTable}.trx_spread_payment_paid_datetime as paid_at",
        ])
            ->from($spreadTable)
            ->leftJoin($trxTable, "{$trxTable}.trx_id = {$spreadTable}.trx_spread_payment_trx_id")
            ->leftJoin("{$memberTable} as buyer", "buyer.member_id = {$spreadTable}.trx_spread_payment_member_id")
            ->where("{$spreadTable}.trx_spread_payment_upline_id", $memberId)
            ->search(['trx_code', 'buyer_code', 'buyer_name'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function sharingProfit(TrxSpreadPayment $sharingProfit): TrxSpreadPayment
    {
        return $sharingProfit->load([
            'trx:trx_id,trx_code,trx_grand_total_price',
            'member:member_id,member_code,member_name',
        ]);
    }
}
