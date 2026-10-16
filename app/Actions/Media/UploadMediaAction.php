<?php

namespace App\Actions\Media;

use App\Domains\Media\Media\DTOs\UploadMediaDTO;
use App\Domains\Media\Media\Services\MediaService;
use App\Http\Resources\Media\MediaResource;

class UploadMediaAction
{
    public function __construct(private MediaService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input, ?int $userId): MediaResource
    {
        return new MediaResource($this->service->upload(UploadMediaDTO::fromRequest($input, $userId)));
    }
}
