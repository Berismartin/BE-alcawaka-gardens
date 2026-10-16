<?php

namespace App\Actions\Content\Enquiry;

use App\Domains\Content\Enquiry\Services\EnquiryService;
use App\Http\Resources\Content\EnquiryResource;

class UpdateEnquiryAction
{
    public function __construct(private EnquiryService $service) {}

    /** @param array{status?: string, notes?: ?string} $input */
    public function execute(string $id, array $input): EnquiryResource
    {
        return new EnquiryResource($this->service->update($id, $input));
    }
}
