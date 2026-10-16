<?php

namespace App\Http\Controllers\Api\Staff;

use App\Actions\Staff\InviteStaffAction;
use App\Actions\Staff\PaginateStaffAction;
use App\Actions\Staff\ResendInviteAction;
use App\Actions\Staff\UpdateStaffAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Http\Requests\Staff\ListStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\Users\StaffResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    public function index(ListStaffRequest $request, PaginateStaffAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), StaffResource::class);
    }

    public function store(InviteStaffRequest $request, InviteStaffAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Invitation sent.', Response::HTTP_CREATED);
    }

    public function update(UpdateStaffRequest $request, int $id, UpdateStaffAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated(), $request->user()), 'Staff account updated.');
    }

    public function resendInvite(int $id, ResendInviteAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Invitation sent again.');
    }
}
