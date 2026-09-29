<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStockAdjustment extends Model
{
    protected $table = 'warehouse_stock_adjustment';

    protected $primaryKey = 'stock_adjustment_id';

    public $timestamps = false;

    protected $fillable = [
        'stock_adjustment_administrator_id',
        'stock_adjustment_warehouse_id',
        'stock_adjustment_code',
        'stock_adjustment_note',
        'stock_adjustment_datetime',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(SiteAdministrator::class, 'stock_adjustment_administrator_id', 'administrator_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'stock_adjustment_warehouse_id', 'warehouse_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(
            WarehouseStockAdjustmentDetail::class,
            'stock_adjustment_detail_stock_adjustment_id',
            'stock_adjustment_id',
        );
    }

    protected function casts(): array
    {
        return ['stock_adjustment_datetime' => 'datetime'];
    }
}
