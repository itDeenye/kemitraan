<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: warehouse_stock
 */
class WarehouseStock extends Model
{
    protected $table = 'warehouse_stock';

    protected $primaryKey = 'warehouse_stock_id';

    public $timestamps = false;

    protected $fillable = [
        'warehouse_stock_warehouse_id',
        'warehouse_stock_product_id',
        'warehouse_stock_balance',
        'warehouse_stock_transfer_in',
        'warehouse_stock_transfer_out',
    ];

    protected function casts(): array
    {
        return [
            'warehouse_stock_balance' => 'integer',
            'warehouse_stock_transfer_in' => 'integer',
            'warehouse_stock_transfer_out' => 'integer',
        ];
    }
}
