<?php

namespace App\Http\Controllers\Api\Reservations;

use App\Actions\Reservations\Reservation\AddChargeAction;
use App\Actions\Reservations\Reservation\DownloadReceiptAction;
use App\Actions\Reservations\Reservation\RecordPaymentAction;
use App\Actions\Reservations\Reservation\RemoveChargeAction;
use App\Actions\Reservations\Reservation\SendReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\RecordPaymentRequest;
use App\Http\Requests\Reservations\AddChargeRequest;
use App\Http\Requests\Reservations\SendReceiptRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/** The guest's bill (B06). Each call returns the whole updated reservation. */
class FolioController extends Controller
{
    public function addCharge(AddChargeRequest $request, string $id, AddChargeAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Charge added.', Response::HTTP_CREATED);
    }

    public function removeCharge(string $id, string $chargeId, RemoveChargeAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $chargeId), 'Charge removed.');
    }

    public function recordPayment(RecordPaymentRequest $request, string $id, RecordPaymentAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated()), 'Payment recorded.', Response::HTTP_CREATED);
    }

    public function sendReceipt(SendReceiptRequest $request, string $id, SendReceiptAction $action): JsonResponse
    {
        $to = $action->execute($id, $request->validated('email'));

        return $this->respond(['email' => $to], "Receipt sent to {$to}.");
    }

    public function receiptPdf(string $id, DownloadReceiptAction $action): HttpResponse
    {
        return $action->execute($id);
    }
}
