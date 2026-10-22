<?php

namespace App\Services\Stc;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\ShippingCourierExpress;
use App\Models\Trx;
use Illuminate\Support\Facades\DB;

class ShippingCostReportService
{
    public function report(array $params): array
    {
        $table = (new ShippingCourierExpress)->getTable();
        $prefix = 'shipping_courier_express';
        $snapshots = DB::table($table.' as shipment')->where("shipment.{$prefix}_ref_type", 'trx')
            ->whereRaw("shipment.{$prefix}_id = (SELECT MAX(latest.{$prefix}_id) FROM {$table} latest WHERE latest.{$prefix}_ref_type = 'trx' AND latest.{$prefix}_ref_id = shipment.{$prefix}_ref_id)")
            ->whereRaw("TRIM(COALESCE(shipment.{$prefix}_order_id, '')) <> ''")
            ->selectRaw("shipment.{$prefix}_ref_id as transaction_id, ? as method, shipment.{$prefix}_awb as awb, COALESCE(shipment.{$prefix}_expedition_name, '') as courier, COALESCE(shipment.{$prefix}_expedition_service, '') as service, COALESCE(shipment.{$prefix}_cost, 0) as base_cost, COALESCE(shipment.{$prefix}_insurance, 0) as insurance, 0 as admin_fee", ['courier_express']);
        $rows = DB::query()->fromSub($snapshots, 'shipment')->join((new Trx)->getTable().' as orders', 'orders.trx_id', '=', 'shipment.transaction_id')
            ->leftJoin((new Member)->getTable().' as buyer', function ($join): void {
                $join->on('buyer.member_id', '=', 'orders.trx_buyer_id')->whereIn('orders.trx_buyer_type', ['distributor', 'agent', 'reseller']);
            })
            ->whereColumn('orders.trx_shipping_method', 'shipment.method')->where('orders.trx_shipping_cost', 0)
            ->whereIn('orders.trx_status', ['shipped', 'delivered', 'ready_to_pickup', 'picked_up', 'received', 'completed', 'reship_required'])
            ->whereRaw('(shipment.base_cost + shipment.insurance + shipment.admin_fee) > 0')
            ->select(['orders.trx_id as id', 'orders.trx_code as code', 'orders.trx_datetime as datetime',
                'orders.trx_status as status', 'orders.trx_is_preorder as is_preorder', 'orders.trx_seller_type as seller_type', 'orders.trx_seller_id as seller_id',
                'buyer.member_name as buyer_name', 'buyer.member_code as buyer_code', 'orders.trx_buyer_type as buyer_type',
                'shipment.method', 'shipment.awb', 'shipment.courier', 'shipment.service', 'shipment.base_cost', 'shipment.insurance', 'shipment.admin_fee'])
            ->selectRaw('0 as buyer_shipping_cost, (shipment.base_cost + shipment.insurance + shipment.admin_fee) as uncharged_cost');
        $columns = ['id', 'code', 'datetime', 'status', 'is_preorder', 'seller_type', 'seller_id', 'buyer_name', 'buyer_code', 'buyer_type',
            'method', 'awb', 'courier', 'service', 'base_cost', 'insurance', 'admin_fee', 'buyer_shipping_cost', 'uncharged_cost'];
        $data = DataTable::select($columns)->fromSub($rows, 'report')->search(['code', 'buyer_name', 'buyer_code', 'awb'])->defaultSort('-datetime')->get($params);
        foreach ($data['results'] as $row) {
            $row->is_preorder = (bool) $row->is_preorder;
            foreach (['id', 'seller_id', 'base_cost', 'insurance', 'admin_fee', 'buyer_shipping_cost', 'uncharged_cost'] as $field) {
                $row->{$field} = (int) $row->{$field};
            }
        }

        return $data;
    }
}
