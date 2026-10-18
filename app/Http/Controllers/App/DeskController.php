<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DeskHandover;
use App\Models\FinanceTransaction;
use App\Models\Kid;
use App\Services\Finance\ChargeService;
use App\Services\Finance\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Kassa: bog'chaning kunlik tushumi. Ota-onalardan to'lov shu yerga tushadi, so'ng direktorga topshiriladi. */
class DeskController extends Controller
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'today');
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', today()->toDateString()));

        $base = FinanceTransaction::where('wallet', 'desk');
        $todayIn = (int) (clone $base)->where('status', 'confirmed')->where('type', 'kid_payment')->whereDate('occurred_on', today())->sum('amount');
        $balances = $this->finance->balances('desk');
        $held = DeskHandover::where('status', 'pending')->selectRaw('account, sum(amount) s')->groupBy('account')->pluck('s', 'account');

        $journal = collect();
        if ($tab === 'journal') {
            $journal = (clone $base)->with('kid', 'creator')->where('status', '!=', 'canceled')
                ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->latest('id')->paginate(30)->withQueryString();
        }

        return view('app.desk.index', [
            'tab' => $tab, 'from' => $from, 'to' => $to,
            'balances' => $balances, 'held' => $held,
            'todayIn' => $todayIn,
            'todayList' => (clone $base)->with('kid', 'creator')->whereDate('occurred_on', today())->where('status', '!=', 'canceled')->latest('id')->get(),
            'pending' => (clone $base)->with('kid', 'creator')->where('status', 'pending')->latest('id')->get(),
            'handovers' => DeskHandover::with('creator', 'reviewer')->latest('id')->limit(30)->get(),
            'journal' => $journal,
            'accounts' => $this->finance->enabledAccounts(),
            'available' => collect(['cash', 'card', 'bank'])->mapWithKeys(fn ($a) => [$a => $this->finance->deskAvailable($a)]),
        ]);
    }

    /** To'lov qabul qilish uchun bola qidirish (JSON). */
    public function kids(Request $request): JsonResponse
    {
        $s = trim((string) $request->query('q'));
        if (mb_strlen($s) < 2) {
            return response()->json([]);
        }
        $digits = preg_replace('/\D/', '', $s);
        $kids = Kid::active()->with('currentLink.group')
            ->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('parent_name', 'like', "%$s%")
                ->when($digits, fn ($x) => $x->orWhere('parent_phone', 'like', "%$digits%")))
            ->orderBy('full_name')->limit(8)->get();

        return response()->json($kids->map(fn ($k) => [
            'id' => $k->id, 'name' => $k->full_name, 'group' => $k->currentLink?->group?->name,
            'balance' => $k->balance, 'phone' => format_phone($k->parent_phone), 'url' => route('app.kids.payment', $k),
        ]));
    }

    public function expense(Request $request)
    {
        // Kassadan xarajat FAQAT naqd pul bilan. Karta/bank tushumi direktorga topshiriladi.
        $data = $request->validate(['amount' => 'required|string', 'category' => 'nullable|string|max:80', 'description' => 'nullable|string|max:200']);
        try {
            $this->finance->record('expense', 'cash', (int) preg_replace('/\D/', '', $data['amount']), $data['category'] ?? null, $data['description'] ?? null, $request->user(), null, null, null, 'desk');
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.desk.index')->with('success', 'Kassa xarajati yozildi');
    }

    public function handover(Request $request)
    {
        $data = $request->validate(['account' => 'required|in:cash,card,bank', 'amount' => 'required|string', 'note' => 'nullable|string|max:200']);
        try {
            $this->finance->requestHandover($data['account'], (int) preg_replace('/\D/', '', $data['amount']), $data['note'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('app.desk.index', ['tab' => 'handovers'])->with('success', "Topshirish yuborildi. Direktor tasdiqlaganda pul moliyaga o'tadi.");
    }

    public function confirm(Request $request, FinanceTransaction $transaction)
    {
        abort_unless($transaction->wallet === 'desk', 404);
        try {
            $this->finance->confirm($transaction, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "To'lov tasdiqlandi");
    }

    public function cancel(Request $request, FinanceTransaction $transaction)
    {
        abort_unless($transaction->wallet === 'desk', 404);
        try {
            $this->finance->cancelPending($transaction, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "To'lov bekor qilindi");
    }

    /** Joriy davr uchun oylik to'lovlarni hozir hisoblash. */
    public function charge(ChargeService $charges)
    {
        $r = $charges->runForTenant(tenant(), null, true);

        return back()->with('success', __('Hisoblandi: :charged ta. Oldin hisoblanganlar o\'tkazib yuborildi: :skipped.', ['charged' => $r['charged'], 'skipped' => $r['skipped']]));
    }
}
