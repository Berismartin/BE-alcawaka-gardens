<?php

namespace App\Http\Requests\Content;

use App\Domains\Content\ContentEntry\Services\ContentService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteContentRequest extends FormRequest
{
    private const URLS = ['hero_image_url', 'intro_image_url', 'about_image_url', 'cta_image_url', 'instagram_url', 'facebook_url', 'tiktok_url'];

    private const LONG = ['intro_body', 'about_body'];

    public function rules(): array
    {
        $rules = [];
        foreach (array_keys(ContentService::siteDefaults()) as $key) {
            $rules[$key] = match (true) {
                in_array($key, self::URLS, true) => ['sometimes', 'nullable', 'url:https,http', 'max:1000'],
                in_array($key, self::LONG, true) => ['sometimes', 'nullable', 'string', 'max:5000'],
                $key === 'stats' => ['sometimes', 'array', 'max:4'],
                default => ['sometimes', 'nullable', 'string', 'max:300'],
            };
        }
        $rules['hero_title'] = ['sometimes', 'required', 'string', 'max:120'];
        $rules['stats.*.value'] = ['required', 'string', 'max:20'];
        $rules['stats.*.label'] = ['required', 'string', 'max:60'];

        return $rules;
    }
}
