<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberHistory extends Model
{
    protected $table = 'member_history';

    protected $primaryKey = 'member_history_id';

    public $timestamps = false;

    protected $fillable = [
        'member_history_member_id',
        'member_history_upgrade_qualified_id',
        'member_history_network_transfer_id',
        'member_history_action',
        'member_history_from_level_id',
        'member_history_to_level_id',
        'member_history_upline_member_id',
        'member_history_upline_member_level_id',
        'member_history_downline_member_id',
        'member_history_reason',
        'member_history_approved_by',
        'member_history_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_history_member_id', 'member_id');
    }

    public function upgradeQualification(): BelongsTo
    {
        return $this->belongsTo(
            MemberUpgradeQualified::class,
            'member_history_upgrade_qualified_id',
            'member_upgrade_qualified_id'
        );
    }

    public function networkTransfer(): BelongsTo
    {
        return $this->belongsTo(
            MemberNetworkSwitch::class,
            'member_history_network_transfer_id',
            'network_switch_transfer_id'
        );
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_history_from_level_id', 'member_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_history_to_level_id', 'member_level_id');
    }

    public function uplineLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_history_upline_member_level_id', 'member_level_id');
    }

    public function upline(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_history_upline_member_id', 'member_id');
    }

    public function downline(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_history_downline_member_id', 'member_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(
            SiteAdministrator::class,
            'member_history_approved_by',
            'administrator_id'
        );
    }

    protected function casts(): array
    {
        return [
            'member_history_datetime' => 'datetime',
        ];
    }
}
