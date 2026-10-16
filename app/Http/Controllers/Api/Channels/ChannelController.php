<?php

namespace App\Http\Controllers\Api\Channels;

use App\Actions\Channels\ChannelAvailabilityAction;
use App\Actions\Channels\ChannelFeedAction;
use App\Actions\Channels\CreateChannelAction;
use App\Actions\Channels\DeleteChannelAction;
use App\Actions\Channels\ListChannelsAction;
use App\Actions\Channels\SyncChannelsAction;
use App\Actions\Channels\UpdateChannelAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\ChannelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as Status;

class ChannelController extends Controller
{
    public function index(ListChannelsAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function availability(Request $request, ChannelAvailabilityAction $action): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'days' => ['sometimes', 'integer', 'min:1', 'max:31']]);

        return $this->respond($action->execute($data['from'], (int) ($data['days'] ?? 7)));
    }

    public function store(ChannelRequest $request, CreateChannelAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Channel added.', Status::HTTP_CREATED);
    }

    public function update(ChannelRequest $request, string $id, UpdateChannelAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Channel updated.');
    }

    public function destroy(string $id, DeleteChannelAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Channel removed.');
    }

    public function sync(SyncChannelsAction $action): JsonResponse
    {
        return $this->respond($action->execute(), 'Channels synced.');
    }

    /** Public calendar feed for OTAs (the token in the URL is the secret). */
    public function feed(string $token, ChannelFeedAction $action): Response
    {
        return response($action->execute($token), 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }
}
