<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Kid;
use App\Services\Finance\DiscountService;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function __construct(private DiscountService $discounts)
    {
    }

    public function index()
    {
        return view('app.families.index', [
            'suggestions' => $this->discounts->suggestions(),
            'families' => Family::with(['kids' => fn ($q) => $q->where('status', 'active')])->whereHas('kids')->latest('id')->get(),
        ]);
    }

    public function link(Request $request)
    {
        $data = $request->validate(['kids' => 'required|array|min:2', 'kids.*' => 'integer']);
        $ids = Kid::whereIn('id', $data['kids'])->pluck('id')->all();
        abort_if(count($ids) < 2, 422);
        $family = $this->discounts->linkFamily($ids, $request->user());

        return back()->with('status', __("Oila tasdiqlandi. Chegirma :date dan boshlanadi", ['date' => $family->effective_from->format('d.m.Y')]));
    }

    public function unlink(Request $request, Kid $kid)
    {
        $this->discounts->unlinkKid($kid);

        return back()->with('status', __('Bola oiladan chiqarildi'));
    }
}
