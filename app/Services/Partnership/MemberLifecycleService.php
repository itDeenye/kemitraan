<?php

namespace App\Services\Partnership;

use App\Exceptions\ProcessException;
use App\Mail\MemberPasswordResetByAdminMail;
use App\Models\Member;
use App\Models\MemberHistory;
use App\Models\MemberNetworkSwitch;
use App\Models\ReturnModel;
use App\Models\RewardPointMonthly;
use App\Models\SiteAdministrator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class MemberLifecycleService
{
    public function __construct(
        private readonly MemberPasswordService $passwordService,
        private readonly MemberTransactionCancellationService $transactionCancellationService,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function deactivationOptions(Member $member, array $params): array
    {
        $member = Member::query()
            ->with('level')
            ->whereKey($member->getKey())
            ->where('member_status', 1)
            ->firstOrFail();
        $downlines = $this->directDownlines($member);
        $outstandingRewards = $this->outstandingRewardCount((int) $member->getKey());
        $transactionSummary = $this->transactionCancellationService->summary($member);
        $activeReturns = $this->activeReturnCount((int) $member->getKey());
        $hasPendingTransfer = $this->hasPendingTransfer((int) $member->getKey())
            || $downlines->contains(fn (Member $downline): bool => $this->hasPendingTransfer((int) $downline->getKey()));
        $requiresReplacement = $downlines->isNotEmpty() || $outstandingRewards > 0;

        return [
            'member' => $this->memberOption($member),
            'summary' => [
                'direct_downline_count' => $downlines->count(),
                'outstanding_reward_count' => $outstandingRewards,
                'active_transaction_count' => $transactionSummary['active'],
                'cancellable_transaction_count' => $transactionSummary['cancellable'],
                'blocking_transaction_count' => $transactionSummary['blocking'],
                'active_return_count' => $activeReturns,
                'has_pending_network_change' => $hasPendingTransfer,
            ],
            'requires_replacement_sponsor' => $requiresReplacement,
            'requires_transaction_cancellation' => $transactionSummary['cancellable'] > 0,
            'can_deactivate' => $transactionSummary['blocking'] === 0
                && $activeReturns === 0
                && ! $hasPendingTransfer,
            'results' => $requiresReplacement
                ? $this->replacementSponsors($member, $downlines, $params)
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{member: Member, moved_downlines: int, transferred_reward_liabilities: int, cancelled_transactions: int, released_stock_orders: int}
     */
    public function deactivate(
        Member $member,
        SiteAdministrator $administrator,
        array $data,
    ): array {
        return DB::transaction(function () use ($member, $administrator, $data): array {
            $lockedMember = Member::query()
                ->with('level')
                ->whereKey($member->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedMember->member_status !== 1) {
                throw new ProcessException('Mitra sudah tidak aktif dan tidak dapat dinonaktifkan kembali.');
            }

            $downlines = $this->directDownlines($lockedMember, true);
            $outstandingRewards = $this->outstandingRewardCount((int) $lockedMember->getKey());
            $requiresReplacement = $downlines->isNotEmpty() || $outstandingRewards > 0;
            $replacement = null;

            $this->ensureNoPendingTransfer($lockedMember, $downlines);
            $this->ensureCanDeactivate($lockedMember, $data);
            $cancelledTransactions = $this->transactionCancellationService
                ->cancelCancellableTransactions($lockedMember);

            if ($requiresReplacement) {
                $replacementId = (int) ($data['replacement_sponsor_id'] ?? 0);
                if ($replacementId <= 0) {
                    throw new ProcessException('Sponsor pengganti wajib dipilih karena masih ada jaringan atau kewajiban reward.');
                }

                $replacement = Member::query()
                    ->with('level')
                    ->whereKey($replacementId)
                    ->where('member_status', 1)
                    ->lockForUpdate()
                    ->first();

                $this->ensureReplacementIsValid($lockedMember, $replacement, $downlines);
            }

            $note = trim((string) ($data['note'] ?? ''));
            $note = $note !== '' ? $note : 'Dinonaktifkan oleh administrator dan jaringan dipindahkan langsung.';
            $movedDownlines = 0;

            if ($replacement) {
                foreach ($downlines as $downline) {
                    $downline->update(['member_parent_member_id' => $replacement->getKey()]);
                    $this->recordHistory(
                        member: $downline,
                        administrator: $administrator,
                        action: 'reparent',
                        upline: $replacement,
                        downlineId: (int) $downline->getKey(),
                        reason: "Pemindahan jaringan karena {$lockedMember->member_code} dinonaktifkan. {$note}",
                    );
                    $movedDownlines++;
                }
            }

            $transferredRewards = $replacement
                ? RewardPointMonthly::query()
                    ->where('reward_point_monthly_upline_id', $lockedMember->getKey())
                    ->where('reward_point_monthly_bonus_value', '>', 0)
                    ->where('reward_point_monthly_is_processed', 0)
                    ->update([
                        'reward_point_monthly_upline_id' => $replacement->getKey(),
                        'reward_point_monthly_upline_level_id' => $replacement->member_member_level_id,
                    ])
                : 0;

            $lockedMember->update(['member_status' => 0]);
            $this->recordHistory(
                member: $lockedMember,
                administrator: $administrator,
                action: 'deactivate',
                upline: $replacement,
                downlineId: 0,
                reason: $note,
            );

            return [
                'member' => $lockedMember->refresh()->load(['level', 'parent']),
                'moved_downlines' => $movedDownlines,
                'transferred_reward_liabilities' => $transferredRewards,
                'cancelled_transactions' => $cancelledTransactions['cancelled_transactions'],
                'released_stock_orders' => $cancelledTransactions['released_stock_orders'],
            ];
        }, 3);
    }

    public function resetPassword(Member $member): Member
    {
        $result = DB::transaction(function () use ($member): array {
            $lockedMember = Member::query()
                ->with(['accounts', 'level'])
                ->whereKey($member->getKey())
                ->where('member_status', '!=', 3)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMember->member_birth_date) {
                throw new ProcessException('Tanggal lahir mitra belum tersedia sehingga password default tidak dapat dibuat.');
            }
            if (blank($lockedMember->member_email)) {
                throw new ProcessException('Email mitra belum tersedia sehingga informasi reset password tidak dapat dikirim.');
            }
            if ($lockedMember->accounts->isEmpty()) {
                throw new ProcessException('Akun login mitra tidak ditemukan.');
            }

            $password = $this->passwordService->birthDatePassword($lockedMember->member_birth_date);
            DB::table('member_account')
                ->where('member_account_member_id', $lockedMember->getKey())
                ->update([
                    'member_account_password' => Hash::make($password),
                    'member_account_failed_login_attempts' => 0,
                    'member_account_last_failed_login_datetime' => null,
                    'member_account_locked_until' => null,
                ]);

            return [
                'member' => $lockedMember,
                'username' => (string) $lockedMember->accounts->first()->member_account_username,
                'password' => $password,
            ];
        });

        Mail::to($result['member']->member_email)->queue(new MemberPasswordResetByAdminMail(
            memberName: $result['member']->member_name,
            memberCode: $result['member']->member_code,
            username: $result['username'],
            password: $result['password'],
        ));

        return $result['member']->refresh()->load(['accounts.group', 'parent', 'level']);
    }

    /** @return Collection<int, Member> */
    private function directDownlines(Member $member, bool $lock = false): Collection
    {
        $query = Member::query()
            ->with('level')
            ->where('member_parent_member_id', $member->getKey())
            ->where('member_status', '!=', 3)
            ->orderBy('member_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Member>  $downlines
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function replacementSponsors(Member $member, Collection $downlines, array $params): array
    {
        $allowedCodes = $this->replacementLevelCodes($member, $downlines);
        $excludedIds = [$member->getKey(), ...$this->descendantIds($member)];
        $search = trim((string) ($params['search'] ?? ''));

        return Member::query()
            ->with('level')
            ->where('member_status', 1)
            ->whereNotIn('member_id', $excludedIds)
            ->whereHas('level', fn (Builder $query): Builder => $query
                ->whereIn('member_level_code', $allowedCodes)
                ->where('member_level_is_active', 1))
            ->whereNotIn('member_id', MemberNetworkSwitch::query()
                ->select('network_switch_member_id')
                ->whereIn('network_switch_status', ['scheduled', 'approved'])
                ->whereNull('network_switch_applied_datetime'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('member_code', 'like', "%{$search}%")
                        ->orWhere('member_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('member_name')
            ->orderBy('member_id')
            ->limit((int) ($params['limit'] ?? 50))
            ->get()
            ->map(fn (Member $candidate): array => $this->memberOption($candidate))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Member>  $downlines
     * @return list<string>
     */
    private function replacementLevelCodes(Member $member, Collection $downlines): array
    {
        if ($downlines->isEmpty()) {
            return [$member->level?->member_level_code ?? ''];
        }

        $allowed = ['DST', 'AGT'];
        foreach ($downlines as $downline) {
            $forDownline = match ($downline->level?->member_level_code) {
                'DST', 'AGT' => ['DST'],
                'RSL' => ['DST', 'AGT'],
                default => [],
            };
            $allowed = array_values(array_intersect($allowed, $forDownline));
        }

        return $allowed;
    }

    /** @param Collection<int, Member> $downlines */
    private function ensureReplacementIsValid(Member $member, ?Member $replacement, Collection $downlines): void
    {
        if (! $replacement || ! $replacement->level) {
            throw new ProcessException('Sponsor pengganti aktif tidak ditemukan.');
        }
        if ((int) $replacement->getKey() === (int) $member->getKey()
            || in_array((int) $replacement->getKey(), $this->descendantIds($member), true)) {
            throw new ProcessException('Sponsor pengganti tidak boleh berasal dari jaringan mitra yang dinonaktifkan.');
        }
        if ($this->hasPendingTransfer((int) $replacement->getKey())) {
            throw new ProcessException('Sponsor pengganti masih memiliki perubahan jaringan yang belum diterapkan.');
        }

        if (! in_array($replacement->level->member_level_code, $this->replacementLevelCodes($member, $downlines), true)) {
            throw new ProcessException('Tingkat sponsor pengganti tidak dapat menaungi seluruh jaringan yang dipindahkan.');
        }
    }

    /** @param Collection<int, Member> $downlines */
    private function ensureNoPendingTransfer(Member $member, Collection $downlines): void
    {
        if ($this->hasPendingTransfer((int) $member->getKey())
            || $downlines->contains(fn (Member $downline): bool => $this->hasPendingTransfer((int) $downline->getKey()))) {
            throw new ProcessException('Mitra atau jaringan langsungnya masih memiliki perubahan jaringan yang belum diterapkan.');
        }
    }

    /** @param array<string, mixed> $data */
    private function ensureCanDeactivate(Member $member, array $data): void
    {
        if ($this->activeReturnCount((int) $member->getKey()) > 0) {
            throw new ProcessException('Mitra masih memiliki proses retur berjalan. Selesaikan retur sebelum menonaktifkan mitra.');
        }
        $transactionSummary = $this->transactionCancellationService->summary($member);
        if ($transactionSummary['blocking'] > 0) {
            throw new ProcessException(
                'Mitra masih memiliki transaksi dalam tahap pengiriman atau penerimaan yang tidak dapat dibatalkan otomatis.',
            );
        }
        if ($transactionSummary['cancellable'] > 0
            && ! (bool) ($data['cancel_active_transactions'] ?? false)) {
            throw new ProcessException(
                'Konfirmasi pembatalan transaksi aktif wajib diberikan agar stok dapat dikembalikan.',
            );
        }
    }

    private function activeReturnCount(int $memberId): int
    {
        return ReturnModel::query()
            ->where('return_member_id', $memberId)
            ->whereNotIn('return_status', ['completed', 'rejected'])
            ->count();
    }

    private function outstandingRewardCount(int $memberId): int
    {
        return RewardPointMonthly::query()
            ->where('reward_point_monthly_upline_id', $memberId)
            ->where('reward_point_monthly_bonus_value', '>', 0)
            ->where('reward_point_monthly_is_processed', 0)
            ->count();
    }

    private function hasPendingTransfer(int $memberId): bool
    {
        return MemberNetworkSwitch::query()
            ->where('network_switch_member_id', $memberId)
            ->whereIn('network_switch_status', ['scheduled', 'approved'])
            ->whereNull('network_switch_applied_datetime')
            ->exists();
    }

    /** @return list<int> */
    private function descendantIds(Member $member): array
    {
        $descendants = [];
        $parents = [(int) $member->getKey()];

        for ($depth = 0; $depth < 100 && $parents !== []; $depth++) {
            $children = Member::query()
                ->whereIn('member_parent_member_id', $parents)
                ->pluck('member_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $children = array_values(array_diff($children, $descendants));
            if ($children === []) {
                break;
            }
            $descendants = [...$descendants, ...$children];
            $parents = $children;
        }

        return $descendants;
    }

    /** @return array<string, mixed> */
    private function memberOption(Member $member): array
    {
        return [
            'id' => (int) $member->getKey(),
            'code' => $member->member_code,
            'name' => $member->member_name,
            'label' => "{$member->member_code} - {$member->member_name}",
            'level' => [
                'id' => (int) $member->member_member_level_id,
                'code' => $member->level?->member_level_code,
                'name' => $member->level?->member_level_name,
            ],
        ];
    }

    private function recordHistory(
        Member $member,
        SiteAdministrator $administrator,
        string $action,
        ?Member $upline,
        int $downlineId,
        string $reason,
    ): void {
        MemberHistory::query()->create([
            'member_history_member_id' => $member->getKey(),
            'member_history_upgrade_qualified_id' => 0,
            'member_history_network_transfer_id' => 0,
            'member_history_action' => $action,
            'member_history_from_level_id' => $member->member_member_level_id,
            'member_history_to_level_id' => $member->member_member_level_id,
            'member_history_upline_member_id' => $upline?->getKey() ?? 0,
            'member_history_upline_member_level_id' => $upline?->member_member_level_id ?? 0,
            'member_history_downline_member_id' => $downlineId,
            'member_history_reason' => $reason,
            'member_history_approved_by' => $administrator->getKey(),
            'member_history_datetime' => now(),
        ]);
    }
}
