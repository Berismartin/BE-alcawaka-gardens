<?php

namespace App\Actions\Channels;

use App\Domains\Channels\Channel\Services\ChannelService;

class DeleteChannelAction
{
    public function __construct(private ChannelService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
