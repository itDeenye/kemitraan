<?php

namespace App\Libraries;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Query builder for read-only list endpoints.
 *
 * Search, filter, and sort fields must be registered explicitly so request
 * values can never become arbitrary SQL identifiers.
 */
class DataTable
{
    /** @var array<string, string> */
    private array $searchable = [];

    /** @var array<string, string> */
    private array $selectedFields = [];

    private string $defaultSort = '';

    private bool $allowUnpaginated = false;

    /** @param array<int|string, mixed> $columns */
    private function __construct(private readonly Builder $query, array $columns)
    {
        $this->selectedFields = $this->selectedFields($columns);
    }

    /** @param array<int|string, mixed> $columns */
    public static function select(array $columns = ['*']): self
    {
        return new self(DB::query()->select($columns), $columns);
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->query->selectRaw($expression, $bindings);
        $this->selectedFields = array_merge(
            $this->selectedFields,
            $this->selectedFields([$expression])
        );

        return $this;
    }

    public function from(string $table, ?string $as = null): self
    {
        $this->query->from($table, $as);

        return $this;
    }

    public function fromSub(Builder $query, string $as): self
    {
        $this->query->fromSub($query, $as);

        return $this;
    }

    public function join(string $type, string $table, string $condition): self
    {
        $conditions = preg_split('/\s+and\s+/i', trim($condition)) ?: [];

        $this->query->join($table, function (JoinClause $join) use ($conditions): void {
            foreach ($conditions as $condition) {
                if (! preg_match(
                    '/^([A-Za-z_][A-Za-z0-9_.]*)\s*(=|!=|<>|>=|<=|>|<)\s*([A-Za-z_][A-Za-z0-9_.]*)$/',
                    trim($condition),
                    $matches
                )) {
                    throw new InvalidArgumentException("Invalid join condition: {$condition}");
                }

                $join->on($matches[1], $matches[2], $matches[3]);
            }
        }, null, null, $type);

        return $this;
    }

    public function leftJoin(string $table, string $condition): self
    {
        return $this->join('left', $table, $condition);
    }

    public function leftJoinSub(Builder $query, string $as, string $condition): self
    {
        $conditions = preg_split('/\s+and\s+/i', trim($condition)) ?: [];

        $this->query->leftJoinSub($query, $as, function (JoinClause $join) use ($conditions): void {
            foreach ($conditions as $condition) {
                if (! preg_match(
                    '/^([A-Za-z_][A-Za-z0-9_.]*)\s*(=|!=|<>|>=|<=|>|<)\s*([A-Za-z_][A-Za-z0-9_.]*)$/',
                    trim($condition),
                    $matches
                )) {
                    throw new InvalidArgumentException("Invalid join condition: {$condition}");
                }

                $join->on($matches[1], $matches[2], $matches[3]);
            }
        });

        return $this;
    }

    public function where(Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof Closure) {
            $this->query->where($column);
        } elseif (func_num_args() === 2) {
            $this->query->where($column, $operator);
        } else {
            $this->query->where($column, $operator, $value);
        }

