<?php

namespace App\Actions\Content\Enquiry;

use App\Domains\Content\Enquiry\Services\EnquiryService;
use App\Http\Resources\Content\EnquiryResource;

class ShowEnquiryAction
{
    public function __construct(private EnquiryService $service) {}

    public function execute(string $id): EnquiryResource
    {
        return new EnquiryResource($this->service->find($id));
    }
}
