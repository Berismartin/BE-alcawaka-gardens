<?php

namespace App\Http\Requests;

use App\Support\Query\ListQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base for every paginated list endpoint:
 * ?page=&per_page=&q=&sort=field|-field&filter[key]=&from=Y-m-d&to=Y-m-d
 */
abstract class ListRequest extends FormRequest
{
    /** @return list<string> columns the client may sort by */
    abstract protected function sortable(): array;

    /** @return array<string, array<int, mixed>> filter key => validation rules */
    protected function filters(): array
    {
        return [];
    }

    public function rules(): array
    {
        $sorts = [];
        foreach ($this->sortable() as $field) {
            array_push($sorts, $field, "-{$field}");
        }
        $filterKeys = array_keys($this->filters());

        $rules = [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in(ListQuery::PER_PAGE)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', 'nullable', Rule::in($sorts)],
            'filter' => $filterKeys ? ['sometimes', 'array:'.implode(',', $filterKeys)] : ['prohibited'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
        foreach ($this->filters() as $key => $keyRules) {
            $rules["filter.{$key}"] = ['sometimes', ...$keyRules];
        }

        return $rules;
    }

    public function toListQuery(): ListQuery
    {
        $data = $this->validated();
        // Query strings carry booleans as text; compare them as real booleans.
        $filters = array_map(
            fn ($v) => in_array($v, ['true', 'false'], true) ? $v === 'true' : $v,
            array_filter($data['filter'] ?? [], fn ($v) => $v !== null && $v !== ''),
        );

        return new ListQuery(
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['per_page'] ?? 25),
            search: $data['q'] ?? null,
            sort: $data['sort'] ?? null,
            filters: $filters,
            from: $data['from'] ?? null,
            to: $data['to'] ?? null,
        );
    }
}
