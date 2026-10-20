<?php

namespace App\Http\Controllers\Api\Facilities;

use App\Actions\Facilities\FacilityBooking\CancelFacilityBookingAction;
use App\Actions\Facilities\FacilityBooking\CreateFacilityBookingAction;
use App\Actions\Facilities\FacilityBooking\FacilityBookingSummaryAction;
use App\Actions\Facilities\FacilityBooking\PaginateFacilityBookingsAction;
use App\Actions\Facilities\FacilityBooking\PayFacilityBookingAction;
use App\Actions\Facilities\FacilityBooking\ShowFacilityBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facilities\FacilityBooking\ListFacilityBookingsRequest;
use App\Http\Requests\Facilities\FacilityBooking\StoreFacilityBookingRequest;
use App\Http\Requests\Payments\RecordPaymentRequest;
use App\Http\Resources\Facilities\FacilityBooking\FacilityBookingResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FacilityBookingController extends Controller
{
    public function index(ListFacilityBookingsRequest $request, PaginateFacilityBookingsAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), FacilityBookingResource::class);
    }

    public function summary(FacilityBookingSummaryAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function show(string $id, ShowFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($id));
    }

    public function store(StoreFacilityBookingRequest $request, CreateFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Facility booked.', Response::HTTP_CREATED);
    }

    public function pay(RecordPaymentRequest $request, string $id, PayFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Payment recorded.');
    }

    public function cancel(string $id, CancelFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Facility booking cancelled.');
    }
}
