<?php

namespace App\Http\Requests\Content\Enquiry;

use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnquiryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(EnquiryStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
        ];
    }
}
