<?php

namespace App\Http\Controllers\Api\Payments;

use App\Actions\Payments\Payment\CheckPaymentAction;
use App\Actions\Payments\Payment\ExportPaymentsAction;
use App\Actions\Payments\Payment\PaginatePaymentsAction;
use App\Actions\Payments\Payment\PaymentSummaryAction;
use App\Actions\Payments\Payment\RefundPaymentAction;
use App\Actions\Payments\Payment\SettlePaymentAction;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ListPaymentsRequest;
use App\Http\Requests\Payments\RefundRequest;
use App\Http\Requests\Payments\SettlePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(ListPaymentsRequest $request, PaginatePaymentsAction $action): JsonResponse
    {
        return $this->respondPage($action->execute($request->toListQuery()), PaymentResource::class);
    }

    public function summary(Request $request, PaymentSummaryAction $action): JsonResponse
    {
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? null;

        return $this->respond($action->execute($date));
    }

    /** CSV of the same filtered list ("Download summary"). */
    public function export(ListPaymentsRequest $request, ExportPaymentsAction $action): StreamedResponse
    {
        $rows = $action->execute($request->toListQuery());
        $name = 'payments-'.now('Africa/Kampala')->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function refund(RefundRequest $request, string $id, RefundPaymentAction $action): JsonResponse
    {
        $refund = $action->execute($id, $request->validated());
        $message = $refund->resource->status === PaymentStatus::Pending
            ? 'Refund requested from Pesapal. It shows as refunded once Pesapal pays it back.'
            : 'Refund recorded.';

        return $this->respond($refund, $message, Response::HTTP_CREATED);
    }

    public function check(string $id, CheckPaymentAction $action): JsonResponse
    {
        return $this->respond($action->execute($id), 'Checked with Pesapal.');
    }

    public function settle(SettlePaymentRequest $request, string $id, SettlePaymentAction $action): JsonResponse
    {
        return $this->respond($action->execute($id, $request->validated('status')), 'Payment updated.');
    }
}
