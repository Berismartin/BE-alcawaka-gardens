<?php

namespace App\Actions\Channels;

use App\Domains\Channels\Channel\Services\ChannelService;

class ChannelAvailabilityAction
{
    public function __construct(private ChannelService $service) {}

    /** @return array<string, mixed> */
    public function execute(string $from, int $days): array
    {
        return $this->service->availability($from, $days);
    }
}
