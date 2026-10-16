<?php

namespace App\Domains\Audit\AuditLog\Repositories;

use App\Models\Audit\AuditLog;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    use AppliesListQuery;

    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(AuditLog::query()->with('user:id,name'), $query, ['action']);
    }
}
