<?php

namespace App\Actions\Channels;

use App\Domains\Channels\Channel\Services\ChannelService;
use App\Http\Resources\Channels\ChannelResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SyncChannelsAction
{
    public function __construct(private ChannelService $service) {}

    public function execute(): AnonymousResourceCollection
    {
        return ChannelResource::collection($this->service->syncAll());
    }
}
