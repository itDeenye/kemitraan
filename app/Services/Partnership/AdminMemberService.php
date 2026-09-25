<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAddress;
use App\Models\MemberBankAccount;
use App\Support\MediaUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminMemberService
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, mixed>}
     */
    public function list(array $params): array
    {
        return DataTable::select([
            'member.member_id as id',
            'member.member_code as code',
            'member.member_name as name',
            'member.member_email as email',
            'member.member_mobilephone as mobile_phone',
            'member_level.member_level_id as level_id',
            'member_level.member_level_code as level_code',
            'member_level.member_level_name as level',
            'member_level.member_level_min_order as level_min_order',
            'member.member_status as status',
            'member.member_join_datetime as joined_at',
            'member.member_image as image',
        ])
            ->from('member')
            ->leftJoin('member_level', 'member_level.member_level_id = member.member_member_level_id')
            ->where('member.member_status', '!=', 3)
            ->search(['code', 'name', 'email', 'mobile_phone'])
            ->defaultSort('-id')
            ->get($params);
    }

    public function detail(Member $member): Member
    {
        $member = Member::query()
            ->with([
                'accounts.group',
                'parent',
                'level',
                'stockist',
                'addresses' => fn ($query) => $query
                    ->with(['province', 'city', 'district', 'subdistrict', 'country'])
                    ->orderByDesc('member_address_is_default')
                    ->orderBy('member_address_id'),
                'bankAccounts' => fn ($query) => $query
                    ->with('bank')
                    ->orderByDesc('member_bank_account_is_default')
                    ->orderByDesc('member_bank_account_is_active')
                    ->orderBy('member_bank_account_id'),
            ])
            ->where('member_status', '!=', 3)
            ->findOrFail($member->getKey());

        // The member detail is the single entry point for the complete
        // network tree used by the admin screen.
        $member->setAttribute(
            'network',
            $this->detailNetwork($member)
        );

        return $member;
    }

    /** @param array<string, mixed> $data */
    public function update(Member $member, array $data): Member
    {
        $this->detail($member);

        if ((int) $member->member_status === 1 && (int) $data['status'] === 0) {
            throw new ProcessException('Gunakan aksi Nonaktifkan agar jaringan dan kewajiban reward dipindahkan dengan aman.');
        }

        DB::transaction(function () use ($member, $data): void {
            $member->update([
                'member_name' => $data['name'],
                'member_email' => $data['email'] ?? '',
                'member_mobilephone' => $data['mobile_phone'],
                'member_gender' => $data['gender'],
                'member_birth_date' => $data['birth_date'] ?? null,
                'member_image' => $data['image_url'] ?? null,
                'member_instagram' => $data['instagram'] ?? null,
                'member_facebook' => $data['facebook'] ?? null,
                'member_tiktok' => $data['tiktok'] ?? null,
                'member_status' => $data['status'],
            ]);

            $this->syncAddresses($member, $data['addresses']);
            $this->syncBankAccounts($member, $data['bank_accounts']);
        });

        return $this->detail($member->refresh());
    }

    /** @param array<int, array<string, mixed>> $addresses */
    private function syncAddresses(Member $member, array $addresses): void
    {
        $existing = MemberAddress::query()
            ->where('member_address_member_id', $member->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy('member_address_id');
        $keptIds = [];
        $defaultId = null;
        $firstId = null;

        foreach ($addresses as $data) {
            $address = filled($data['id'] ?? null)
                ? $existing->get((int) $data['id'])
                : new MemberAddress;

            $address->fill([
                'member_address_member_id' => $member->getKey(),
                'member_address_label' => $data['label'] ?? '',
                'member_address_recipient' => $data['recipient'],
                'member_address_phone' => $data['phone'],
                'member_address_full' => $data['full_address'],
                'member_address_subdistrict_id' => $data['subdistrict_id'],
                'member_address_district_id' => $data['district_id'],
                'member_address_city_id' => $data['city_id'],
                'member_address_province_id' => $data['province_id'],
                'member_address_country_id' => $data['country_id'] ?? 1,
                'member_address_is_default' => 0,
            ])->save();

            $addressId = (int) $address->getKey();
            $keptIds[] = $addressId;
            $firstId ??= $addressId;

            if ($defaultId === null && ($data['is_default'] ?? false)) {
                $defaultId = $addressId;
            }
        }

        $this->deleteMissingAddresses((int) $member->getKey(), $keptIds);
        $defaultId ??= $firstId;

        if ($defaultId !== null) {
            MemberAddress::query()->whereKey($defaultId)->update(['member_address_is_default' => 1]);
        }
    }

    /** @param array<int, array<string, mixed>> $bankAccounts */
    private function syncBankAccounts(Member $member, array $bankAccounts): void
    {
        $existing = MemberBankAccount::query()
            ->where('member_bank_account_member_id', $member->getKey())
            ->lockForUpdate()
            ->get()
            ->keyBy('member_bank_account_id');
        $keptIds = [];
        $defaultId = null;
        $firstActiveId = null;

        foreach ($bankAccounts as $data) {
            $account = filled($data['id'] ?? null)
                ? $existing->get((int) $data['id'])
                : new MemberBankAccount;
            $isActive = (bool) $data['is_active'];

            $account->fill([
                'member_bank_account_member_id' => $member->getKey(),
                'member_bank_account_bank_id' => $data['bank_id'],
                'member_bank_account_name' => $data['account_name'],
                'member_bank_account_number' => $data['account_number'],
                'member_bank_account_city' => $data['city'] ?? null,
                'member_bank_account_branch' => $data['branch'] ?? null,
                'member_bank_account_is_active' => $isActive,
                'member_bank_account_is_default' => 0,
            ])->save();

            $accountId = (int) $account->getKey();
            $keptIds[] = $accountId;
            $firstActiveId ??= $isActive ? $accountId : null;

            if ($defaultId === null && $isActive && ($data['is_default'] ?? false)) {
                $defaultId = $accountId;
            }
        }

        $this->deleteMissingBankAccounts((int) $member->getKey(), $keptIds);
        $defaultId ??= $firstActiveId;

        if ($defaultId !== null) {
            MemberBankAccount::query()->whereKey($defaultId)->update(['member_bank_account_is_default' => 1]);
        }
    }

    /** @param array<int, int> $keptIds */
    private function deleteMissingAddresses(int $memberId, array $keptIds): void
    {
        $query = MemberAddress::query()->where('member_address_member_id', $memberId);
        if ($keptIds !== []) {
            $query->whereNotIn('member_address_id', $keptIds);
        }
        $query->delete();
    }

    /** @param array<int, int> $keptIds */
    private function deleteMissingBankAccounts(int $memberId, array $keptIds): void
    {
        $query = MemberBankAccount::query()->where('member_bank_account_member_id', $memberId);
        if ($keptIds !== []) {
            $query->whereNotIn('member_bank_account_id', $keptIds);
        }
        $query->delete();
    }

    /** @return array<int, array<string, mixed>> */
    public function genealogy(?int $memberId, int $depth): array
    {
        if ($memberId !== null) {
            $selected = Member::query()
                ->with('level')
                ->whereKey($memberId)
                ->where('member_status', '!=', 3)
                ->firstOrFail();
            $ancestor = $this->topAncestor($selected);
            $tree = $this->genealogyNodes(collect([$ancestor]), max($depth, 5))[0] ?? null;

            if ($tree === null) {
                return [];
            }

            return $ancestor->getKey() === $selected->getKey()
                ? [$tree]
                : array_values(array_filter([
                    $this->pruneNetworkToMember($tree, (int) $selected->getKey()),
                ]));
        }

        $roots = Member::query()
            ->with('level')
            ->where('member_status', '!=', 3)
            ->where('member_parent_member_id', 0)
            ->orderBy('member_name')
            ->get();

        return $this->genealogyNodes($roots, $depth);
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array<int, array<string, mixed>>
     */
    private function genealogyNodes(Collection $members, int $depth): array
    {
        $downlines = collect();

        if ($depth > 1 && $members->isNotEmpty()) {
            $downlines = Member::query()
                ->with('level')
                ->where('member_status', '!=', 3)
                ->whereIn('member_parent_member_id', $members->pluck('member_id')->all())
                ->orderBy('member_name')
                ->get()
                ->groupBy('member_parent_member_id');
        }

        return $members->map(function (Member $member) use ($depth, $downlines): array {
            $children = $downlines->get($member->getKey(), collect());

            return [
                'id' => $member->member_id,
                'code' => $member->member_code,
                'name' => $member->member_name,
                'image' => MediaUrl::publicUrl($member->member_image),
                'image_url' => MediaUrl::publicUrl($member->member_image),
                'level' => $member->level ? [
                    'id' => $member->level->member_level_id,
                    'code' => $member->level->member_level_code,
                    'name' => $member->level->member_level_name,
                ] : null,
                'status' => (int) $member->member_status,
                'parent_id' => (int) $member->member_parent_member_id ?: null,
                'total_direct_downlines' => $children->count(),
                'downlines' => $depth > 1
                    ? $this->genealogyNodes($children, $depth - 1)
                    : [],
            ];
        })->all();
    }

    /** @return array<string, mixed>|null */
    private function detailNetwork(Member $member): ?array
    {
        $ancestor = $this->topAncestor($member);

        $tree = $this->genealogyNodes(collect([$ancestor]), 5)[0] ?? null;

        return $tree === null || $ancestor->getKey() === $member->getKey()
            ? $tree
            : $this->pruneNetworkToMember($tree, (int) $member->getKey());
    }

    private function topAncestor(Member $member): Member
    {
        $ancestor = $member;
        $visited = [];

        while ((int) $ancestor->member_parent_member_id !== 0
            && ! isset($visited[$ancestor->getKey()])) {
            $visited[$ancestor->getKey()] = true;
            $parent = Member::query()
                ->with('level')
                ->whereKey($ancestor->member_parent_member_id)
                ->where('member_status', '!=', 3)
                ->first();

            if (! $parent) {
                break;
            }

            $ancestor = $parent;
        }

        return $ancestor;
    }

    /** @param array<string, mixed> $node */
    private function pruneNetworkToMember(array $node, int $memberId): ?array
    {
        if ((int) $node['id'] === $memberId) {
            return $node;
        }

        foreach ($node['downlines'] as $child) {
            $branch = $this->pruneNetworkToMember($child, $memberId);
            if ($branch !== null) {
                $node['downlines'] = [$branch];
                $node['total_direct_downlines'] = 1;

                return $node;
            }
        }

        return null;
    }
}
