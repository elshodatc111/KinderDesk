<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Topup;
use App\Services\Billing\TopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TopupController extends Controller
{
    public function __construct(private TopupService $topups)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $items = Topup::with('tenant', 'creator')->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status='pending' then 0 else 1 end")->latest('id')->paginate(20)->withQueryString();

        return view('admin.topups', ['items' => $items, 'status' => $status, 'pending' => Topup::where('status', 'pending')->count()]);
    }

    public function approve(Request $request, Topup $topup)
    {
        try {
            $this->topups->approve($topup, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __("Tasdiqlandi: :sum so'm bog'cha balansiga tushdi", ['sum' => money($topup->amount)]));
    }

    public function reject(Request $request, Topup $topup)
    {
        $data = $request->validate(['reason' => 'required|string|max:300']);
        try {
            $this->topups->reject($topup, $request->user(), $data['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rad etildi');
    }

    public function receipt(Topup $topup)
    {
        $path = $this->topups->receiptUrl($topup);
        abort_unless($path, 404);

        return Storage::disk('local')->response($path);
    }
}
