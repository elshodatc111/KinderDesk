<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Lead;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $aiReport = auth()->user()->can('ai.report') ? \App\Models\AiReport::whereIn('status', ['ready', 'unreliable'])->orderByDesc('date')->first() : null;

        $staleLeads = feature('leads') && auth()->user()->can('leads.view') ? \App\Services\Leads\LeadFlow::stale()->count() : 0;

        return view('app.dashboard', ['stats' => $this->stats(), 'aiReport' => $aiReport, 'staleLeads' => $staleLeads, 'w' => app(\App\Services\Stats\DashboardWidgets::class)->build(auth()->user())]);
    }

    public function stats()
    {
        $t = tenant();
        $user = auth()->user();
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $out = [];

        $out['kids'] = Kid::where('status', 'active')->count();
        $out['groups'] = Group::where('status', 'active')->count();
        $out['employees'] = Employee::where('status', 'active')->count();

        if ($t->hasFeature('attendance')) {
            $marked = KidAttendance::whereDate('date', $today)->selectRaw("status, count(*) c")->groupBy('status')->pluck('c', 'status');
            $present = (int) ($marked['present'] ?? 0);
            $total = (int) $marked->sum();
            $out['attendance'] = ['present' => $present, 'absent' => $total - $present, 'marked' => $total, 'percent' => $total ? round($present / $total * 100) : null];
        }

        if ($t->hasFeature('finance') && $user->hasPermission('finance.view')) {
            $sum = fn (array $types, Carbon $from) => (int) FinanceTransaction::whereIn('type', $types)->where('status', 'confirmed')
                ->whereDate('occurred_on', '>=', $from)->sum('amount');
            $out['income_month'] = $sum(['kid_payment', 'income'], $monthStart);
            $out['income_today'] = (int) FinanceTransaction::whereIn('type', ['kid_payment', 'income'])->where('status', 'confirmed')->whereDate('occurred_on', $today)->sum('amount');
            $out['expense_month'] = abs($sum(['expense', 'salary'], $monthStart));
            $out['debt'] = abs((int) Kid::where('status', 'active')->where('balance', '<', 0)->sum('balance'));
            $out['debtors'] = Kid::where('status', 'active')->where('balance', '<', 0)->count();
            $out['pending'] = FinanceTransaction::where('status', 'pending')->count();

            $chart = ['labels' => [], 'income' => [], 'expense' => []];
            for ($i = 5; $i >= 0; $i--) {
                $from = $today->copy()->subMonthsNoOverflow($i)->startOfMonth();
                $to = $from->copy()->endOfMonth();
                $q = fn (array $types) => (int) FinanceTransaction::whereIn('type', $types)->where('status', 'confirmed')->whereBetween('occurred_on', [$from, $to])->sum('amount');
                $chart['labels'][] = ucfirst(mb_substr($from->copy()->locale(app()->getLocale() === 'ru' ? 'ru' : 'uz_Latn')->translatedFormat('M'), 0, 4));
                $chart['income'][] = $q(['kid_payment', 'income']);
                $chart['expense'][] = abs($q(['expense', 'salary']));
            }
            $out['chart'] = $chart;
        }

        if ($t->hasFeature('leads') && $user->hasPermission('leads.view')) {
            $out['new_leads'] = Lead::where('result', 'open')->where('created_at', '>=', $today->copy()->subDays(7))->count();
        }

        $out['birthdays'] = Kid::where('status', 'active')->get(['id', 'full_name', 'birth_date'])
            ->filter(function ($k) use ($today) {
                $b = $k->birth_date->copy()->year($today->year);

                return $b->between($today, $today->copy()->addDays(7));
            })->sortBy(fn ($k) => $k->birth_date->copy()->year($today->year))->take(6)
            ->map(fn ($k) => ['id' => $k->id, 'name' => $k->full_name, 'date' => $k->birth_date->copy()->year($today->year)->format('d.m'), 'age' => $today->year - $k->birth_date->year])->values();

        if (request()->expectsJson()) {
            return response()->json($out);
        }

        return $out;
    }
}
