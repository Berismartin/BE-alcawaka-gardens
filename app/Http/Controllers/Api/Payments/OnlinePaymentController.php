<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\Online\PayPaymentLinkAction;
use App\Actions\Payments\Online\RequestOnlinePaymentAction;
use App\Actions\Payments\Online\SendPaymentLinkAction;
use App\Actions\Payments\Online\ShowPaymentLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\OnlinePaymentRequest;
use App\Http\Requests\Payments\PaymentLinkCheckoutRequest;
use App\Http\Requests\Payments\SendPaymentLinkRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Pesapal payments started by staff, and emailed invoice links guests pay themselves. */
class OnlinePaymentController extends Controller
{
    /** POST /portal/{reservations|facility-bookings}/{id}/online-payments */
    public function request(OnlinePaymentRequest $request, string $id, RequestOnlinePaymentAction $action): JsonResponse
    {
        $payment = $action->execute($request->route('ownerType'), $id, $request->validated());
        $message = $payment->resource->status->value === 'failed'
            ? 'Pesapal could not start the payment. Try again, or record cash instead.'
            : 'Payment started. The guest pays on Pesapal’s page.';

        return $this->respond($payment, $message, Response::HTTP_CREATED);
    }

    /** POST /portal/{reservations|facility-bookings}/{id}/payment-links */
    public function sendLink(SendPaymentLinkRequest $request, string $id, SendPaymentLinkAction $action): JsonResponse
    {
        $link = $action->execute($request->route('ownerType'), $id, $request->validated());
        $message = $link->toArray($request)['emailed']
            ? "Invoice emailed to {$request->validated('email')}."
            : 'The link is ready, but the email could not be sent. Copy the link and send it another way.';

        return $this->respond($link, $message, Response::HTTP_CREATED);
    }

    /** GET /pay/{token} (public) */
    public function showLink(string $token, ShowPaymentLinkAction $action): JsonResponse
    {
        return $this->respond($action->execute($token));
    }

    /** POST /pay/{token}/checkout (public) */
    public function payLink(PaymentLinkCheckoutRequest $request, string $token, PayPaymentLinkAction $action): JsonResponse
    {
        return $this->respond($action->execute($token, $request->validated('method')));
    }
}
