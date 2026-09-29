<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRegistration extends Model
{
    protected $table = 'member_registration';

    protected $primaryKey = 'member_registration_id';

    public $timestamps = false;

    protected $fillable = [
        'member_registration_member_level_id',
        'member_registration_upline_member_id',
        'member_registration_member_id',
        'member_registration_name',
        'member_registration_email',
        'member_registration_mobilephone',
        'member_registration_gender',
        'member_registration_birth_date',
        'member_registration_address',
        'member_registration_subdistrict_id',
        'member_registration_district_id',
        'member_registration_city_id',
        'member_registration_province_id',
        'member_registration_country_id',
        'member_registration_bank_id',
        'member_registration_bank_name',
        'member_registration_bank_account_name',
        'member_registration_bank_account_no',
        'member_registration_bank_city',
        'member_registration_bank_branch',
        'member_registration_identity_type',
        'member_registration_identity_no',
        'member_registration_identity_image',
        'member_registration_identity_image_filename',
        'member_registration_nib',
        'member_registration_username',
        'member_registration_password',
        'member_registration_status',
        'member_registration_status_administrator_id',
        'member_registration_status_datetime',
        'member_registration_note',
        'member_registration_datetime',
    ];

    protected $hidden = ['member_registration_password'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_registration_member_level_id', 'member_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_registration_upline_member_id', 'member_id');
    }

    public function registeredMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_registration_member_id', 'member_id');
    }

    public function statusAdministrator(): BelongsTo
    {
        return $this->belongsTo(
            SiteAdministrator::class,
            'member_registration_status_administrator_id',
            'administrator_id'
        );
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(RefProvince::class, 'member_registration_province_id', 'province_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(RefCity::class, 'member_registration_city_id', 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(RefDistrict::class, 'member_registration_district_id', 'district_id');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(
            RefSubdistrict::class,
            'member_registration_subdistrict_id',
            'subdistrict_id'
        );
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(RefCountry::class, 'member_registration_country_id', 'country_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(RefBank::class, 'member_registration_bank_id', 'bank_id');
    }

    protected function casts(): array
    {
        return [
            'member_registration_birth_date' => 'date',
            'member_registration_status_datetime' => 'datetime',
            'member_registration_datetime' => 'datetime',
        ];
    }
}
