<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrxSupplyChainSync extends Model
{
    protected $table = 'trx_supply_chain_syncs';

    protected $primaryKey = 'trx_supply_chain_sync_id';

    public const CREATED_AT = 'trx_supply_chain_sync_created_datetime';

    public const UPDATED_AT = 'trx_supply_chain_sync_updated_datetime';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'trx_supply_chain_sync_url' => 'array',
            'trx_supply_chain_sync_param' => 'array',
            'trx_supply_chain_sync_response' => 'array',
            'trx_supply_chain_sync_synced_datetime' => 'datetime',
            'trx_supply_chain_sync_created_datetime' => 'datetime',
            'trx_supply_chain_sync_updated_datetime' => 'datetime',
        ];
    }

    public function trx(): BelongsTo
    {
        return $this->belongsTo(Trx::class, 'trx_supply_chain_sync_trx_id', 'trx_id');
    }
}
