<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: site_administrator_privilege
 */
class SiteAdministratorPrivilege extends Model
{
    protected $table = 'site_administrator_privilege';

    protected $primaryKey = 'administrator_privilege_id';

    public $timestamps = false;

    protected $fillable = [
        'administrator_privilege_administrator_group_id',
        'administrator_privilege_administrator_menu_id',
    ];

    public function menu()
    {
        return $this->belongsTo(
            SiteAdministratorMenu::class,
            'administrator_privilege_administrator_menu_id',
            'administrator_menu_id'
        );
    }
}
