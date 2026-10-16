<?php

namespace App\Domains\Media\Media\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class UploadMediaDTO
{
    public function __construct(
        public UploadedFile $file,
        public string $collection,
        public ?string $mediableType = null,
        public ?string $mediableId = null,
        public ?int $uploadedBy = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data, ?int $userId): self
    {
        return new self(
            file: $data['file'],
            collection: $data['collection'],
            mediableType: $data['mediable_type'] ?? null,
            mediableId: isset($data['mediable_id']) ? (string) $data['mediable_id'] : null,
            uploadedBy: $userId,
        );
    }
}
