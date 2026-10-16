<?php

namespace App\Actions\Channels;

use App\Domains\Channels\Channel\DTOs\ChannelDTO;
use App\Domains\Channels\Channel\Services\ChannelService;
use App\Http\Resources\Channels\ChannelResource;

class UpdateChannelAction
{
    public function __construct(private ChannelService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): ChannelResource
    {
        return new ChannelResource($this->service->update($id, ChannelDTO::fromRequest($input)));
    }
}
