<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model untuk tabel: site_administrator_menu
 */
class SiteAdministratorMenu extends Model
{
    protected $table = 'site_administrator_menu';

    protected $primaryKey = 'administrator_menu_id';

    public $timestamps = false;

    protected $fillable = [
        'administrator_menu_par_id',
        'administrator_menu_title',
        'administrator_menu_description',
        'administrator_menu_link',
        'administrator_menu_icon',
        'administrator_menu_class',
        'administrator_menu_order_by',
        'administrator_menu_is_active',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'administrator_menu_par_id', 'administrator_menu_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'administrator_menu_par_id', 'administrator_menu_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            SiteAdministratorGroup::class,
            'site_administrator_privilege',
            'administrator_privilege_administrator_menu_id',
            'administrator_privilege_administrator_group_id',
        );
    }

    protected function casts(): array
    {
        return [
            'administrator_menu_is_active' => 'boolean',
        ];
    }
}
