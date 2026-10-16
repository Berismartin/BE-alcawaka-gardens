<?php

namespace App\Http\Requests\Content\ContentEntry;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image_url' => ['sometimes', 'nullable', 'url:https,http', 'max:1000'],
            'data' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
