<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Edition;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans', [
            'editions' => Edition::orderBy('sort')->with('plans')->get(),
            'features' => config('kinderdesk.features'),
        ]);
    }

    public function updateEdition(Request $request, Edition $edition)
    {
        $data = $request->validate([
            'tagline' => 'required|string|max:160',
            'is_active' => 'nullable|boolean',
            'features' => 'array',
            'features.*' => 'string',
            'plans' => 'array',
            'plans.*.price' => 'required|integer|min:0',
            'plans.*.is_active' => 'nullable|boolean',
        ]);

        $core = collect(config('kinderdesk.features'))->filter(fn ($f) => ! empty($f['core']))->keys()->all();
        $valid = array_keys(config('kinderdesk.features'));
        $features = array_values(array_unique([...$core, ...array_intersect($data['features'] ?? [], $valid)]));

        $edition->update(['tagline' => $data['tagline'], 'is_active' => $request->boolean('is_active'), 'features' => $features]);
        foreach ($data['plans'] ?? [] as $id => $p) {
            Plan::where('edition_id', $edition->id)->where('id', $id)->update(['price' => (int) $p['price'], 'is_active' => ! empty($p['is_active'])]);
        }
        AuditLog::record('edition.updated', $edition, ['features' => $features]);

        return back()->with('success', __('«:edition» nashri saqlandi. Yangi narxlar yangi xaridlarga amal qiladi.', ['edition' => $edition->name]));
    }
}
