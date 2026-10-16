<?php

namespace App\Domains\Audit\AuditLog\Repositories;

use App\Models\Audit\AuditLog;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): AuditLog;

    public function paginate(ListQuery $query): LengthAwarePaginator;
}
