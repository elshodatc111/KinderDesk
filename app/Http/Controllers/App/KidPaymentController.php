<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;

class KidPaymentController extends Controller
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function payment(Request $request, Kid $kid)
    {
        $data = $request->validate(['amount' => 'required|string', 'account' => 'required|in:cash,card,bank', 'description' => 'nullable|string|max:200', 'date' => 'nullable|date|before_or_equal:today']);
        try {
            $tx = $this->finance->receivePayment($kid, $this->amount($data['amount']), $data['account'], $data['description'] ?? null, $request->user(), isset($data['date']) ? \Illuminate\Support\Carbon::parse($data['date']) : null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $tx->status === 'pending' ? "To'lov qabul qilindi va tasdiqlashni kutmoqda" : "To'lov qabul qilindi: ".money($tx->amount)." so'm");
    }

    public function refund(Request $request, Kid $kid)
    {
        $data = $request->validate(['amount' => 'required|string', 'account' => 'required|in:cash,card,bank', 'description' => 'nullable|string|max:200']);
        try {
            $this->finance->refund($kid, $this->amount($data['amount']), $data['account'], $data['description'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "To'lov qaytarildi");
    }

    public function adjust(Request $request, Kid $kid)
    {
        $data = $request->validate(['amount' => 'required|string', 'direction' => 'required|in:credit,debit', 'description' => 'required|string|max:200']);
        $amount = $this->amount($data['amount']);
        $this->finance->adjust($kid, $data['direction'] === 'credit' ? $amount : -$amount, $data['description'], $request->user());

        return back()->with('success', 'Hisob tuzatildi');
    }

    private function amount(string $raw): int
    {
        return (int) preg_replace('/\D/', '', $raw);
    }
}
