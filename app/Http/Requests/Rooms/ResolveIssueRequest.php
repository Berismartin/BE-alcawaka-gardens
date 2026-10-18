<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Foundation\Http\FormRequest;

class ResolveIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return ['resolution' => ['nullable', 'string', 'max:500']];
    }
}
