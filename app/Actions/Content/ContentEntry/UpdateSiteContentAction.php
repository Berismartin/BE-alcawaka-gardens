<?php

namespace App\Actions\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Services\ContentService;

class UpdateSiteContentAction
{
    public function __construct(private ContentService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): array
    {
        return $this->service->updateSite($input);
    }
}
