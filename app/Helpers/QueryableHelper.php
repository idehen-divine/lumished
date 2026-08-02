<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use L0n3ly\LaravelDynamicHelpers\Helper;

class QueryableHelper extends Helper
{
    /**
     * Build a standardized pagination metadata array.
     *
     * @param  LengthAwarePaginator  $paginator  The paginator instance
     * @return array The pagination metadata (from, to, total, per_page, etc.)
     */
    public function getPagination(LengthAwarePaginator $paginator): array
    {
        return [
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'first_page' => 1,
            'previous_page' => $paginator->currentPage() > 1 ? $paginator->currentPage() - 1 : null,
            'current_page' => $paginator->currentPage(),
            'next_page' => $paginator->currentPage() < $paginator->lastPage() ? $paginator->currentPage() + 1 : null,
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * Fetch paginated results with optional filtering, sorting, and search.
     *
     * Applies status filters, featured filters, exact column filters,
     * fuzzy prefix search across searchable columns, custom query callbacks,
     * and sorted pagination based on request input.
     *
     * @param  Model|EloquentBuilder|QueryBuilder  $model  The starting model or query builder instance
     * @param  array  $options  Configuration options (status_column, status_map, featured_column, exact_filters, searchable, sortable, sort_map, etc.)
     * @param  callable|null  $extraQuery  Optional callback for additional query constraints
     * @return LengthAwarePaginator The paginated result set
     *
     * @throws ValidationException
     */
    public function fetchWithFilters(
        Model|EloquentBuilder|QueryBuilder $model,
        array $options = [],
        ?callable $extraQuery = null,
    ): LengthAwarePaginator {
        $this->validate($options);

        $query = $model instanceof Model ? $model->newQuery() : $model;

        if (isset($options['status_column'])) {
            $statusColumn = (string) $options['status_column'];
            if (request()->filled('status') && $this->hasColumn($query, $statusColumn)) {
                $statusKey = request('status') === 'active' ? 'active' : 'inactive';
                $statusValue = $options['status_map'][$statusKey] ?? (request('status') === 'active');

                $query->where($statusColumn, $statusValue);
            }
        }

        if (isset($options['featured_column'])) {
            $featuredColumn = (string) $options['featured_column'];
            if (request()->filled('featured') && $this->hasColumn($query, $featuredColumn)) {
                $query->where($featuredColumn, request('featured') === 'featured');
            }
        }

        foreach ($options['exact_filters'] ?? [] as $requestKey => $column) {
            $key = is_int($requestKey) ? $column : $requestKey;
            if (request()->filled($key) && $this->hasColumn($query, (string) $column)) {
                $query->where((string) $column, request()->input($key));
            }
        }

        $searchable = $options['searchable'] ?? [];
        if (request()->filled('search') && $searchable !== []) {
            $search = (string) request()->input('search');

            $allResults = $query->get();
            $filtered = $allResults->filter(function (Model $record) use ($search, $searchable): bool {
                $fields = array_map(fn (string $column): string => (string) ($record->{$column} ?? ''), $searchable);

                return stringHelper()->matchesWithFuzzyPrefix($search, 0.75, ...$fields);
            });

            $query = $model instanceof Model ? $model->newQuery() : clone $model;

            if ($filtered->isNotEmpty()) {
                $query->whereIn('id', $filtered->pluck('id')->toArray());
            } else {
                $query->whereRaw('1=0');
            }
        }

        if ($extraQuery) {
            $extraQuery($query);
        }

        $sortBy = request()->input('sort_by');
        $sortOrder = request()->input('sort_order', (string) ($options['default_sort_order'] ?? 'asc'));
        $sortMap = $options['sort_map'] ?? [];

        if (is_string($sortBy) && $sortBy !== '') {
            $sortColumn = (string) ($sortMap[$sortBy] ?? $sortBy);
            if ($this->hasColumn($query, $sortColumn)) {
                $query->orderBy($sortColumn, $sortOrder);
            }
        } elseif (! empty($options['default_sort_by'])) {
            $defaultSortBy = (string) $options['default_sort_by'];
            if ($this->hasColumn($query, $defaultSortBy)) {
                $query->orderBy($defaultSortBy, $sortOrder);
            }
        }

        $perPageDefault = (int) ($options['per_page_default'] ?? 10);
        $perPageMax = (int) ($options['per_page_max'] ?? 100);
        $perPage = min(max((int) request()->input('per_page', $perPageDefault), 1), $perPageMax);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Validate request input parameters for filtered queries.
     *
     * Dynamically builds validation rules based on the configured options
     * (sortable columns, sort map, per_page limits, search, etc.).
     *
     * @param  array  $options  Configuration options defining allowed parameters
     *
     * @throws ValidationException
     */
    public function validate(array $options = []): void
    {
        $sortable = $options['sortable'] ?? [];
        $sortMap = $options['sort_map'] ?? [];
        $sortByOptions = array_values(array_unique([...$sortable, ...array_keys($sortMap)]));
        $perPageMax = (int) ($options['per_page_max'] ?? 100);

        $sortByRule = $sortByOptions === []
            ? ['nullable', 'string']
            : ['nullable', 'string', 'in:'.implode(',', $sortByOptions)];

        $rules = [
            'sort_by' => $sortByRule,
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$perPageMax],
        ];

        if (isset($options['status_column'])) {
            $rules['status'] = ['nullable', 'string', 'in:active,inactive'];
        }

        if (isset($options['featured_column'])) {
            $rules['featured'] = ['nullable', 'string', 'in:featured,not-featured'];
        }

        if (! empty($options['searchable'])) {
            $rules['search'] = ['nullable', 'string', 'max:255'];
        }

        request()->validate($rules);
    }

    /**
     * Check if a given column exists on the query's table.
     *
     * @param  EloquentBuilder|QueryBuilder  $query  The query builder instance
     * @param  string  $column  The column name to check
     * @return bool True if the column exists, false otherwise
     */
    protected function hasColumn(EloquentBuilder|QueryBuilder $query, string $column): bool
    {
        $table = $query instanceof EloquentBuilder
            ? $query->getModel()->getTable()
            : $query->from;

        if (! is_string($table) || $table === '') {
            return false;
        }

        if (str_contains($table, ' as ')) {
            $table = trim(explode(' as ', $table)[0]);
        }

        return Schema::hasColumn($table, $column);
    }
}
