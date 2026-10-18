<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DeskHandover;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Kid;
use App\Models\Subsidy;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Moliya: direktorning hisobi — kassadan kelgan pul, xarajat, daromad, ish haqi. */
class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'overview');
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', today()->toDateString()));
        $range = [$from->toDateString(), $to->toDateString()];

        $main = fn () => FinanceTransaction::where('wallet', 'main')->where('status', 'confirmed')->whereBetween('occurred_on', $range);

        $q = FinanceTransaction::with('kid', 'creator')->where('wallet', 'main')->where('status', '!=', 'canceled')->whereBetween('occurred_on', $range);
        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }
        if ($request->filled('account')) {
            $q->where('account', $request->query('account'));
        }

        return view('app.finance.index', [
            'tab' => $tab, 'from' => $from, 'to' => $to,
            'balances' => $this->finance->balances('main'),
            'deskBalances' => $this->finance->balances('desk'),
            'accounts' => $this->finance->enabledAccounts(),
            'fromDesk' => (int) $main()->where('type', 'handover')->sum('amount'),
            'otherIncome' => (int) $main()->where('type', 'income')->sum('amount'),
            'expense' => abs((int) $main()->whereIn('type', ['expense', 'salary'])->sum('amount')),
            'handovers' => DeskHandover::with('creator', 'reviewer')->where('status', 'pending')->latest('id')->get(),
            'handoverHistory' => $tab === 'handovers' ? DeskHandover::with('creator', 'reviewer')->where('status', '!=', 'pending')->latest('id')->limit(30)->get() : collect(),
            'items' => $tab === 'journal' ? $q->latest('id')->paginate(30)->withQueryString() : collect(),
            'finAccounts' => $tab === 'accounts' ? FinanceAccount::orderByDesc('is_active')->orderBy('id')->get() : collect(),
            'subsidies' => $tab === 'subsidy' ? Subsidy::orderByDesc('period')->orderByDesc('id')->get() : collect(),
            'subsidyTotals' => $tab === 'subsidy' ? [
                'expected' => (int) Subsidy::where('status', 'expected')->sum('expected'),
                'received' => (int) Subsidy::where('status', 'received')->sum('received'),
                'received_month' => (int) Subsidy::where('status', 'received')->whereBetween('received_on', [today()->startOfMonth()->toDateString(), today()->toDateString()])->sum('received'),
            ] : [],
            'activeKids' => $tab === 'subsidy' ? Kid::active()->count() : 0,
            'deposits' => (int) $main()->where('type', 'deposit')->sum('amount'),
            'subsidyIn' => (int) $main()->where('type', 'subsidy')->sum('amount'),
            'toDesk' => abs((int) $main()->where('type', 'desk_fund')->sum('amount')),
            'categories' => FinanceTransaction::whereIn('type', ['expense', 'income'])->whereNotNull('category')->distinct()->pluck('category'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:expense,income,deposit', 'account' => 'required|in:cash,card,bank', 'amount' => 'required|string',
            'category' => 'nullable|string|max:80', 'description' => 'nullable|string|max:200', 'date' => 'nullable|date|before_or_equal:today',
        ]);
        try {
            $this->finance->record($data['type'], $data['account'], $this->amount($data['amount']), $data['category'] ?? null, $data['description'] ?? null, $request->user(), isset($data['date']) ? Carbon::parse($data['date']) : null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', ['expense' => 'Xarajat yozildi', 'income' => 'Daromad yozildi', 'deposit' => "Qo'shimcha mablag' kiritildi"][$data['type']]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate(['from' => 'required|in:cash,card,bank', 'to' => 'required|in:cash,card,bank', 'amount' => 'required|string', 'description' => 'nullable|string|max:200']);
        try {
            $this->finance->transfer($data['from'], $data['to'], $this->amount($data['amount']), $data['description'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "O'tkazma bajarildi");
    }

    /** Direktor kassaga naqd pul beradi. */
    public function fundDesk(Request $request)
    {
        $data = $request->validate(['amount' => 'required|string', 'note' => 'nullable|string|max:150']);
        try {
            $this->finance->fundDesk($this->amount($data['amount']), $data['note'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kassaga naqd pul berildi');
    }

    /* ----- Hisob raqamlari ----- */

    public function accountStore(Request $request)
    {
        $data = $request->validate(['kind' => 'required|in:card,bank', 'title' => 'required|string|max:80', 'number' => 'required|string|max:40', 'holder' => 'nullable|string|max:120', 'bank_name' => 'nullable|string|max:80']);
        FinanceAccount::create($data + ['tenant_id' => tenant()->id, 'is_active' => true]);

        return redirect()->route('app.finance.index', ['tab' => 'accounts'])->with('success', "Hisob raqami qo'shildi");
    }

    public function accountToggle(FinanceAccount $account)
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('success', $account->is_active ? 'Hisob yoqildi' : "Hisob o'chirildi");
    }

    public function accountDestroy(FinanceAccount $account)
    {
        $account->delete();

        return back()->with('success', "Hisob raqami o'chirildi");
    }

    /* ----- Subsidiya ----- */

    public function subsidyStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120', 'source' => 'nullable|string|max:120', 'period' => 'required|date_format:Y-m',
            'kids_count' => 'nullable|integer|min:0|max:100000', 'rate' => 'nullable|string', 'expected' => 'nullable|string', 'note' => 'nullable|string|max:255',
        ]);
        $kids = $data['kids_count'] ?? null;
        $rate = isset($data['rate']) ? $this->amount($data['rate']) : null;
        $expected = isset($data['expected']) && $data['expected'] !== '' ? $this->amount($data['expected']) : (int) ($kids * $rate);
        if ($expected <= 0) {
            return back()->withInput()->with('error', "Kutilayotgan summani yozing yoki bolalar soni va stavkani kiriting");
        }
        Subsidy::create([
            'tenant_id' => tenant()->id, 'title' => $data['title'], 'source' => $data['source'] ?? null, 'period' => Carbon::parse($data['period'].'-01'),
            'kids_count' => $kids, 'rate' => $rate ?: null, 'expected' => $expected, 'note' => $data['note'] ?? null, 'status' => 'expected', 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('app.finance.index', ['tab' => 'subsidy'])->with('success', 'Subsidiya hisoblandi');
    }

    public function subsidyReceive(Request $request, Subsidy $subsidy)
    {
        $data = $request->validate(['amount' => 'required|string', 'account' => 'required|in:cash,card,bank', 'date' => 'nullable|date|before_or_equal:today']);
        try {
            $this->finance->receiveSubsidy($subsidy, $this->amount($data['amount']), $data['account'], isset($data['date']) ? Carbon::parse($data['date']) : null, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Subsidiya qabul qilindi va moliyaga kirim qilindi");
    }

    public function subsidyCancel(Subsidy $subsidy)
    {
        abort_unless($subsidy->status === 'expected', 422);
        $subsidy->update(['status' => 'canceled']);

        return back()->with('success', 'Subsidiya bekor qilindi');
    }

    public function handoverConfirm(Request $request, DeskHandover $handover)
    {
        try {
            $this->finance->confirmHandover($handover, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pul qabul qilindi va moliyaga o'tkazildi");
    }

    public function handoverReject(Request $request, DeskHandover $handover)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        try {
            $this->finance->rejectHandover($handover, $request->user(), $data['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Topshirish rad etildi. Pul kassada qoladi.');
    }

    private function amount(string $raw): int
    {
        return (int) preg_replace('/\D/', '', $raw);
    }
}
