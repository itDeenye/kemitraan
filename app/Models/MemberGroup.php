<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel: member_group
 */
class MemberGroup extends Model
{
    protected $table = 'member_group';

    protected $primaryKey = 'member_group_id';

    public $timestamps = false;

    protected $fillable = ['member_group_name', 'member_group_description', 'member_group_is_active'];

    public function accounts()
    {
        return $this->hasMany(MemberAccount::class, 'member_account_member_group_id', 'member_group_id');
    }

    protected function casts(): array
    {
        return ['member_group_is_active' => 'boolean'];
    }
}
