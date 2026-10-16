<?php

namespace App\Domains\Content\ContentEntry\DTOs;

/**
 * Create or partial update: only the fields the client sent are present,
 * so a field can be cleared (sent as null) without touching the others.
 */
final readonly class ContentEntryDTO
{
    public const FIELDS = ['type', 'title', 'summary', 'body', 'image_url', 'data', 'sort_order', 'is_published'];

    /** @param array<string, mixed> $attributes */
    public function __construct(public array $attributes) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(array_intersect_key($data, array_flip(self::FIELDS)));
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->attributes);
    }
}
