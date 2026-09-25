<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberUpgradeQualified extends Model
{
    protected $table = 'member_upgrade_qualified';

    protected $primaryKey = 'member_upgrade_qualified_id';

    public $timestamps = false;

    protected $fillable = [
        'member_upgrade_qualified_member_id',
        'member_upgrade_qualified_from_level_id',
        'member_upgrade_qualified_to_level_id',
        'member_upgrade_qualified_from_year_month',
        'member_upgrade_qualified_to_year_month',
        'member_upgrade_qualified_status',
        'member_upgrade_qualified_admin_id',
        'member_upgrade_qualified_approved_datetime',
        'member_upgrade_qualified_effective_date',
        'member_upgrade_qualified_applied_datetime',
        'member_upgrade_qualified_last_update_datetime',
        'member_upgrade_qualified_created_datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_upgrade_qualified_member_id', 'member_id');
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_upgrade_qualified_from_level_id', 'member_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_upgrade_qualified_to_level_id', 'member_level_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(SiteAdministrator::class, 'member_upgrade_qualified_admin_id', 'administrator_id');
    }

    public function networkTransfers(): HasMany
    {
        return $this->hasMany(
            MemberNetworkSwitch::class,
            'network_switch_upgrade_qualified_id',
            'member_upgrade_qualified_id'
        );
    }

    public function histories(): HasMany
    {
        return $this->hasMany(
            MemberHistory::class,
            'member_history_upgrade_qualified_id',
            'member_upgrade_qualified_id'
        );
    }

    protected function casts(): array
    {
        return [
            'member_upgrade_qualified_from_year_month' => 'integer',
            'member_upgrade_qualified_to_year_month' => 'integer',
            'member_upgrade_qualified_approved_datetime' => 'datetime',
            'member_upgrade_qualified_effective_date' => 'date',
            'member_upgrade_qualified_applied_datetime' => 'datetime',
            'member_upgrade_qualified_last_update_datetime' => 'datetime',
            'member_upgrade_qualified_created_datetime' => 'datetime',
        ];
    }
}
