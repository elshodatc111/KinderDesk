<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Services\Attendance\AttendanceStats;
use App\Services\Finance\ChargeService;
use App\Services\Sms\SmsService;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    /* ---------- Bolalar davomadi ---------- */

    /** Guruhlar ro'yxati (tarbiyachi faqat o'z guruhlarini ko'radi). */
    private function visibleGroups(Request $request)
    {
        $user = $request->user();
        $groups = Group::active()->when(! feature('extra_groups'), fn ($q) => $q->where('type', 'main'))->orderByRaw("case type when 'main' then 0 when 'evening' then 1 else 2 end")->orderBy('name')->get();
        $emp = Employee::where('user_id', $user->id)->first();
        if ($emp && ! $user->can('groups.manage')) {
            $mine = $emp->activeGroupLinks()->pluck('group_id');
            if ($mine->isNotEmpty()) {
                $groups = $groups->whereIn('id', $mine)->values();
            }
        }

        return $groups;
    }

    /**
     * Bugun: guruh kartalari (+ umumiy natija). Guruh tanlansa — belgilash ro'yxati.
     * Davomad FAQAT bugungi kun uchun belgilanadi; o'tgan kunlar faqat ko'rish uchun (kalendar orqali).
     */
    public function index(Request $request, AttendanceStats $stats)
    {
        $groups = $this->visibleGroups($request);
        $view = $request->query('view') === 'calendar' ? 'calendar' : 'today';
        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->min(today()) : today();
        $group = $request->filled('group') ? $groups->firstWhere('id', (int) $request->query('group')) : null;

        // ----- Bitta guruh: belgilash (bugun) yoki ko'rish (o'tgan kun) -----
        if ($group) {
            $wrongDay = $group->type === 'saturday' && ! $date->isSaturday();
            $rows = collect();
            if (! $wrongDay) {
                $kids = GroupKid::with('kid.health')->where('group_id', $group->id)
                    ->whereDate('started_on', '<=', $date)->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))->get()->pluck('kid')->filter()->sortBy('full_name')->values();
                $marks = KidAttendance::where('group_id', $group->id)->whereDate('date', $date)->pluck('status', 'kid_id');
                $rows = $kids->map(fn ($k) => ['id' => $k->id, 'name' => $k->full_name, 'status' => $marks[$k->id] ?? null, 'alert' => feature('health') && (bool) $k->health?->has_alert, 'alert_text' => feature('health') ? (string) ($k->health?->allergies ?: $k->health?->conditions) : '']);
            }
            $isToday = $date->isToday();
            $available = ($isToday && $group->isDaily() && $request->user()->can('attendance.kids'))
                ? Kid::active()->whereNotIn('id', $rows->pluck('id'))->orderBy('full_name')->get(['id', 'full_name']) : collect();

            return view('app.attendance.group', [
                'groups' => $groups, 'group' => $group, 'date' => $date, 'rows' => $rows, 'wrongDay' => $wrongDay, 'available' => $available,
                'locked' => ! $isToday, 'day' => $stats->day($date, collect([$group]))['groups']->first(),
            ]);
        }

        // ----- Bugun / kalendar -----
        $month = $request->filled('month') ? Carbon::parse($request->query('month').'-01') : today()->startOfMonth();
        $month = $month->min(today()->startOfMonth());
        $data = [
            'groups' => $groups, 'view' => $view, 'date' => $date, 'isToday' => $date->isToday(),
            'day' => $stats->day($date, $groups), 'month' => $month,
        ];
        if ($view === 'calendar') {
            $cal = $stats->month($month);
            $marked = collect($cal)->filter(fn ($d) => $d['working'] && ! $d['future'] && $d['marked']);
            $data['cal'] = $cal;
            $data['monthSummary'] = [
                'days' => $marked->count(),
                'avg' => $marked->isEmpty() ? null : round($marked->avg(fn ($d) => $d['rate'] ?? 0)),
                'absent' => $marked->sum('absent'), 'sick' => $marked->sum('sick'),
            ];
            $data['selected'] = $request->filled('date') ? $date : null;
            $data['selectedDay'] = $request->filled('date') ? $stats->day($date, $groups) : null;
        }

        return view('app.attendance.index', $data);
    }

    /** Shanba/kechki guruhga bolani qo'shish (bolalar doimiy kelmaydi). */
    public function attach(Request $request)
    {
        $data = $request->validate(['group_id' => 'required|integer', 'kid_id' => 'required|integer', 'date' => 'nullable|date']);
        $group = Group::active()->where('type', '!=', 'main')->findOrFail($data['group_id']);
        $kid = Kid::active()->findOrFail($data['kid_id']);
        \App\Models\GroupKid::firstOrCreate(['group_id' => $group->id, 'kid_id' => $kid->id, 'ended_on' => null],
            ['tenant_id' => tenant()->id, 'started_on' => today(), 'started_by' => $request->user()->id]);

        return redirect()->route('app.attendance.index', array_filter(['group' => $group->id, 'date' => $data['date'] ?? null]))->with('success', __(':kid «:group» ro\'yxatiga qo\'shildi', ['kid' => $kid->full_name, 'group' => $group->name]));
    }

    public function store(Request $request, SmsService $sms, ChargeService $charges)
    {
        $data = $request->validate([
            'group_id' => 'required|integer', 'date' => 'required|date|before_or_equal:today',
            'marks' => 'required|array', 'marks.*' => 'in:present,absent,sick',
        ]);
        $group = Group::findOrFail($data['group_id']);
        $date = Carbon::parse($data['date']);
        if (! $date->isToday()) {
            return response()->json(['message' => __("Davomad faqat bugungi kun uchun belgilanadi. O'tgan kunlarni o'zgartirib bo'lmaydi.")], 422);
        }
        if ($group->type === 'saturday' && ! $date->isSaturday()) {
            return response()->json(['message' => "Shanba guruhi davomadi faqat shanba kuni belgilanadi"], 422);
        }

        $valid = GroupKid::where('group_id', $group->id)->whereDate('started_on', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))->pluck('kid_id')->all();
        $existing = KidAttendance::where('group_id', $group->id)->whereDate('date', $date)->whereIn('kid_id', array_keys($data['marks']))->pluck('status', 'kid_id');
        $saved = 0;
        foreach ($data['marks'] as $kidId => $status) {
            if (! in_array((int) $kidId, $valid, true)) {
                continue;
            }
            KidAttendance::updateOrCreate(['kid_id' => $kidId, 'group_id' => $group->id, 'date' => $date->toDateString()],
                ['tenant_id' => tenant()->id, 'status' => $status, 'marked_by' => $request->user()->id]);
            $saved++;
            // Shanba/kechki guruh: kelgan kun uchun bolaning hisobidan yechiladi
            if ($group->isDaily() && ($kidModel = Kid::find($kidId))) {
                $charges->syncDaily($kidModel, $group, $date, $status);
            }
            // Telegram: keldi / kelmadi (sozlamada yoqilgan bo'lsa, holat o'zgargandagina)
            $prev = $existing[$kidId] ?? null;
            $changed = $status === 'present' ? $prev !== 'present' : in_array($prev, [null, 'present'], true);
            if ($changed && $date->isToday() && ($kidTg = Kid::find($kidId))) {
                try {
                    app(\App\Services\Telegram\TelegramNotifier::class)->attendance($kidTg, $status, $group->name);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            // Kelmadi bo'lsa — ota-onaga SMS (sozlamada yoqilgan bo'lsa, faqat bugun uchun va birinchi marta)
            if ($status === 'absent' && ($existing[$kidId] ?? null) !== 'absent' && $date->isToday()) {
                $kid = \App\Models\Kid::find($kidId);
                if ($kid) {
                    try {
                        $sms->sendTenant(tenant(), $kid->parent_phone, 'kid_absent', ['bola' => $kid->full_name]);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }
        Realtime::tenant(tenant()->id, 'attendance', ['group' => $group->id]);

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    /* ---------- Xodimlar davomadi ---------- */

    public function staff(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->min(today());
        $locked = ! $date->isToday();
        $employees = Employee::active()->orderBy('full_name')->get();
        $marks = EmployeeAttendance::whereDate('date', $date)->get()->keyBy('employee_id');
        $rows = $employees->map(fn ($e) => [
            'id' => $e->id, 'name' => $e->full_name, 'position' => $e->positionLabel(),
            'status' => $marks[$e->id]->status ?? null, 'check_in' => isset($marks[$e->id]) && $marks[$e->id]->check_in ? substr($marks[$e->id]->check_in, 0, 5) : '',
            'note' => $marks[$e->id]->note ?? '', 'excuse' => $marks[$e->id]->excuse ?? null,
        ]);

        return view('app.attendance.staff', ['date' => $date, 'locked' => $locked, 'rows' => $rows, 'lateAfter' => tenant()->setting('attendance.late_after', '09:00'),
            'canExcuse' => auth()->user()->can('attendance.excuse')]);
    }

    public function staffStore(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today', 'marks' => 'required|array',
            'marks.*.status' => 'required|in:present,late,absent,leave', 'marks.*.check_in' => 'nullable|date_format:H:i',
            'marks.*.note' => 'nullable|string|max:255', 'marks.*.excuse' => 'nullable|in:excused,unexcused',
        ]);
        // Kechikdi / kelmadi uchun izoh majburiy
        foreach ($data['marks'] as $empId => $m) {
            if (in_array($m['status'], ['late', 'absent'], true) && trim((string) ($m['note'] ?? '')) === '') {
                return response()->json(['message' => __("Kechikish yoki kelmaganlik sababini izohga yozing"), 'employee' => (int) $empId], 422);
            }
        }
        $canExcuse = $request->user()->can('attendance.excuse');
        $date = Carbon::parse($data['date']);
        if (! $date->isToday()) {
            return response()->json(['message' => __("Davomad faqat bugungi kun uchun belgilanadi. O'tgan kunlarni o'zgartirib bo'lmaydi.")], 422);
        }
        $lateAfter = tenant()->setting('attendance.late_after', '09:00');
        $ids = Employee::whereIn('id', array_keys($data['marks']))->pluck('id')->all();
        foreach ($data['marks'] as $empId => $m) {
            if (! in_array((int) $empId, $ids, true)) {
                continue;
            }
            $status = $m['status'];
            $in = $m['check_in'] ?? null;
            // Kelish vaqti kechikish chegarasidan keyin bo'lsa — avtomatik "kechikdi"
            if ($status === 'present' && $in && $in > $lateAfter) {
                $status = 'late';
            }
            $values = ['tenant_id' => tenant()->id, 'status' => $status, 'check_in' => in_array($status, ['present', 'late']) ? $in : null,
                'note' => in_array($status, ['late', 'absent', 'leave'], true) ? trim((string) ($m['note'] ?? '')) : null, 'marked_by' => $request->user()->id];
            // Sababli/sababsiz — faqat ruxsati bor administrator belgilaydi; holat o'zgarsa, belgi tozalanadi
            $existing = EmployeeAttendance::where('employee_id', $empId)->whereDate('date', $date)->first();
            if (! in_array($status, ['late', 'absent'], true)) {
                $values['excuse'] = null;
                $values['excuse_by'] = null;
            } elseif ($canExcuse && array_key_exists('excuse', $m)) {
                $values['excuse'] = $m['excuse'];
                $values['excuse_by'] = $m['excuse'] ? $request->user()->id : null;
            } elseif ($existing && $existing->status !== $status) {
                $values['excuse'] = null;
                $values['excuse_by'] = null;
            }
            EmployeeAttendance::updateOrCreate(['employee_id' => $empId, 'date' => $date->toDateString()], $values);
        }
        Realtime::tenant(tenant()->id, 'attendance', ['staff' => true]);

        return response()->json(['ok' => true]);
    }
}
