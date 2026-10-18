<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidHealth;
use App\Models\KidPickup;
use Illuminate\Http\Request;

/** Sog'liq va xavfsizlik: barcha xodimlar ko'radi, tahrirlash — kids.manage. Hech qachon AI ga yuborilmaydi. */
class HealthController extends Controller
{
    public function index(Request $request)
    {
        $groupId = (int) $request->query('group');
        $kids = Kid::active()->with(['health', 'currentLink.group'])
            ->when($groupId, fn ($q) => $q->whereIn('id', GroupKid::where('group_id', $groupId)->whereNull('ended_on')->pluck('kid_id')))
            ->when($request->query('q'), fn ($q, $s) => $q->where('full_name', 'like', "%$s%"))
            ->orderBy('full_name')->get();
        $only = $request->query('all') !== '1';
        if ($only) {
            $kids = $kids->filter(fn ($k) => $k->health && $k->health->has_alert)->values();
        }

        return view('app.health.index', ['kids' => $kids, 'groups' => Group::active()->orderBy('name')->get(), 'groupId' => $groupId, 'only' => $only]);
    }

    public function show(Kid $kid)
    {
        $kid->load('health', 'pickups', 'currentLink.group');

        return view('app.health.show', ['kid' => $kid, 'h' => $kid->health]);
    }

    public function update(Request $request, Kid $kid)
    {
        $d = $request->validate([
            'allergies' => 'nullable|string|max:1000', 'conditions' => 'nullable|string|max:1000', 'medications' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000', 'doctor_name' => 'nullable|string|max:120', 'doctor_phone' => 'nullable|string|max:30',
        ]);
        $d = array_map(fn ($v) => ($v === null || trim($v) === '') ? null : trim($v), $d);
        $d['has_alert'] = filled($d['allergies']) || filled($d['conditions']);
        $d['updated_by'] = $request->user()->id;
        KidHealth::updateOrCreate(['kid_id' => $kid->id], $d + ['tenant_id' => $kid->tenant_id]);
        \App\Models\AuditLog::record('kid.health.updated', $kid);

        return back()->with('success', 'Saqlandi');
    }

    public function pickupStore(Request $request, Kid $kid)
    {
        $d = $request->validate(['name' => 'required|string|max:120', 'relation' => 'nullable|string|max:60', 'phone' => 'nullable|string|max:30']);
        KidPickup::create($d + ['kid_id' => $kid->id, 'tenant_id' => $kid->tenant_id]);

        return back()->with('success', "Qo'shildi");
    }

    public function pickupDestroy(Kid $kid, KidPickup $pickup)
    {
        abort_unless($pickup->kid_id === $kid->id, 404);
        $pickup->delete();

        return back()->with('success', "O'chirildi");
    }
}
