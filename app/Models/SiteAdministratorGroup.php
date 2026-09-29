<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: site_administrator_group
 */
class SiteAdministratorGroup extends Model
{
    protected $table = 'site_administrator_group';

    protected $primaryKey = 'administrator_group_id';

    public $timestamps = false;

    protected $fillable = [
        'administrator_group_title',
        'administrator_group_type',
        'administrator_group_is_active',
    ];

    public function administrators(): HasMany
    {
        return $this->hasMany(
            SiteAdministrator::class,
            'administrator_administrator_group_id',
            'administrator_group_id',
        );
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(
            SiteAdministratorMenu::class,
            'site_administrator_privilege',
            'administrator_privilege_administrator_group_id',
            'administrator_privilege_administrator_menu_id',
        );
    }

    protected function casts(): array
    {
        return [
            'administrator_group_is_active' => 'boolean',
        ];
    }
}
