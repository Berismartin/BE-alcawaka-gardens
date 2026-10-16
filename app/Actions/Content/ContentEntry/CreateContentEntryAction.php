<?php

namespace App\Actions\Content\ContentEntry;

use App\Domains\Content\ContentEntry\DTOs\ContentEntryDTO;
use App\Domains\Content\ContentEntry\Services\ContentService;
use App\Http\Resources\Content\ContentEntryResource;

class CreateContentEntryAction
{
    public function __construct(private ContentService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): ContentEntryResource
    {
        return new ContentEntryResource($this->service->create(ContentEntryDTO::fromRequest($input)));
    }
}
