<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class NormalizeDataTableQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        $filter = $request->query('filter');

        if (is_array($filter)) {
            $request->query->set('filter', $this->normalizeFilter($filter));
        }

        foreach (['field_search', 'sort'] as $parameter) {
            $value = $request->query($parameter);

            if (is_string($value)) {
                $request->query->set($parameter, $this->normalizeFieldList($value));
            }
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $filter
     * @return array<array-key, mixed>
     */
    private function normalizeFilter(array $filter): array
    {
        $normalized = [];

        foreach ($filter as $field => $value) {
            if (! is_string($field)) {
                $normalized[$field] = $value;

                continue;
            }

            $canonicalField = $this->normalizeField($field);

            if ($canonicalField !== $field && array_key_exists($canonicalField, $filter)) {
                continue;
            }

            $normalized[$canonicalField] = $value;
        }

        return $normalized;
    }

    private function normalizeFieldList(string $fields): string
    {
        return collect(explode(',', $fields))
            ->map(function (string $field): string {
                $field = trim($field);
                $descending = Str::startsWith($field, '-');
                $normalized = $this->normalizeField(ltrim($field, '-'));

                return $descending ? "-{$normalized}" : $normalized;
            })
            ->implode(',');
    }

    private function normalizeField(string $field): string
    {
        return Str::replace('.', '_', $field);
    }
}
