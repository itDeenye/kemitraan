<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSupplyChainSync extends Model
{
    protected $table = 'member_supply_chain_syncs';

    protected $primaryKey = 'member_supply_chain_sync_id';

    public const CREATED_AT = 'member_supply_chain_sync_created_datetime';

    public const UPDATED_AT = 'member_supply_chain_sync_updated_datetime';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'member_supply_chain_sync_param' => 'array',
            'member_supply_chain_sync_response' => 'array',
            'member_supply_chain_sync_synced_datetime' => 'datetime',
            'member_supply_chain_sync_created_datetime' => 'datetime',
            'member_supply_chain_sync_updated_datetime' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_supply_chain_sync_member_id', 'member_id');
    }
}
