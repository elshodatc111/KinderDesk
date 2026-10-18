<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\Kid;
use App\Models\KidAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01');
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $t = tenant();
        $data = ['month' => $month];

        $data['kids_by_group'] = Group::active()->withCount('activeKidLinks')->orderBy('name')->get();
        if ($t->hasFeature('finance')) {
            $data['debtors'] = Kid::active()->where('balance', '<', 0)->with('currentLink.group')->orderBy('balance')->limit(50)->get();
            $data['income_by_cat'] = FinanceTransaction::whereIn('type', ['income', 'kid_payment'])->where('status', 'confirmed')->whereBetween('occurred_on', [$from, $to])
                ->selectRaw("coalesce(category, 'Boshqa') as cat, sum(amount) as s")->groupBy('cat')->orderByDesc('s')->get();
            $data['expense_by_cat'] = FinanceTransaction::whereIn('type', ['expense', 'salary'])->where('status', 'confirmed')->whereBetween('occurred_on', [$from, $to])
                ->selectRaw("coalesce(category, 'Boshqa') as cat, sum(-amount) as s")->groupBy('cat')->orderByDesc('s')->get();
        }
        if ($t->hasFeature('attendance')) {
            $data['attendance'] = Group::active()->orderBy('name')->get()->map(function ($g) use ($from, $to) {
                $c = KidAttendance::where('group_id', $g->id)->whereBetween('date', [$from, $to])->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
                $total = (int) $c->sum();

                return ['name' => $g->name, 'present' => (int) ($c['present'] ?? 0), 'absent' => (int) ($c['absent'] ?? 0), 'sick' => (int) ($c['sick'] ?? 0), 'percent' => $total ? round(($c['present'] ?? 0) / $total * 100) : null];
            });
        }

        return view('app.reports.index', $data);
    }

    public function export(Request $request, string $what): StreamedResponse
    {
        // Eksport faqat direktorda (owner); boshqa rolga berib bo'lmaydi
        abort_unless($request->user()->is_owner && tenant()->hasFeature('exports'), 403);
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', today()->toDateString()));

        [$headers, $rows, $name] = match ($what) {
            'kids' => [
                ['Ism', 'Guruh', 'Tug\'ilgan sana', 'Ota-ona', 'Telefon', 'Hisob (so\'m)', 'Holat'],
                Kid::with('currentLink.group')->orderBy('full_name')->get()->map(fn ($k) => [$k->full_name, $k->currentLink?->group?->name, $k->birth_date->format('d.m.Y'), $k->parent_name, $k->parent_phone, $k->balance, $k->status === 'active' ? 'Faol' : 'Arxiv']),
                'bolalar',
            ],
            'debtors' => [
                ['Ism', 'Guruh', 'Telefon', 'Qarz (so\'m)'],
                Kid::active()->where('balance', '<', 0)->with('currentLink.group')->orderBy('balance')->get()->map(fn ($k) => [$k->full_name, $k->currentLink?->group?->name, $k->parent_phone, abs($k->balance)]),
                'qarzdorlar',
            ],
            'finance' => [
                ['Sana', 'Turi', 'Hisob', 'Bola', 'Toifa', 'Izoh', 'Holat', 'Summa (so\'m)'],
                FinanceTransaction::with('kid')->whereBetween('occurred_on', [$from, $to])->orderBy('occurred_on')->get()->map(fn ($t) => [$t->occurred_on->format('d.m.Y'), $t->typeLabel(), FinanceTransaction::accountLabels()[$t->account], $t->kid?->full_name, $t->category, $t->description, $t->status, $t->amount]),
                'moliya_'.$from->format('Ymd').'_'.$to->format('Ymd'),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel uchun UTF-8
            fputcsv($out, $headers, ';');
            foreach ($rows as $r) {
                fputcsv($out, $r instanceof \Illuminate\Support\Collection ? $r->all() : $r, ';');
            }
            fclose($out);
        }, "kinderdesk_{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
