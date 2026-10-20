<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\Gateway\GatewayReturnAction;
use App\Actions\Payments\Gateway\SyncGatewayPaymentAction;
use App\Exceptions\EntityNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\GatewayReturnRequest;
use App\Http\Requests\Payments\PesapalIpnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Pesapal talks to us here: its IPN, and the guest's browser coming back from the payment page. */
class PesapalController extends Controller
{
    /**
     * Pesapal expects its own reply shape, not our envelope. status 200 = handled; 500 = try again later.
     * An order we don't know is acknowledged (200) so Pesapal stops retrying, and logged.
     */
    public function ipn(PesapalIpnRequest $request, SyncGatewayPaymentAction $action): JsonResponse
    {
        $trackingId = $request->validated('OrderTrackingId');
        try {
            $action->execute($trackingId);
            $status = 200;
        } catch (EntityNotFoundException) {
            Log::warning('Pesapal IPN for an unknown order', ['order_tracking_id' => $trackingId]);
            $status = 200;
        } catch (Throwable $e) {
            report($e);
            $status = 500;
        }

        return response()->json([
            'orderNotificationType' => $request->validated('OrderNotificationType') ?? 'IPNCHANGE',
            'orderTrackingId' => $trackingId,
            'orderMerchantReference' => $request->validated('OrderMerchantReference') ?? '',
            'status' => $status,
        ]);
    }

    public function return(GatewayReturnRequest $request, GatewayReturnAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated('order_tracking_id')));
    }
}
