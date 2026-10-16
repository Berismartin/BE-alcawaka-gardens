<?php

namespace App\Http\Requests\Content\Enquiry;

use Illuminate\Foundation\Http\FormRequest;

/** Public website form. */
class StoreEnquiryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:9', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'event_type' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'message' => ['required', 'string', 'max:3000'],
            // Honeypot: hidden on the page, so only bots fill it in.
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a phone number, for example 0772 123 456.'];
    }
}
