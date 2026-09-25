<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    protected $table = 'member';

    protected $primaryKey = 'member_id';

    public $timestamps = false;

    protected $fillable = [
        'member_code',
        'member_member_level_id',
        'member_parent_member_id',
        'member_stockist_id',
        'member_name',
        'member_email',
        'member_mobilephone',
        'member_gender',
        'member_birth_date',
        'member_identity_type',
        'member_identity_no',
        'member_identity_image',
        'member_identity_image_filename',
        'member_nib',
        'member_image',
        'member_image_filename',
        'member_instagram',
        'member_facebook',
        'member_tiktok',
        'member_join_datetime',
        'member_status',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'member_member_level_id', 'member_level_id');
    }

    protected function casts(): array
    {
        return [
            'member_status' => 'integer',
            'member_join_datetime' => 'datetime',
            'member_birth_date' => 'date',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(MemberAccount::class, 'member_account_member_id', 'member_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_parent_member_id', 'member_id');
    }

    public function downlines(): HasMany
    {
        return $this->hasMany(Member::class, 'member_parent_member_id', 'member_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(MemberAddress::class, 'member_address_member_id', 'member_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(MemberAddress::class, 'member_address_member_id', 'member_id')
            ->where('member_address_is_default', 1)
            ->orderBy('member_address_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(MemberBankAccount::class, 'member_bank_account_member_id', 'member_id');
    }

    public function activeBankAccounts(): HasMany
    {
        return $this->hasMany(MemberBankAccount::class, 'member_bank_account_member_id', 'member_id')
            ->where('member_bank_account_is_active', 1);
    }

    public function defaultBankAccount(): HasOne
    {
        return $this->hasOne(MemberBankAccount::class, 'member_bank_account_member_id', 'member_id')
            ->where('member_bank_account_is_default', 1)
            ->where('member_bank_account_is_active', 1)
            ->orderBy('member_bank_account_id');
    }

    protected function memberAddress(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_full);
    }

    protected function memberSubdistrictId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_subdistrict_id ?? 0);
    }

    protected function memberDistrictId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_district_id ?? 0);
    }

    protected function memberCityId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_city_id ?? 0);
    }

    protected function memberProvinceId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_province_id ?? 0);
    }

    protected function memberCountryId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->member_address_country_id ?? 0);
    }

    protected function province(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->province);
    }

    protected function city(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->city);
    }

    protected function district(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->district);
    }

    protected function subdistrict(): Attribute
    {
        return Attribute::get(fn () => $this->defaultAddress?->subdistrict);
    }

    protected function memberBankId(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->member_bank_account_bank_id ?? 0);
    }

    protected function memberBankName(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->bank?->bank_name);
    }

    protected function memberBankAccountName(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->member_bank_account_name);
    }

    protected function memberBankAccountNo(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->member_bank_account_number);
    }

    protected function memberBankCity(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->member_bank_account_city);
    }

    protected function memberBankBranch(): Attribute
    {
        return Attribute::get(fn () => $this->defaultBankAccount?->member_bank_account_branch);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MemberHistory::class, 'member_history_member_id', 'member_id');
    }

    public function stockist(): HasOne
    {
        return $this->hasOne(Stockist::class, 'stockist_member_id', 'member_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(MemberStock::class, 'member_stock_member_id', 'member_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Trx::class, 'trx_buyer_id', 'member_id')
            ->whereIn('trx_buyer_type', ['distributor', 'agent', 'reseller']);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Trx::class, 'trx_seller_id', 'member_id')
            ->whereIn('trx_seller_type', ['distributor', 'agent', 'reseller']);
    }

    public function goodsReceived(): HasMany
    {
        return $this->hasMany(GoodsReceive::class, 'goods_receive_buyer_id', 'member_id');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(MemberAchievement::class, 'member_achievement_member_id', 'member_id');
    }

    public function annualPointReports(): HasMany
    {
        return $this->hasMany(RewardPointAnnual::class, 'reward_point_annual_member_id', 'member_id');
    }

    public function upgradeQualifications(): HasMany
    {
        return $this->hasMany(
            MemberUpgradeQualified::class,
            'member_upgrade_qualified_member_id',
            'member_id'
        );
    }

    public function networkTransfers(): HasMany
    {
        return $this->hasMany(
            MemberNetworkSwitch::class,
            'network_switch_member_id',
            'member_id'
        );
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class, 'return_member_id', 'member_id');
    }
}
