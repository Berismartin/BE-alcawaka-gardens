<?php

namespace App\Models\Content;

use App\Domains\Content\ContentEntry\Enums\ContentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['type', 'title', 'slug', 'summary', 'body', 'image_url', 'data', 'sort_order', 'is_published'])]
class ContentEntry extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'data' => 'array',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
