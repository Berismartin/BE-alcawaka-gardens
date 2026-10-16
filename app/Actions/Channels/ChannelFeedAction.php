<?php

namespace App\Actions\Channels;

use App\Domains\Channels\Channel\Services\ChannelService;

class ChannelFeedAction
{
    public function __construct(private ChannelService $service) {}

    public function execute(string $token): string
    {
        return $this->service->exportFeed($token);
    }
}
