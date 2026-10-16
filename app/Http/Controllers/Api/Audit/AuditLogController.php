<?php

namespace App\Http\Controllers\Api\Audit;

use App\Domains\Audit\AuditLog\Repositories\AuditLogRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\ListAuditLogsRequest;
use App\Http\Resources\Audit\AuditLogResource;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /** Read-only listing; no business logic, so no Action/Service layer. */
    public function index(ListAuditLogsRequest $request, AuditLogRepositoryInterface $logs): JsonResponse
    {
        return $this->respondPage($logs->paginate($request->toListQuery()), AuditLogResource::class);
    }
}
