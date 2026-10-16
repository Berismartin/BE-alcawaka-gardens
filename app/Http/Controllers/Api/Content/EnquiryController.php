<?php

namespace App\Http\Controllers\Api\Content;

use App\Actions\Content\Enquiry\CreateEnquiryAction;
use App\Actions\Content\Enquiry\PaginateEnquiriesAction;
use App\Actions\Content\Enquiry\UpdateEnquiryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\Enquiry\ListEnquiriesRequest;
use App\Http\Requests\Content\Enquiry\StoreEnquiryRequest;
use App\Http\Requests\Content\Enquiry\UpdateEnquiryRequest;
use App\Http\Resources\Content\EnquiryResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request, CreateEnquiryAction $action): JsonResponse
    {
        $action->execute($request->validated());

        // The public form gets no record back: nothing about the enquiry is exposed.
        return $this->respond(null, 'Thank you. Our events team will call you within one working day.', Response::HTTP_CREATED);
    }

    public function index(ListEnquiriesRequest $request, PaginateEnquiriesAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), EnquiryResource::class);
    }

    public function update(UpdateEnquiryRequest $request, string $id, UpdateEnquiryAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Enquiry updated.');
    }
}
