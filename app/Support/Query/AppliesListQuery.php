<?php

namespace App\Support\Query;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** For Eloquent repositories: search, filter, date range, sort and paginate in one place. */
trait AppliesListQuery
{
    /**
     * @param  list<string>  $searchColumns
     */
    protected function paginateList(
        Builder $query,
        ListQuery $list,
        array $searchColumns = [],
        ?string $dateColumn = 'created_at',
        string $defaultSort = '-created_at',
    ): LengthAwarePaginator {
        if ($list->search !== null && $list->search !== '' && $searchColumns) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $q) use ($searchColumns, $term) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        }

        foreach ($list->filters as $field => $value) {
            $query->where($field, $value);
        }

        // Dates arrive as hotel-local days (Y-m-d) and cover the whole day.
        if ($dateColumn && $list->from) {
            $query->where($dateColumn, '>=', Carbon::parse($list->from, 'Africa/Kampala')->startOfDay()->utc());
        }
        if ($dateColumn && $list->to) {
            $query->where($dateColumn, '<=', Carbon::parse($list->to, 'Africa/Kampala')->endOfDay()->utc());
        }

        $sort = $list->sort ?? $defaultSort;
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy($query->getModel()->getKeyName()); // stable order across pages

        return $query->paginate($list->perPage, ['*'], 'page', $list->page);
    }
}
