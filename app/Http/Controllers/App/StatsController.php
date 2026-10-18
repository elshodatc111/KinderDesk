<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Stats\StatsService;
use Illuminate\Http\Request;

/** Statistika: kunlik, haftalik, oylik va yillik diagrammalar. */
class StatsController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $u = $request->user();
        $period = in_array($request->query('period'), StatsService::PERIODS, true) ? $request->query('period') : 'day';

        $data = $stats->build(
            $period,
            finance: feature('finance') && $u->can('finance.view'),
            staff: feature('staff_attendance') && $u->can('attendance.staff'),
            leads: feature('leads') && $u->can('leads.view'),
            subsidy: feature('finance') && $u->can('finance.view'),
        );

        return view('app.stats.index', ['data' => $data, 'period' => $period]);
    }
}