        return $this;
    }

    /** @param array<int, mixed> $bindings */
    public function whereRaw(string $expression, array $bindings = []): self
    {
        $this->query->whereRaw($expression, $bindings);

        return $this;
    }

    /** @param array<int, mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    /** @param array{0: mixed, 1: mixed} $values */
    public function whereBetween(string $column, array $values): self
    {
        $this->query->whereBetween($column, $values);

        return $this;
    }

    /** @param array<int, mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        $this->query->whereNotIn($column, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    /** @param array<int, string>|string ...$groups */
    public function groupBy(array|string ...$groups): self
    {
        $this->query->groupBy(...$groups);

        return $this;
    }

    /** @return $this */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /** @return $this */
    public function orderByDesc(string $column): self
    {
        $this->query->orderByDesc($column);

        return $this;
    }

    /** @return $this */
    public function orderByRaw(string $expression): self
    {
        $this->query->orderByRaw($expression);

        return $this;
    }

    /**
     * @param  array<int|string, string>  $columns
     * @return $this
     */
    public function search(array $fields): self
    {
        $columns = [];

        foreach ($fields as $field) {
            if (isset($this->selectedFields[$field])) {
                $columns[] = $this->selectedFields[$field];
            }
        }

        $this->searchable = array_filter(
            $this->selectedFields,
            fn (string $column): bool => in_array($column, $columns, true)
        );

        return $this;
    }

    /** @return $this */
    public function defaultSort(string $sort): self
    {
        $this->defaultSort = $sort;

        return $this;
    }

    /** @return $this */
    public function allowUnpaginated(): self
    {
        $this->allowUnpaginated = true;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{results: Collection<int, object>, pagination?: array<string, int|bool|array<int, int>>}
     */
    public function get(array $params): array
    {
        $query = clone $this->query;

        $this->applyFilters($query, $params);
        $this->applySearch($query, $params);
        $this->applySort($query, $params);

        if ($this->paginationWasDisabled($params)) {
            return ['results' => $query->get()];
        }

        $limit = max(1, min(100, (int) ($params['limit'] ?? $params['per_page'] ?? 10)));
        $page = max(1, (int) ($params['page'] ?? 1));
        $paginator = $query->paginate(perPage: $limit, page: $page);

        return [
            'results' => new Collection($paginator->items()),
            'pagination' => $this->pagination(
                total: $paginator->total(),
                page: $paginator->currentPage(),
                limit: $paginator->perPage(),
                displayed: $paginator->count()
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applySearch(Builder $query, array $params): void
    {
        $search = trim((string) ($params['search'] ?? ''));

        if ($search === '' || $this->searchable === []) {
            return;
        }

        $columns = $this->searchable;
        $requestedFields = array_filter(array_map(
            'trim',
            explode(',', (string) ($params['field_search'] ?? ''))
        ));

        if ($requestedFields !== []) {
            $columns = array_intersect_key($this->searchable, array_flip($requestedFields));
        }

        if ($columns === []) {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($columns, $search): void {
            foreach (array_values($columns) as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $searchQuery->{$method}($column, 'like', "%{$search}%");
            }
        });
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applyFilters(Builder $query, array $params): void
    {
        $filters = is_array($params['filter'] ?? null) ? $params['filter'] : [];

        foreach ($this->selectedFields as $alias => $column) {
            $value = $filters[$alias] ?? $params[$alias] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $this->applyFilterValue($query, $column, $value);
        }
    }

    private function applyFilterValue(Builder $query, string $column, mixed $value): void
    {
        if (! is_array($value)) {
            $query->where($column, $value);

            return;
        }

        $operators = [
            'eq' => '=',
            'ne' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'like' => 'like',
        ];

        foreach ($operators as $key => $operator) {
            if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                $query->where($column, $operator, $value[$key]);
            }
        }

        foreach (['in' => false, 'not_in' => true] as $key => $not) {
            if (! array_key_exists($key, $value)) {
                continue;
            }

            $values = is_array($value[$key])
                ? $value[$key]
                : explode(',', (string) $value[$key]);
            $values = array_slice(array_values(array_filter($values, 'is_scalar')), 0, 100);

            if ($values !== []) {
                $query->whereIn($column, $values, 'and', $not);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function applySort(Builder $query, array $params): void
    {
        $requestedSort = array_key_exists('sort', $params)
            ? (string) $params['sort']
            : $this->defaultSort;

        if ($this->applySortExpression($query, $requestedSort)) {
            return;
        }

        if ($requestedSort !== $this->defaultSort) {
            $this->applySortExpression($query, $this->defaultSort);
        }
    }

    private function applySortExpression(Builder $query, string $expression): bool
    {
        $sorts = array_filter(array_map(
            'trim',
            explode(',', $expression)
        ));
        $applied = false;

        foreach ($sorts as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $alias = ltrim($sort, '-');

            if (! isset($this->selectedFields[$alias])) {
                continue;
            }

            $query->orderBy($this->selectedFields[$alias], $direction);
            $applied = true;
        }

        return $applied;
    }

    /** @param array<string, mixed> $params */
    private function paginationWasDisabled(array $params): bool
    {
        // `pagination` is the public query parameter. Keep accepting the
        // legacy `pagination_bool` name for existing consumers.
        $pagination = $params['pagination']
            ?? $params['pagination_bool']
            ?? request()->query('pagination')
            ?? request()->query('pagination_bool')
            ?? true;

        return filter_var(
            $pagination,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) === false;
    }

    /**
     * @param  array<int|string, mixed>  $columns
     * @return array<string, string>
     */
    private function selectedFields(array $columns): array
    {
        $fields = [];

        foreach ($columns as $alias => $expression) {
            if (! is_string($expression)) {
                continue;
            }

            if (is_string($alias)) {
                $column = $expression;
                $fieldAlias = $alias;
            } elseif (preg_match(
                '/^([A-Za-z_][A-Za-z0-9_.]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?$/i',
                trim($expression),
                $matches
            )) {
                $column = $matches[1];
                $fieldAlias = $matches[2] ?? $this->unqualified($column);
            } else {
                continue;
            }

            $fields[$fieldAlias] = $column;
            $fields[$this->unqualified($column)] = $column;
        }

        return $fields;
    }

    private function unqualified(string $column): string
    {
        $segments = explode('.', $column);

        return end($segments);
    }

    /** @return array<string, int|bool|array<int, int>> */
    private function pagination(int $total, int $page, int $limit, int $displayed): array
    {
        $totalPage = (int) ceil($total / $limit);
        $start = $displayed === 0 ? 0 : (($page - 1) * $limit) + 1;
        $end = $displayed === 0 ? 0 : $start + $displayed - 1;
        $from = max(1, $page - 2);
        $to = min($totalPage, $from + 4);
        $from = max(1, $to - 4);

        return [
            'total_data' => $total,
            'total_page' => $totalPage,
            'total_display' => $displayed,
            'first_page' => $totalPage > 1 && $from > 1,
            'last_page' => $totalPage > 1 && $to < $totalPage,
            'prev' => $page > 1 ? $page - 1 : 0,
            'current' => $page,
            'next' => $page < $totalPage ? $page + 1 : 0,
            'detail' => $totalPage > 1 ? range($from, $to) : [],
            'start' => $start,
            'end' => $end,
        ];
    }
}
