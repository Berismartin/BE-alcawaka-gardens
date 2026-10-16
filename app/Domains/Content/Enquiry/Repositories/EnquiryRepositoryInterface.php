<?php

namespace App\Domains\Content\Enquiry\Repositories;

use App\Models\Content\Enquiry;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EnquiryRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?Enquiry;

    /** @param array<string, mixed> $data */
    public function create(array $data): Enquiry;

    /** @param array<string, mixed> $data */
    public function update(Enquiry $enquiry, array $data): Enquiry;
}
