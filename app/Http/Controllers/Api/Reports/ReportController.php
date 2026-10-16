<?php

namespace App\Http\Controllers\Api\Reports;

use App\Actions\Reports\DashboardAction;
use App\Actions\Reports\ReportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function dashboard(DashboardAction $action): JsonResponse
    {
        return $this->respond($action->execute());
    }

    public function show(ReportRequest $request, ReportAction $action): JsonResponse
    {
        return $this->respond($action->execute($request->validated('period'), $request->validated('date')));
    }

    /** "Download summary": the same figures as a CSV the owner can open in Excel. */
    public function export(ReportRequest $request, ReportAction $action): StreamedResponse
    {
        $r = $action->execute($request->validated('period'), $request->validated('date'));

        return response()->streamDownload(function () use ($r) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Report', ucfirst($r['period']), $r['from'], $r['to']]);
            fputcsv($out, []);
            fputcsv($out, ['Average occupancy (%)', $r['occupancy_percent']]);
            fputcsv($out, ['Room nights sold', $r['room_nights_sold']]);
            fputcsv($out, ['Income (UGX)', $r['income_ugx']]);
            fputcsv($out, ['Open balances (UGX)', $r['open_balances']['total']]);
            fputcsv($out, ['Open bills', $r['open_balances']['count']]);
            fputcsv($out, ['Bookings made', $r['bookings']]);
            fputcsv($out, []);
            fputcsv($out, ['Revenue by source', 'UGX', '%']);
            foreach ($r['revenue_by_source'] as $s) {
                fputcsv($out, [$s['label'], $s['amount_ugx'], $s['percent']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Income by '.($r['period'] === 'daily' ? 'day' : 'month'), 'UGX']);
            foreach ($r['series'] as $p) {
                fputcsv($out, [$p['key'], $p['income_ugx']]);
            }
            fclose($out);
        }, "report-{$r['period']}-{$r['from']}.csv", ['Content-Type' => 'text/csv']);
    }
}
