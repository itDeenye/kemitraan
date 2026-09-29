<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: warehouse_stock_log
 */
class WarehouseStockLog extends Model
{
    protected $table = 'warehouse_stock_log';

    protected $primaryKey = 'warehouse_stock_log_id';

    public $timestamps = false;

    protected $fillable = [
        'warehouse_stock_log_warehouse_id',
        'warehouse_stock_log_product_id',
        'warehouse_stock_log_type',
        'warehouse_stock_log_quantity',
        'warehouse_stock_log_unit_price',
        'warehouse_stock_log_balance',
        'warehouse_stock_log_note',
        'warehouse_stock_log_datetime',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_stock_log_quantity' => 'integer',
            'warehouse_stock_log_unit_price' => 'integer',
            'warehouse_stock_log_balance' => 'integer',
            'warehouse_stock_log_datetime' => 'datetime',
        ];
    }
}
