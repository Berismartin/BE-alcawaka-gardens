<?php

namespace App\Http\Controllers\Api\Booking;

use App\Actions\Booking\BookOnlineAction;
use App\Actions\Booking\DownloadBookingReceiptAction;
use App\Actions\Booking\GuestRequestAction;
use App\Actions\Booking\LookupBookingAction;
use App\Actions\Booking\PayOnlineBookingAction;
use App\Actions\Booking\SearchRoomsAction;
use App\Actions\Booking\ShowOnlineBookingAction;
use App\Actions\Booking\SimulatePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\GuestKeyRequest;
use App\Http\Requests\Booking\LookupBookingRequest;
use App\Http\Requests\Booking\OnlineBookingRequest;
use App\Http\Requests\Booking\SearchRoomsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/** Public website booking (A02–A07). The guest's key in each call is what proves it's their booking. */
class OnlineBookingController extends Controller
{
    public function search(SearchRoomsRequest $request, SearchRoomsAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()));
    }

    public function store(OnlineBookingRequest $request, BookOnlineAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated()), 'Room held while you pay.', Response::HTTP_CREATED);
    }

    public function show(Request $request, string $reference, ShowOnlineBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($reference, (string) $request->query('key')));
    }

    public function receipt(Request $request, string $reference, DownloadBookingReceiptAction $action): HttpResponse
    {
        return $action->execute($reference, (string) $request->query('key'));
    }

    public function pay(GuestKeyRequest $request, string $reference, PayOnlineBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($reference, $request->validated('key'), $request->validated('payment_method')), 'Payment request sent.');
    }

    public function simulate(GuestKeyRequest $request, string $reference, SimulatePaymentAction $action): JsonResponse
    {
        return $this->respond($action->execute($reference, $request->validated('key'), $request->validated('outcome') === 'approve'));
    }

    public function lookup(LookupBookingRequest $request, LookupBookingAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated('reference'), $request->validated('contact')));
    }

    public function request(GuestKeyRequest $request, string $reference, GuestRequestAction $action): JsonResponse
    {
        $action->execute($reference, $request->validated());

        return $this->respond(null, 'Request sent. The front desk will confirm by email or phone.');
    }
}
