<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use App\Services\ReportExporter;
use App\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Отчёты за период с графиками и выгрузкой в Excel (ТЗ 2.10). */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExporter $exporter,
    ) {}

    public function index(ReportRequest $request): Response
    {
        [$from, $to] = $this->reports->period($request->validated('from'), $request->validated('to'));

        return Inertia::render('Reports/Index', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ...$this->reports->build($from, $to),
        ]);
    }

    public function export(ReportRequest $request, string $report): HttpResponse
    {
        abort_unless(in_array($report, ReportExporter::REPORTS, true), 404);
        [$from, $to] = $this->reports->period($request->validated('from'), $request->validated('to'));

        $content = $this->exporter->export($report, $from, $to)->toString();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->exporter->filename($report, $from, $to).'"',
            'Content-Length' => (string) strlen($content),
        ]);
    }
}
