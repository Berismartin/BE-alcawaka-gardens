<?php

namespace App\Domains\Content\Enquiry\Repositories;

use App\Models\Content\Enquiry;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EnquiryRepository implements EnquiryRepositoryInterface
{
    use AppliesListQuery;

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(Enquiry::query()->with(['reservation:id,number,status', 'attendant:id,name']), $query, ['name', 'phone', 'email', 'event_type', 'message']);
    }

    public function findById(string $id): ?Enquiry
    {
        return Enquiry::with(['reservation:id,number,status', 'attendant:id,name'])->find($id);
    }

    public function create(array $data): Enquiry
    {
        return Enquiry::create($data);
    }

    public function update(Enquiry $enquiry, array $data): Enquiry
    {
        $enquiry->update($data);

        return $enquiry->refresh()->load(['reservation:id,number,status', 'attendant:id,name']);
    }
}
