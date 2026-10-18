<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Group;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Services\Finance\FinanceService;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayScheme;
use App\Services\Payroll\SchemePayroll;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollCalculator $calc, FinanceService $finance)
    {
        $period = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01');
        $slips = Payslip::with('employee')->whereDate('period', $period)->get()->keyBy('employee_id');
        $employees = Employee::active()->orderBy('full_name')->get();

        $periodData = tenant()->hasFeature('pay_schemes') ? app(SchemePayroll::class)->period($period) : null;
        $rows = $employees->map(function ($e) use ($slips, $calc, $period, $periodData) {
            $slip = $slips[$e->id] ?? null;

            return ['employee' => $e, 'slip' => $slip, 'preview' => $slip ? null : $calc->calculate($e, $period, $periodData)];
        });

        return view('app.payroll.index', [
            'period' => $period, 'rows' => $rows,
            'totalDue' => $slips->sum(fn ($s) => $s->total - $s->paid),
            'accounts' => $finance->enabledAccounts(), 'balances' => $finance->balances(),
            'strategy' => tenant()->setting('payroll.strategy', 'fixed'),
            'schemes' => tenant()->hasFeature('pay_schemes'),
            'items' => tenant()->hasFeature('pay_schemes') ? PayslipItem::whereDate('period', $period)->orderBy('id')->get()->groupBy('employee_id') : collect(),
        ]);
    }

    /** Oy uchun barcha xodimlarga hisob-kitob varaqalarini yaratadi (mavjudlari o'zgarmaydi). */
    public function generate(Request $request, PayrollCalculator $calc)
    {
        $period = Carbon::parse(($request->input('month') ?: today()->format('Y-m')).'-01');
        $n = $this->generateFor($calc, $period, $request->user()->id);

        return back()->with('success', __(":n ta xodim uchun ish haqi hisoblandi", ['n' => $n]));
    }

    /** Qoralamalarni yaratish (qo'lda ham, payroll:draft buyrug'i ham shu yerdan foydalanadi). */
    public function generateFor(PayrollCalculator $calc, Carbon $period, ?int $userId): int
    {
        $schemes = tenant()->hasFeature('pay_schemes');
        $periodData = $schemes ? app(SchemePayroll::class)->period($period) : null;
        $n = 0;
        foreach (Employee::active()->get() as $e) {
            if (Payslip::where('employee_id', $e->id)->whereDate('period', $period)->exists()) {
                continue;
            }
            $c = $calc->calculate($e, $period, $periodData, true);
            if ($c['total'] <= 0 && $c['base_amount'] <= 0 && ($c["advance"] ?? 0) <= 0) {
                continue;
            }
            Payslip::create($c + ['tenant_id' => tenant()->id, 'employee_id' => $e->id, 'period' => $period->toDateString(), 'paid' => 0,
                'status' => $schemes ? 'draft' : 'approved', 'created_by' => $userId]);
            $n++;
        }

        return $n;
    }

    public function recalc(Payslip $payslip, PayrollCalculator $calc)
    {
        if ($payslip->paid > 0) {
            return back()->with('error', "To'lov boshlangan varaqani qayta hisoblab bo'lmaydi");
        }
        if (tenant()->hasFeature('pay_schemes')) {
            if ($payslip->status !== 'draft') {
                return back()->with('error', "Tasdiqlangan varaqani avval qayta oching");
            }
            $payslip->update($calc->calculate($payslip->employee, $payslip->period, null, true));

            return back()->with('success', 'Qayta hisoblandi');
        }
        $c = $calc->calculate($payslip->employee, $payslip->period);
        $payslip->update(array_diff_key($c, ['detail' => 1, 'bonus' => 1, 'advance' => 1]) + ['total' => max(0, $c['total'] + $payslip->bonus)]);

        return back()->with('success', 'Qayta hisoblandi');
    }

    public function adjust(Request $request, Payslip $payslip)
    {
        $data = $request->validate(['bonus' => 'nullable|integer|min:0', 'penalty' => 'nullable|integer|min:0', 'note' => 'nullable|string|max:200']);
        $bonus = (int) ($data['bonus'] ?? 0);
        $penalty = (int) ($data['penalty'] ?? 0);
        $total = max(0, $payslip->base_amount + $payslip->kpi_amount + $bonus - $penalty);
        if ($total < $payslip->paid) {
            return back()->with('error', "Yangi summa allaqachon to'langan summadan kam bo'lishi mumkin emas");
        }
        $payslip->update(['bonus' => $bonus, 'penalty' => $penalty, 'total' => $total, 'note' => $data['note'] ?? null, 'status' => $payslip->paid >= $total && $total > 0 ? 'paid' : 'approved']);

        return back()->with('success', 'Saqlandi');
    }

    public function pay(Request $request, Payslip $payslip, FinanceService $finance)
    {
        $data = $request->validate(['amount' => 'required|string', 'account' => 'required|in:cash,card,bank']);
        if ($payslip->status === 'draft') {
            return back()->with('error', "Avval direktor qoralamani tasdiqlashi kerak");
        }
        $amount = (int) preg_replace('/\D/', '', $data['amount']);
        if ($amount <= 0 || $amount > $payslip->remaining()) {
            return back()->with('error', "Summa noto'g'ri. Qolgan summa: ".money($payslip->remaining())." so'm");
        }
        try {
            DB::transaction(function () use ($finance, $payslip, $amount, $data, $request) {
                $locked = Payslip::lockForUpdate()->findOrFail($payslip->id);
                if ($amount > $locked->remaining()) {
                    throw new \DomainException("Qolgan summadan ko'p kiritildi");
                }
                $finance->record('salary', $data['account'], $amount, 'Ish haqi', $locked->employee->full_name.' — '.$locked->period->format('m.Y'), $request->user(), null, $locked->employee_id, $locked->id);
                $paid = $locked->paid + $amount;
                $locked->update(['paid' => $paid, 'status' => $paid >= $locked->total ? 'paid' : 'approved']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "To'landi: ".money($amount)." so'm");
    }

    public function approve(Request $request, Payslip $payslip)
    {
        if ($payslip->status !== 'draft') {
            return back()->with('error', 'Varaqa allaqachon tasdiqlangan');
        }
        $payslip->update(['status' => $payslip->total > 0 && $payslip->paid >= $payslip->total ? 'paid' : 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        AuditLog::record('payroll.approve', $payslip, ['total' => $payslip->total]);

        return back()->with('success', 'Ish haqi tasdiqlandi');
    }

    public function approveAll(Request $request)
    {
        $period = Carbon::parse(($request->input('month') ?: today()->format('Y-m')).'-01');
        $n = 0;
        foreach (Payslip::whereDate('period', $period)->where('status', 'draft')->get() as $slip) {
            $slip->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
            $n++;
        }
        AuditLog::record('payroll.approve_all', null, ['period' => $period->format('Y-m'), 'count' => $n]);

        return back()->with('success', __(":n ta varaqa tasdiqlandi", ['n' => $n]));
    }

    /** Tasdiqlangan varaqani qayta ochish (faqat to'lov boshlanmagan bo'lsa), jurnalga yoziladi. */
    public function reopen(Request $request, Payslip $payslip)
    {
        if ($payslip->paid > 0) {
            return back()->with('error', "To'lov boshlangan varaqani qayta ochib bo'lmaydi");
        }
        $payslip->update(['status' => 'draft', 'approved_at' => null, 'approved_by' => null]);
        AuditLog::record('payroll.reopen', $payslip, ['by' => $request->user()->name]);

        return back()->with('success', 'Varaqa qayta ochildi');
    }

    /** "Nega shu summa" — formula va raqamlar. */
    public function detail(Payslip $payslip)
    {
        return response()->json(['employee' => $payslip->employee->full_name, 'period' => $payslip->period->format('m.Y'), 'slip' => $payslip->only(['strategy', 'base_amount', 'kpi_amount', 'bonus', 'penalty', 'advance', 'total', 'worked_days', 'working_days', 'late_count', 'kids_count']),
            'detail' => $payslip->detail, 'items' => PayslipItem::where('employee_id', $payslip->employee_id)->whereDate('period', $payslip->period)->get(['type', 'amount', 'reason', 'source'])]);
    }

    public function itemStore(Request $request, PayrollCalculator $calc, FinanceService $finance)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer', 'month' => 'required|date_format:Y-m', 'type' => 'required|in:premium,event_bonus,fine,deduction,advance',
            'amount' => 'required|string', 'reason' => 'nullable|string|max:200', 'account' => 'nullable|in:cash,card,bank',
        ]);
        $emp = Employee::findOrFail($data['employee_id']);
        $period = Carbon::parse($data['month'].'-01');
        $amount = (int) preg_replace('/\D/', '', $data['amount']);
        abort_if($amount <= 0, 422);
        $slip = Payslip::where('employee_id', $emp->id)->whereDate('period', $period)->first();
        if ($slip && $slip->status !== 'draft') {
            return back()->with('error', "Varaqa tasdiqlangan: avval qayta oching");
        }
        $txId = null;
        if ($data['type'] === 'advance') {
            try {
                $tx = $finance->record('salary', $data['account'] ?? 'cash', $amount, 'Avans', $emp->full_name.' — '.$period->format('m.Y'), $request->user(), null, $emp->id);
            } catch (\DomainException $e) {
                return back()->with('error', $e->getMessage());
            }
            $txId = $tx->id;
        }
        PayslipItem::create(['tenant_id' => tenant()->id, 'employee_id' => $emp->id, 'period' => $period->toDateString(), 'type' => $data['type'], 'amount' => $amount,
            'reason' => $data['reason'] ?? null, 'source' => 'manual', 'finance_transaction_id' => $txId, 'created_by' => $request->user()->id]);
        $this->refreshSlip($calc, $emp, $period);

        return back()->with('success', 'Qator qo\'shildi');
    }

    /** Hammaga bayram premiyasi kabi ommaviy qo'shish. */
    public function itemBulk(Request $request, PayrollCalculator $calc)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'type' => 'required|in:premium,fine', 'amount' => 'required|string', 'reason' => 'required|string|max:200']);
        $period = Carbon::parse($data['month'].'-01');
        $amount = (int) preg_replace('/\D/', '', $data['amount']);
        abort_if($amount <= 0, 422);
        $n = 0;
        foreach (Employee::active()->get() as $emp) {
            $slip = Payslip::where('employee_id', $emp->id)->whereDate('period', $period)->first();
            if ($slip && $slip->status !== 'draft') {
                continue;
            }
            PayslipItem::create(['tenant_id' => tenant()->id, 'employee_id' => $emp->id, 'period' => $period->toDateString(), 'type' => $data['type'], 'amount' => $amount,
                'reason' => $data['reason'], 'source' => 'bulk', 'created_by' => $request->user()->id]);
            $this->refreshSlip($calc, $emp, $period);
            $n++;
        }

        return back()->with('success', __(":n ta xodimga qo'shildi", ['n' => $n]));
    }

    public function itemDestroy(PayslipItem $item, PayrollCalculator $calc)
    {
        if ($item->type === 'advance') {
            return back()->with('error', "Avans qatorini o'chirib bo'lmaydi: moliyada xarajat yozilgan. Tuzatish uchun jarima/ushlanma qo'shing.");
        }
        $slip = Payslip::where('employee_id', $item->employee_id)->whereDate('period', $item->period)->first();
        if ($slip && $slip->status !== 'draft') {
            return back()->with('error', "Varaqa tasdiqlangan: avval qayta oching");
        }
        $emp = $item->employee_id;
        $period = $item->period;
        $item->delete();
        $this->refreshSlip($calc, Employee::findOrFail($emp), $period);

        return back()->with('success', "Qator o'chirildi");
    }

    private function refreshSlip(PayrollCalculator $calc, Employee $emp, Carbon $period): void
    {
        $slip = Payslip::where('employee_id', $emp->id)->whereDate('period', $period)->where('status', 'draft')->first();
        $slip?->update($calc->calculate($emp, $period, null, false));
    }

    /* ---------------- Shikoyatlar (KPI: ota-onalar mamnun) ---------------- */

    public function complaints()
    {
        return view('app.payroll.complaints', [
            'rows' => Complaint::latest('date')->limit(100)->get(),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
            'groups' => Group::main()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function complaintStore(Request $request)
    {
        $data = $request->validate(['date' => 'required|date|before_or_equal:today', 'employee_id' => 'nullable|integer', 'group_id' => 'nullable|integer', 'note' => 'required|string|max:300']);
        abort_if(empty($data['employee_id']) && empty($data['group_id']), 422);
        Complaint::create($data + ['tenant_id' => tenant()->id, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Shikoyat qaydi saqlandi');
    }

    public function complaintDestroy(Complaint $complaint)
    {
        $complaint->delete();

        return back()->with('success', "Qayd o'chirildi");
    }

    /* ---------------- Sxemalar ---------------- */

    public function schemes()
    {
        return view('app.payroll.schemes', ['schemes' => PayScheme::all()]);
    }

    public function schemesUpdate(Request $request)
    {
        $keys = array_keys(config('kinderdesk.pay_schemes'));
        foreach ($keys as $key) {
            $in = $request->input("schemes.$key", []);
            $params = [
                'p' => max(0, min(100, (float) ($in['p'] ?? 0))), 'L' => max(0, (int) ($in['L'] ?? 0)), 'M' => max(1, (int) ($in['M'] ?? 5)),
                's' => max(0, (int) preg_replace('/\D/', '', (string) ($in['s'] ?? 0))), 'c' => max(0, (int) preg_replace('/\D/', '', (string) ($in['c'] ?? 0))),
            ];
            \App\Models\PayScheme::updateOrCreate(['key' => $key], ['tenant_id' => tenant()->id, 'params' => $params]);
        }
        AuditLog::record('payroll.schemes_update');

        return back()->with('success', 'Sxemalar saqlandi');
    }

    public function destroy(Payslip $payslip)
    {
        if ($payslip->paid > 0) {
            return back()->with('error', "To'lov boshlangan varaqani o'chirib bo'lmaydi");
        }
        $payslip->delete();

        return back()->with('success', "Varaqa o'chirildi");
    }
}
