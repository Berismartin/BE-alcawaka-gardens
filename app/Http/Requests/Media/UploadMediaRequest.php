<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    /** @return array<string, mixed>|null */
    private function collectionConfig(): ?array
    {
        return config('media.collections.'.$this->input('collection'));
    }

    public function authorize(): bool
    {
        $config = $this->collectionConfig();

        // Unknown collection: let rules() answer with a 422 instead of a 403.
        return $config === null || (bool) $this->user()?->role->can($config['permission']);
    }

    public function rules(): array
    {
        $config = $this->collectionConfig();
        $rules = ['collection' => ['required', 'string', Rule::in(array_keys(config('media.collections')))]];
        if ($config === null) {
            return $rules;
        }

        $rules['file'] = ['required', 'file', 'mimes:'.implode(',', $config['mimes']), 'max:'.$config['max_kb']];
        if ($config['mediable']) {
            $rules['mediable_type'] = ['required', Rule::in($config['mediable'])];
            $rules['mediable_id'] = ['required', 'string', 'max:64'];
        } else {
            $rules['mediable_type'] = ['prohibited'];
            $rules['mediable_id'] = ['prohibited'];
        }

        return $rules;
    }

    public function messages(): array
    {
        $config = $this->collectionConfig();
        if ($config === null) {
            return [];
        }
        $types = strtoupper(implode(', ', array_unique(str_replace('jpeg', 'jpg', $config['mimes']))));
        $mb = round($config['max_kb'] / 1024);

        return [
            'file.mimes' => "Files must be {$types} under {$mb} MB.",
            'file.max' => "Files must be {$types} under {$mb} MB.",
        ];
    }
}
