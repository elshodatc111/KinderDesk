<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubAttendance;
use App\Models\ClubKid;
use App\Models\Employee;
use App\Models\Kid;
use App\Services\Finance\ChargeService;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ClubController extends Controller
{
    public function __construct(private ChargeService $charges)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $clubs = Club::where('status', $status === 'archived' ? 'archived' : 'active')->with('teacher')->withCount('activeLinks')->orderBy('name')->get();

        return view('app.clubs.index', compact('clubs', 'status'));
    }

    public function create()
    {
        return view('app.clubs.form', ['club' => new Club(['billing' => 'monthly']), 'teachers' => Employee::active()->clubStaff()->orderBy('full_name')->get()]);
    }

    public function store(Request $request)
    {
        $club = Club::create($this->validated($request) + ['tenant_id' => tenant()->id, 'status' => 'active']);
        AuditLog::record('club.created', $club);

        return redirect()->route('app.clubs.show', $club)->with('success', "To'garak yaratildi");
    }

    public function show(Request $request, Club $club)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->min(today());
        $club->load('teacher');
        $links = $club->activeLinks()->with('kid')->get()->filter(fn ($l) => $l->kid)->sortBy(fn ($l) => $l->kid->full_name)->values();
        $marks = ClubAttendance::where('club_id', $club->id)->whereDate('date', $date)->pluck('status', 'kid_id');
        $enrolled = $links->pluck('kid_id');

        return view('app.clubs.show', [
            'club' => $club, 'links' => $links, 'date' => $date,
            'rows' => $links->map(fn ($l) => ['id' => $l->kid_id, 'name' => $l->kid->full_name, 'status' => $marks[$l->kid_id] ?? null]),
            'available' => Kid::active()->whereNotIn('id', $enrolled)->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function edit(Club $club)
    {
        return view('app.clubs.form', ['club' => $club, 'teachers' => Employee::active()->clubStaff()->orderBy('full_name')->get()]);
    }

    public function update(Request $request, Club $club)
    {
        $club->update($this->validated($request));

        return redirect()->route('app.clubs.show', $club)->with('success', 'Saqlandi');
    }

    public function archive(Club $club)
    {
        $club->update(['status' => $club->status === 'active' ? 'archived' : 'active']);
        if ($club->status === 'archived') {
            $club->activeLinks()->update(['ended_on' => today()]);
        }

        return redirect()->route('app.clubs.index')->with('success', $club->status === 'archived' ? "To'garak arxivlandi" : "To'garak qayta faollashtirildi");
    }

    public function enroll(Request $request, Club $club)
    {
        $data = $request->validate(['kid_id' => 'required|integer']);
        $kid = Kid::active()->findOrFail($data['kid_id']);
        if ($club->activeLinks()->where('kid_id', $kid->id)->exists()) {
            return back()->with('error', "Bola allaqachon shu to'garakda");
        }
        ClubKid::create(['tenant_id' => tenant()->id, 'club_id' => $club->id, 'kid_id' => $kid->id, 'started_on' => today()]);
        // Oylik to'garak: joriy oy uchun darhol hisoblanadi
        if ($club->status === 'active' && ! $club->isPerSession() && tenant()->setting('billing.auto_charge', true)) {
            $this->charges->chargeClubMonth($kid, $club, today()->startOfMonth());
        }
        Realtime::tenant(tenant()->id, 'club', ['club' => $club->id]);

        return back()->with('success', __(':kid to\'garakka yozildi', ['kid' => $kid->full_name]));
    }

    public function leave(Club $club, ClubKid $link)
    {
        abort_unless($link->club_id === $club->id, 404);
        $link->update(['ended_on' => today()]);

        return back()->with('success', "Bola to'garakdan chiqarildi");
    }

    /** To'garak davomadi (AJAX). Mashg'ulot uchun to'lov bo'lsa, kelgan kun uchun yechiladi. */
    public function attendance(Request $request, Club $club)
    {
        $data = $request->validate(['date' => 'required|date|before_or_equal:today', 'marks' => 'required|array', 'marks.*' => 'in:present,absent,sick']);
        $date = Carbon::parse($data['date']);
        if (! $date->isToday()) {
            return response()->json(['message' => __("Davomad faqat bugungi kun uchun belgilanadi. O'tgan kunlarni o'zgartirib bo'lmaydi.")], 422);
        }
        $valid = $club->activeLinks()->pluck('kid_id')->all();
        $saved = 0;
        foreach ($data['marks'] as $kidId => $status) {
            if (! in_array((int) $kidId, $valid, true)) {
                continue;
            }
            ClubAttendance::updateOrCreate(['club_id' => $club->id, 'kid_id' => $kidId, 'date' => $date->toDateString()],
                ['tenant_id' => tenant()->id, 'status' => $status, 'marked_by' => $request->user()->id]);
            if ($kid = Kid::find($kidId)) {
                $this->charges->syncClubSession($kid, $club, $date, $status);
            }
            $saved++;
        }
        Realtime::tenant(tenant()->id, 'attendance', ['club' => $club->id]);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'teacher_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', tenant()->id)->whereIn('role_id', \App\Models\Role::where('tenant_id', tenant()->id)->whereIn('key', Employee::CLUB_ROLES)->pluck('id')))], 'fee' => 'required|integer|min:0',
            'billing' => 'required|in:monthly,per_session', 'schedule' => 'nullable|string|max:120', 'description' => 'nullable|string|max:500',
        ]);
    }
}
