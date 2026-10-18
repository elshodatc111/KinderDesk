<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AiReport;
use App\Services\Ai\ReportService;

class AiReportController extends Controller
{
    public function index()
    {
        return view('app.ai.reports', ['reports' => AiReport::orderByDesc('date')->limit(60)->get(), 'enabled' => ! tenant()->ai_disabled && tenant()->setting('ai.enabled', true)]);
    }

    public function show(AiReport $report, ReportService $svc)
    {
        $r = $report->result ?? [];
        // Pseudonimlarni (Xodim-12) faqat direktor ekranida, sozlama yoqilgan bo'lsa ismga qaytaramiz
        $r = json_decode($svc->deanonymize(json_encode($r, JSON_UNESCAPED_UNICODE)), true) ?: $r;

        return view('app.ai.report', ['report' => $report, 'r' => $r]);
    }
}
