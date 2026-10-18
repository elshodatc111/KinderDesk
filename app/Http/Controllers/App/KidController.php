<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubKid;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Note;
use App\Services\Finance\ChargeService;
use App\Services\Finance\FinanceService;
use App\Services\Guardians\BlacklistService;
use App\Services\Guardians\GuardianService;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KidController extends Controller
{
    public function index(Request $request)
    {
        $q = Kid::with('currentLink.group');
        $status = $request->query('status', 'active');
        $q->where('status', $status === 'archived' ? 'archived' : 'active');
        if ($s = trim((string) $request->query('q'))) {
            $digits = preg_replace('/\D/', '', $s);
            $q->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('parent_name', 'like', "%$s%")
                ->when($digits, fn ($x) => $x->orWhere('parent_phone', 'like', "%$digits%")));
        }
        if ($g = $request->query('group')) {
            $q->whereHas('currentLink', fn ($x) => $x->where('group_id', $g));
        }
        if ($request->boolean('debt')) {
            $q->where('balance', '<', 0);
        }
        $kids = $q->orderBy('full_name')->paginate(20)->withQueryString();

        return view('app.kids.index', [
            'kids' => $kids, 'groups' => Group::active()->main()->orderBy('name')->get(), 'status' => $status,
            'totalDebt' => abs((int) Kid::active()->where('balance', '<', 0)->sum('balance')),
        ]);
    }

    public function create(Request $request)
    {
        return view('app.kids.form', ['kid' => new Kid(['gender' => 'male', 'enrolled_on' => today(), 'parent_name' => $request->query('name'), 'parent_phone' => $request->query('phone')]), 'groups' => Group::active()->main()->orderBy('name')->get()]);
    }

    /** @return array{0: array, 1: ?array} 1-vasiy (asosiy maydonlar) va 2-vasiy */
    private function guardianInput(Request $request, array $data): array
    {
        $first = ['relation' => $request->input('g1_relation', 'other'), 'name' => $data['parent_name'] ?? null, 'phone' => $data['parent_phone'], 'phone2' => $data['parent_phone2'] ?? null,
            'is_payer' => ! $request->boolean('g2_is_payer'), 'sms' => $request->boolean('g1_sms', true), 'pickup' => $request->boolean('g1_pickup', true)];
        $second = null;
        if (feature('guardians2') && filled($request->input('g2_phone'))) {
            $request->validate(['g2_name' => 'nullable|string|max:150', 'g2_relation' => 'nullable|in:father,mother,other', 'g2_phone' => 'required|string', 'g2_phone2' => 'nullable|string']);
            $p = normalize_phone($request->input('g2_phone'));
            if (! $p) {
                throw \Illuminate\Validation\ValidationException::withMessages(['g2_phone' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"]);
            }
            $second = ['relation' => $request->input('g2_relation', 'other'), 'name' => $request->input('g2_name'), 'phone' => $p,
                'phone2' => filled($request->input('g2_phone2')) ? normalize_phone($request->input('g2_phone2')) : null,
                'is_payer' => $request->boolean('g2_is_payer'), 'sms' => $request->boolean('g2_sms', true), 'pickup' => $request->boolean('g2_pickup', true)];
        }

        return [$first, $second];
    }

    private function guardPhones(array $data, ?array $second): array
    {
        return array_filter([$data['parent_phone'], $data['parent_phone2'] ?? null, $second['phone'] ?? null, $second['phone2'] ?? null]);
    }

    public function store(Request $request, ChargeService $charges, GuardianService $guardians, BlacklistService $blacklist)
    {
        $data = $this->validated($request);
        [$g1, $g2] = $this->guardianInput($request, $data);
        if ($stop = $blacklist->guard($request, $this->guardPhones($data, $g2), $data['certificate_no'] ?? null, 'family', $data['full_name'])) {
            return $stop;
        }
        $request->validate(['group_id' => 'required|integer']);
        $group = Group::active()->main()->findOrFail($request->input('group_id'));
        if ($group->isFull()) {
            return back()->withInput()->with('error', __("«:group» guruhi to'lgan (:n o'rin)", ['group' => $group->name, 'n' => $group->capacity]));
        }

        $kid = DB::transaction(function () use ($data, $group, $request) {
            $kid = Kid::create($data + ['tenant_id' => tenant()->id, 'status' => 'active', 'balance' => 0]);
            GroupKid::create(['tenant_id' => $kid->tenant_id, 'group_id' => $group->id, 'kid_id' => $kid->id,
                'started_on' => $data['enrolled_on'] ?? today(), 'started_by' => $request->user()->id]);

            return $kid;
        });
        $guardians->sync($kid, $g1, $g2);
        if ($leadId = (int) $request->input('lead_id')) {
            \App\Services\Leads\LeadFlow::converted($leadId, $kid->id);
        }
        $charges->chargeOnEnroll($kid->fresh());
        Realtime::tenant(tenant()->id, 'kid', ['kind' => 'created']);
        AuditLog::record('kid.created', $kid);

        return redirect()->route('app.kids.show', $kid)->with('success', __(':kid qabul qilindi', ['kid' => $kid->full_name]));
    }

    public function show(Request $request, Kid $kid, FinanceService $finance)
    {
        $kid->load('health', 'guardians', 'currentLink.group', 'links.group', 'notes.author', 'extraLinks.group', 'clubLinks.club');
        $extraGroups = feature('extra_groups') ? Group::active()->where('type', '!=', 'main')->whereNotIn('id', $kid->extraLinks->pluck('group_id'))->orderBy('name')->get() : collect();
        $clubs = feature('clubs') ? Club::active()->whereNotIn('id', $kid->clubLinks->pluck('club_id'))->orderBy('name')->get() : collect();
        $month = today()->startOfMonth();
        $att = KidAttendance::where('kid_id', $kid->id)->whereDate('date', '>=', $month)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('app.kids.show', [
            'kid' => $kid,
            'ledger' => $kid->ledger()->with('group')->limit(40)->get(),
            'groups' => Group::active()->main()->orderBy('name')->get(),
            'extraGroups' => $extraGroups, 'clubs' => $clubs,
            'att' => $att,
            'accounts' => $finance->enabledAccounts(),
            'pending' => $kid->ledger()->where('status', 'pending')->sum('amount'),
            'tab' => $request->query('tab', 'finance'),
        ]);
    }

    public function edit(Kid $kid)
    {
        return view('app.kids.form', ['kid' => $kid, 'groups' => Group::active()->main()->orderBy('name')->get()]);
    }

    public function update(Request $request, Kid $kid, GuardianService $guardians, BlacklistService $blacklist)
    {
        $data = $this->validated($request, $kid);
        [$g1, $g2] = $this->guardianInput($request, $data);
        if ($stop = $blacklist->guard($request, $this->guardPhones($data, $g2), $data['certificate_no'] ?? null, 'family', $data['full_name'])) {
            return $stop;
        }
        $kid->update($data);
        $guardians->sync($kid, $g1, $g2);

        return redirect()->route('app.kids.show', $kid)->with('success', "Ma'lumotlar saqlandi");
    }

    private function validated(Request $request, ?Kid $kid = null): array
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date|before:today',
            'certificate_no' => 'nullable|string|max:30',
            'parent_name' => 'nullable|string|max:150',
            'parent_phone' => 'required|string',
            'parent_phone2' => 'nullable|string',
            'address' => 'nullable|string|max:200',
            'custom_fee' => 'nullable|integer|min:0',
            'enrolled_on' => 'nullable|date',
            'note' => 'nullable|string|max:500',
        ], ['birth_date.before' => "Tug'ilgan sana bugundan oldin bo'lishi kerak"]);

        $phone = normalize_phone($data['parent_phone']);
        if (! $phone) {
            throw \Illuminate\Validation\ValidationException::withMessages(['parent_phone' => "Telefon raqam noto'g'ri. Format: 90 123 45 67"]);
        }
        $data['parent_phone'] = $phone;
        $data['parent_phone2'] = ! empty($data['parent_phone2']) ? normalize_phone($data['parent_phone2']) : null;
        $data['custom_fee'] = ($data['custom_fee'] ?? '') === '' ? null : (int) $data['custom_fee'];
        if (! empty($data['certificate_no'])) {
            $dup = Kid::where('certificate_no', $data['certificate_no'])->when($kid, fn ($q) => $q->where('id', '!=', $kid->id))->exists();
            if ($dup) {
                throw \Illuminate\Validation\ValidationException::withMessages(['certificate_no' => "Bu guvohnoma raqami boshqa bolada mavjud"]);
            }
        } else {
            $data['certificate_no'] = null;
        }

        return $data;
    }

    /* ---------- Amallar ---------- */

    public function transfer(Request $request, Kid $kid, ChargeService $charges)
    {
        $data = $request->validate(['group_id' => 'required|integer', 'date' => 'nullable|date']);
        $group = Group::active()->main()->findOrFail($data['group_id']);
        $current = $kid->currentLink;
        if ($current && $current->group_id === $group->id) {
            return back()->with('error', "Bola allaqachon shu guruhda");
        }
        if ($group->isFull()) {
            return back()->with('error', __('«:group» guruhi to\'lgan', ['group' => $group->name]));
        }
        $on = $data['date'] ?? today()->toDateString();
        DB::transaction(function () use ($kid, $group, $current, $on, $request) {
            $current?->update(['ended_on' => $on, 'ended_by' => $request->user()->id]);
            GroupKid::create(['tenant_id' => $kid->tenant_id, 'group_id' => $group->id, 'kid_id' => $kid->id, 'started_on' => $on, 'started_by' => $request->user()->id]);
        });
        AuditLog::record('kid.transferred', $kid, ['group' => $group->id]);

        return back()->with('success', __('«:group» guruhiga o\'tkazildi', ['group' => $group->name]));
    }

    /** Bolani qo'shimcha (shanba/kechki) guruhga yoki to'garakka yozish. */
    public function addExtra(Request $request, Kid $kid, ChargeService $charges)
    {
        $data = $request->validate(['target' => 'required|string']); // g:ID yoki c:ID
        [$kind, $id] = array_pad(explode(':', $data['target'], 2), 2, null);
        abort_unless($kid->status === 'active' && in_array($kind, ['g', 'c'], true) && $id, 422);

        if ($kind === 'g') {
            abort_unless(feature('extra_groups'), 403);
            $group = Group::active()->where('type', '!=', 'main')->findOrFail($id);
            if ($group->isFull()) {
                return back()->with('error', __('«:group» guruhi to\'lgan', ['group' => $group->name]));
            }
            GroupKid::firstOrCreate(['group_id' => $group->id, 'kid_id' => $kid->id, 'ended_on' => null],
                ['tenant_id' => tenant()->id, 'started_on' => today(), 'started_by' => $request->user()->id]);
            $msg = __("«:name» guruhiga yozildi", ['name' => $group->name]);
        } else {
            abort_unless(feature('clubs'), 403);
            $club = Club::active()->findOrFail($id);
            if (! $club->activeLinks()->where('kid_id', $kid->id)->exists()) {
                ClubKid::create(['tenant_id' => tenant()->id, 'club_id' => $club->id, 'kid_id' => $kid->id, 'started_on' => today()]);
                if (! $club->isPerSession() && tenant()->setting('billing.auto_charge', true)) {
                    $charges->chargeClubMonth($kid, $club, today()->startOfMonth());
                }
            }
            $msg = __("«:name» to'garagiga yozildi", ['name' => $club->name]);
        }

        return back()->with('success', $msg);
    }

    public function archive(Request $request, Kid $kid)
    {
        DB::transaction(function () use ($kid, $request) {
            GroupKid::where('kid_id', $kid->id)->whereNull('ended_on')->update(['ended_on' => today(), 'ended_by' => $request->user()->id]);
            ClubKid::where('kid_id', $kid->id)->whereNull('ended_on')->update(['ended_on' => today()]);
            $kid->update(['status' => 'archived', 'archived_on' => today()]);
        });
        AuditLog::record('kid.archived', $kid);

        return redirect()->route('app.kids.index')->with('success', __(':kid arxivga o\'tkazildi', ['kid' => $kid->full_name]));
    }

    public function restore(Request $request, Kid $kid, ChargeService $charges)
    {
        $data = $request->validate(['group_id' => 'required|integer']);
        $group = Group::active()->main()->findOrFail($data['group_id']);
        DB::transaction(function () use ($kid, $group, $request) {
            $kid->update(['status' => 'active', 'archived_on' => null]);
            GroupKid::create(['tenant_id' => $kid->tenant_id, 'group_id' => $group->id, 'kid_id' => $kid->id, 'started_on' => today(), 'started_by' => $request->user()->id]);
        });
        $charges->chargeOnEnroll($kid->fresh());

        return redirect()->route('app.kids.show', $kid)->with('success', 'Qayta qabul qilindi');
    }

    public function note(Request $request, Kid $kid)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $kid->notes()->create(['tenant_id' => tenant()->id, 'body' => $data['body'], 'user_id' => $request->user()->id]);

        return redirect()->route('app.kids.show', [$kid, 'tab' => 'notes'])->with('success', 'Izoh qo\'shildi');
    }

    public function noteDestroy(Kid $kid, Note $note)
    {
        abort_unless($note->noteable_id === $kid->id && $note->noteable_type === Kid::class, 404);
        $note->delete();

        return back()->with('success', "Izoh o'chirildi");
    }
}
