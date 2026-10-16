<?php

namespace App\Http\Requests\Channels;

use Illuminate\Foundation\Http\FormRequest;

class ChannelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'import_url' => ['nullable', 'url:https,http', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
