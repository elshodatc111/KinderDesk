<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BlacklistEntry;
use App\Models\Lead;
use App\Models\User;
use App\Services\Guardians\BlacklistService;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    private function statuses(): array
    {
        return array_values((array) tenant()->setting('leads.statuses'));
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'kid') === 'employee' ? 'employee' : 'kid';
        $statuses = $this->statuses();
        $all = Lead::where('type', $type)->latest('id')->get();
        $leads = $all->groupBy('status');
        $blocked = app(BlacklistService::class)->enabled()
            ? $all->filter(fn ($l) => app(BlacklistService::class)->check([$l->phone, $l->phone2], null, $type === 'kid' ? 'family' : 'staff')->isNotEmpty())->pluck('id')->all() : [];
        $funnel = $all->groupBy(fn ($l) => $l->source ?: '—')->map(fn ($g) => ['total' => $g->count(), 'won' => $g->where('result', 'won')->count(), 'lost' => $g->where('result', 'lost')->count()])->sortByDesc('total');
        $staleIds = $type === 'kid' ? \App\Services\Leads\LeadFlow::stale()->pluck('id')->all() : [];

        return view('app.leads.index', [
            'type' => $type, 'statuses' => $statuses, 'leads' => $leads, 'funnel' => $funnel, 'blocked' => $blocked, 'staleIds' => $staleIds,
            'staff' => User::where('tenant_id', tenant()->id)->orderBy('name')->get(['id', 'name']),
            'sources' => (array) tenant()->setting('leads.sources'),
            'formUrl' => url('/k/'.tenant()->public_key),
            'formEnabled' => (bool) tenant()->setting('leads.public_form', true),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:kid,employee', 'name' => 'required|string|max:150', 'phone' => 'required|string',
            'source' => 'nullable|string|max:60', 'note' => 'nullable|string|max:500',
            'next_call_on' => 'nullable|date', 'assigned_to' => 'nullable|integer',
        ]);
        $phone = normalize_phone($data['phone']);
        if (! $phone) {
            return back()->withInput()->with('error', "Telefon raqam noto'g'ri");
        }
        $data['phone'] = $phone;
        Lead::create($data + ['tenant_id' => tenant()->id, 'status' => $this->statuses()[0], 'result' => 'open', 'stage_changed_at' => now()]);

        return redirect()->route('app.leads.index', ['type' => $data['type']])->with('success', "Ariza qo'shildi");
    }

    public function move(Request $request, Lead $lead)
    {
        $statuses = $this->statuses();
        $data = $request->validate(['status' => 'required|string', 'lost_reason' => 'nullable|string|max:200', 'add_blacklist' => 'nullable|boolean']);
        abort_unless(in_array($data['status'], $statuses, true), 422);
        $count = count($statuses);
        $result = 'open';
        if ($count >= 3) {
            if ($data['status'] === $statuses[$count - 2]) {
                $result = 'won';
            } elseif ($data['status'] === $statuses[$count - 1]) {
                $result = 'lost';
            }
        }
        if ($result === 'lost' && mb_strlen(trim((string) ($data['lost_reason'] ?? ''))) < 3) {
            return $request->expectsJson() ? response()->json(['error' => 'reason'], 422) : back()->with('error', __("Rad etish sababini yozing"));
        }
        $changed = $lead->status !== $data['status'];
        $lead->update(['status' => $data['status'], 'result' => $result, 'lost_reason' => $result === 'lost' ? ($data['lost_reason'] ?? null) : null,
            'stage_changed_at' => $changed ? now() : $lead->stage_changed_at]);
        if ($result === 'lost' && $request->boolean('add_blacklist') && $lead->type === 'employee' && feature('blacklist') && $request->user()->can('blacklist.manage')) {
            BlacklistEntry::create(BlacklistService::build('staff', $lead->name, [$lead->phone, $lead->phone2], null, (string) $data['lost_reason'], $request->user()->id) + ['tenant_id' => tenant()->id]);
        }

        return $request->expectsJson() ? response()->json(['ok' => true, 'result' => $result]) : back()->with('success', 'Holat yangilandi');
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate(['note' => 'nullable|string|max:500', 'name' => 'required|string|max:150', 'next_call_on' => 'nullable|date', 'assigned_to' => 'nullable|integer']);
        $lead->update($data);

        return back()->with('success', 'Saqlandi');
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return back()->with('success', "Ariza o'chirildi");
    }
}
