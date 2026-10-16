<?php

namespace App\Actions\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Services\ContentService;

class DeleteContentEntryAction
{
    public function __construct(private ContentService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
