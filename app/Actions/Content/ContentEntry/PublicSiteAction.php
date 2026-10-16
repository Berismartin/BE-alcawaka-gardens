<?php

namespace App\Actions\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Services\ContentService;
use App\Http\Resources\Content\ContentEntryResource;

class PublicSiteAction
{
    public function __construct(private ContentService $service) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $site = $this->service->publicSite();
        $site['entries'] = array_map(fn (array $list) => ContentEntryResource::collection($list), $site['entries']);

        return $site;
    }
}
