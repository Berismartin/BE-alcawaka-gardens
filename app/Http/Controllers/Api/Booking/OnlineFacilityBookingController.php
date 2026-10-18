<?php

namespace App\Http\Controllers\Api\Booking;

use App\Actions\Booking\Facility\BookFacilityOnlineAction;
use App\Actions\Booking\Facility\PayFacilityBookingAction;
use App\Actions\Booking\Facility\ShowFacilityBookingAction;
use App\Actions\Booking\Facility\SimulateFacilityPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\GuestKeyRequest;
use App\Http\Requests\Booking\OnlineFacilityBookingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public website facility booking (A08): day passes and event space, paid online. The key proves ownership. */
class OnlineFacilityBookingController extends Controller
{
    public function store(OnlineFacilityBookingRequest $request, BookFacilityOnlineAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Held while you pay.', Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id, ShowFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, (string) $request->query('key')));
    }

    public function pay(GuestKeyRequest $request, string $id, PayFacilityBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated('key'), $request->validated('payment_method')), 'Payment request sent.');
    }

    public function simulate(GuestKeyRequest $request, string $id, SimulateFacilityPaymentAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated('key'), $request->validated('outcome') === 'approve'));
    }
}
