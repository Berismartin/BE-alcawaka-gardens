<?php

namespace App\Http\Controllers\Api\Guests;

use App\Actions\Guests\Guest\CreateGuestAction;
use App\Actions\Guests\Guest\DeleteGuestAction;
use App\Actions\Guests\Guest\PaginateGuestsAction;
use App\Actions\Guests\Guest\ShowGuestAction;
use App\Actions\Guests\Guest\UpdateGuestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guests\ListGuestsRequest;
use App\Http\Requests\Guests\StoreGuestRequest;
use App\Http\Requests\Guests\UpdateGuestRequest;
use App\Http\Resources\Guests\GuestResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GuestController extends Controller
{
    public function index(ListGuestsRequest $request, PaginateGuestsAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), GuestResource::class);
    }

    public function show(string $id, ShowGuestAction $action): JsonResponse
    {
        return $this->respond($action->execute($id));
    }

    public function store(StoreGuestRequest $request, CreateGuestAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Guest added.', Response::HTTP_CREATED);
    }

    public function update(UpdateGuestRequest $request, string $id, UpdateGuestAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Guest updated.');
    }

    public function destroy(string $id, DeleteGuestAction $action): JsonResponse
    {
        $action->execute($id);

        return $this->respond(null, 'Guest deleted.');
    }
}
