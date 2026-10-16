<?php

namespace App\Http\Requests\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Enums\ContentType;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListContentEntriesRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['sort_order', 'title', 'created_at'];
    }

    protected function filters(): array
    {
        return [
            'type' => [Rule::enum(ContentType::class)],
            'is_published' => [Rule::in(['true', 'false', '1', '0'])],
        ];
    }
}
