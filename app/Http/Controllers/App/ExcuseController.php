<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use App\Models\KidExcuse;
use App\Services\Finance\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExcuseController extends Controller
{
    public function __construct(private DiscountService $discounts)
    {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('discount.enter') || $request->user()->can('discount.approve'), 403);
        $status = $request->query('status', 'pending');

        return view('app.excuses.index', [
            'status' => $status,
            'rows' => KidExcuse::with('kid')->when($status !== 'all', fn ($q) => $q->where('status', $status))->latest('id')->limit(200)->get(),
            'kids' => Kid::active()->orderBy('full_name')->get(['id', 'full_name']),
            'pendingCount' => KidExcuse::where('status', 'pending')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kid_id' => 'required|integer', 'month' => 'required|date_format:Y-m', 'days' => 'required|integer|min:1|max:31', 'reason' => 'required|string|max:200',
        ]);
        $kid = Kid::findOrFail($data['kid_id']);
        $this->discounts->enterExcuse($kid, Carbon::parse($data['month'].'-01'), (int) $data['days'], $data['reason'], $request->user());

        return back()->with('status', __("Chegirma so'rovi direktorga yuborildi"));
    }

    public function approve(Request $request, KidExcuse $excuse)
    {
        $this->discounts->approveExcuse($excuse, $request->user());

        return back()->with('status', __('Chegirma tasdiqlandi'));
    }

    public function reject(Request $request, KidExcuse $excuse)
    {
        $this->discounts->rejectExcuse($excuse, $request->user());

        return back()->with('status', __('Chegirma rad etildi'));
    }
}
