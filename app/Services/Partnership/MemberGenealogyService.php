<?php

namespace App\Services\Partnership;

use App\Libraries\DataTable;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberLevel;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class MemberGenealogyService
{
    public function totalDownlines(MemberAccount $account): int
    {
        $root = Member::query()
            ->whereKey($account->member_account_member_id)
            ->where('member_status', 1)
            ->firstOrFail();

        return count($this->descendantIds($root));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{context: array<string, mixed>, results: Collection<int, object>, pagination: array<string, mixed>}
     */
    public function list(MemberAccount $account, array $params): array
    {
        $root = Member::query()
            ->with('level')
            ->whereKey($account->member_account_member_id)
            ->where('member_status', 1)
            ->firstOrFail();

        if (trim((string) ($params['search'] ?? '')) !== '') {
            return [
                'context' => [
                    'root_member' => $this->memberContext($root),
                    'viewing_member' => $this->memberContext($root),
                    'breadcrumbs' => [$this->memberContext($root)],
                    'can_register' => in_array($root->level?->member_level_code, ['DST', 'AGT'], true),
                ],
                ...$this->networkDownlines($root, $params),
            ];
        }

        $currentId = (int) ($params['parent_id'] ?? $root->getKey());
        $current = $currentId === (int) $root->getKey()
            ? $root
            : Member::query()
                ->with('level')
                ->whereKey($currentId)
                ->where('member_status', '!=', 3)
                ->firstOrFail();
        $path = $this->networkPath($root, $current);
        $downlines = $this->directDownlines($current, $params);

        return [
            'context' => [
                'root_member' => $this->memberContext($root),
                'viewing_member' => $this->memberContext($current),
                'breadcrumbs' => $path->map($this->memberContext(...))->values()->all(),
                'can_register' => in_array($root->level?->member_level_code, ['DST', 'AGT'], true),
            ],
            ...$downlines,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    private function directDownlines(Member $parent, array $params): array
    {
        return $this->downlineQuery()
            ->where('member.member_parent_member_id', $parent->getKey())
            ->get($params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination: array<string, mixed>}
     */
    private function networkDownlines(Member $root, array $params): array
    {
        return $this->downlineQuery()
            ->whereIn('member.member_id', $this->descendantIds($root))
            ->get($params);
    }

    private function downlineQuery(): DataTable
    {
        $memberTable = (new Member)->getTable();
        $levelTable = (new MemberLevel)->getTable();

        return DataTable::select([
            "{$memberTable}.member_id as id",
            "{$memberTable}.member_code as code",
            "{$memberTable}.member_name as name",
            "{$memberTable}.member_image as image_url",
            "{$memberTable}.member_join_datetime as joined_at",
            "{$levelTable}.member_level_id as level_id",
            "{$levelTable}.member_level_code as level_code",
            "{$levelTable}.member_level_name as level_name",
        ])
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$memberTable} AS direct_downline
                    WHERE direct_downline.member_parent_member_id = {$memberTable}.member_id
                    AND direct_downline.member_status != ?) as direct_downline_count",
                [3],
            )
            ->from($memberTable)
            ->leftJoin(
                $levelTable,
                "{$levelTable}.member_level_id = {$memberTable}.member_member_level_id",
            )
            ->where("{$memberTable}.member_status", '!=', 3)
            ->search(['code', 'name'])
            ->defaultSort('name');
    }

    /** @return array<int, int> */
    private function descendantIds(Member $root): array
    {
        $visited = [(int) $root->getKey() => true];
        $frontier = [(int) $root->getKey()];
        $descendantIds = [];

        while ($frontier !== []) {
            $children = Member::query()
                ->whereIn('member_parent_member_id', $frontier)
                ->where('member_status', '!=', 3)
                ->pluck('member_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $frontier = [];

            foreach ($children as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $descendantIds[] = $childId;
                $frontier[] = $childId;
            }
        }

        return $descendantIds;
    }

    /** @return Collection<int, Member> */
    private function networkPath(Member $root, Member $current): Collection
    {
        $path = collect();
        $visited = [];
        $cursor = $current;

        while (count($visited) < 100) {
            $cursorId = (int) $cursor->getKey();

            if (isset($visited[$cursorId])) {
                break;
            }

            $visited[$cursorId] = true;
            $path->push($cursor);

            if ($cursorId === (int) $root->getKey()) {
                return $path->reverse()->values();
            }

            $parentId = (int) $cursor->member_parent_member_id;

            if ($parentId === 0) {
                break;
            }

            $cursor = Member::query()
                ->with('level')
                ->whereKey($parentId)
                ->where('member_status', '!=', 3)
                ->first();

            if (! $cursor) {
                break;
            }
        }

        throw (new ModelNotFoundException)->setModel(Member::class, [$current->getKey()]);
    }

    /** @return array<string, mixed> */
    private function memberContext(Member $member): array
    {
        return [
            'id' => (int) $member->getKey(),
            'code' => $member->member_code,
            'name' => $member->member_name,
            'image' => MediaUrl::publicUrl($member->member_image),
            'image_url' => MediaUrl::publicUrl($member->member_image),
            'level' => $member->level ? [
                'id' => (int) $member->level->getKey(),
                'code' => $member->level->member_level_code,
                'name' => $member->level->member_level_name,
            ] : null,
        ];
    }
}
