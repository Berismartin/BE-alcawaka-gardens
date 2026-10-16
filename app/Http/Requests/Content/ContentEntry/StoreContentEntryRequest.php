<?php

namespace App\Http\Requests\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Enums\ContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `data` is checked per type in ContentService (ContentType::dataRules). */
class StoreContentEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContentType::class)],
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'url:https,http', 'max:1000'],
            'data' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
