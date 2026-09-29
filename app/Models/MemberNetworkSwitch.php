<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberNetworkSwitch extends Model
{
    protected $table = 'member_network_switch';

    protected $primaryKey = 'network_switch_transfer_id';

    public $timestamps = false;

    protected $fillable = [
        'network_switch_batch_uuid',
        'network_switch_upgrade_qualified_id',
        'network_switch_member_id',
        'network_switch_from_parent_member_id',
        'network_switch_to_parent_member_id',
        'network_switch_from_level_id',
        'network_switch_to_level_id',
        'network_switch_type',
        'network_switch_status',
        'network_switch_admin_id',
        'network_switch_approved_datetime',
        'network_switch_effective_date',
        'network_switch_applied_datetime',
        'network_switch_transfer_note',
        'network_switch_created_datetime',
    ];

    public function upgradeQualification(): BelongsTo
    {
        return $this->belongsTo(
            MemberUpgradeQualified::class,
            'network_switch_upgrade_qualified_id',
            'member_upgrade_qualified_id'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'network_switch_member_id', 'member_id');
    }

    public function fromParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'network_switch_from_parent_member_id', 'member_id');
    }

    public function toParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'network_switch_to_parent_member_id', 'member_id');
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'network_switch_from_level_id', 'member_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'network_switch_to_level_id', 'member_level_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(SiteAdministrator::class, 'network_switch_admin_id', 'administrator_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(
            MemberHistory::class,
            'member_history_network_transfer_id',
            'network_switch_transfer_id'
        );
    }

    protected function casts(): array
    {
        return [
            'network_switch_approved_datetime' => 'datetime',
            'network_switch_effective_date' => 'date',
            'network_switch_applied_datetime' => 'datetime',
            'network_switch_created_datetime' => 'datetime',
        ];
    }
}
