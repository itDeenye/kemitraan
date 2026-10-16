<?php

namespace App\Services\Reward;

use App\Libraries\DataTable;
use App\Models\MemberPointTransaction;
use App\Support\PointCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MemberPointHistoryService
{
    /** @param array<string, mixed> $params */
    public function history(int $memberId, array $params): array
    {
        $query = $this->query($memberId, $params);
        $summary = (clone $query)->reorder()->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('COALESCE(SUM(COALESCE(points.member_point_transaction_monthly_points, points.member_point_transaction_quantity)), 0) as monthly_points')
            ->selectRaw('COALESCE(SUM(COALESCE(points.member_point_transaction_annual_points, points.member_point_transaction_quantity)), 0) as annual_points')
            ->first();

        $query->select([
            'points.member_point_transaction_id as id',
            'points.member_point_transaction_trx_id as transaction_id',
            'trx.trx_code as transaction_code',
            'trx.trx_is_preorder as is_preorder',
            'points.member_point_transaction_quantity as quantity',
            'points.member_point_transaction_approved_datetime as approved_at',
        ])
            ->selectRaw('COALESCE(points.member_point_transaction_monthly_points, points.member_point_transaction_quantity) as monthly_points')
            ->selectRaw('COALESCE(points.member_point_transaction_annual_points, points.member_point_transaction_quantity) as annual_points');

        return [
            'data' => DataTable::select([
                'point_history.id as id',
                'point_history.transaction_id as transaction_id',
                'point_history.transaction_code as transaction_code',
                'point_history.is_preorder as is_preorder',
                'point_history.quantity as quantity',
                'point_history.monthly_points as monthly_points',
                'point_history.annual_points as annual_points',
                'point_history.approved_at as approved_at',
            ])->fromSub($query, 'point_history')->defaultSort('-approved_at,-id')->get($params),
            'summary' => [
                'monthly_points' => PointCalculation::integer($summary->monthly_points),
                'annual_points' => PointCalculation::integer($summary->annual_points),
                'transaction_count' => (int) $summary->transaction_count,
            ],
            'available_years' => MemberPointTransaction::query()
                ->where('member_point_transaction_member_id', $memberId)
                ->distinct()
                ->orderByDesc('member_point_transaction_year')
                ->pluck('member_point_transaction_year')->map(fn ($year): int => (int) $year)->all(),
        ];
    }

    /** @param array<string, mixed> $params */
    private function query(int $memberId, array $params): Builder
    {
        $query = DB::table('member_point_transaction as points')
            ->leftJoin('trx', 'trx.trx_id', '=', 'points.member_point_transaction_trx_id')
            ->where('points.member_point_transaction_member_id', $memberId);

        if (isset($params['year'])) {
            $from = CarbonImmutable::create((int) $params['year'], (int) ($params['month'] ?? 1), 1)->startOfDay();
            $query->whereBetween('points.member_point_transaction_approved_datetime', [
                $from,
                isset($params['month']) ? $from->endOfMonth() : $from->endOfYear(),
            ]);
        } elseif (isset($params['month'])) {
            $query->whereMonth('points.member_point_transaction_approved_datetime', (int) $params['month']);
        }

        return $query;
    }
}
