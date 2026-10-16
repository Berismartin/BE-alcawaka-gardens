<?php

namespace App\Actions\Content\Enquiry;

use App\Domains\Content\Enquiry\DTOs\CreateEnquiryDTO;
use App\Domains\Content\Enquiry\Services\EnquiryService;
use App\Http\Resources\Content\EnquiryResource;

class CreateEnquiryAction
{
    public function __construct(private EnquiryService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): EnquiryResource
    {
        return new EnquiryResource($this->service->create(CreateEnquiryDTO::fromRequest($input)));
    }
}
