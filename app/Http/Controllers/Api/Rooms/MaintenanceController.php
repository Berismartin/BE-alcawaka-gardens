<?php

namespace App\Http\Controllers\Api\Rooms;

use App\Actions\Rooms\MaintenanceIssue\PaginateMaintenanceIssuesAction;
use App\Actions\Rooms\MaintenanceIssue\ReportIssueAction;
use App\Actions\Rooms\MaintenanceIssue\ResolveIssueAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\ListMaintenanceIssuesRequest;
use App\Http\Requests\Rooms\ReportIssueRequest;
use App\Http\Requests\Rooms\ResolveIssueRequest;
use App\Http\Resources\Rooms\MaintenanceIssueResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Maintenance issues: what is broken in which room, and when it was fixed. */
class MaintenanceController extends Controller
{
    public function index(ListMaintenanceIssuesRequest $request, PaginateMaintenanceIssuesAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), MaintenanceIssueResource::class);
    }

    public function store(ReportIssueRequest $request, ReportIssueAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Issue reported.', Response::HTTP_CREATED);
    }

    public function resolve(ResolveIssueRequest $request, string $id, ResolveIssueAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated('resolution')), 'Marked fixed.');
    }
}
