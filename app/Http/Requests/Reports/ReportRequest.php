<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['daily', 'monthly', 'yearly'])],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
