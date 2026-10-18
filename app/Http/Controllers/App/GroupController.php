<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\Kid;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $groups = Group::where('status', $status === 'archived' ? 'archived' : 'active')
            ->when(! feature('extra_groups'), fn ($q) => $q->where('type', 'main'))
            ->withCount('activeKidLinks')->with('activeEmployeeLinks.employee')->orderByRaw("case type when 'main' then 0 when 'evening' then 1 else 2 end")->orderBy('name')->get();

        return view('app.groups.index', compact('groups', 'status'));
    }

    public function create()
    {
        return view('app.groups.form', ['group' => new Group(['capacity' => 20, 'type' => request('type', 'main'), 'start_time' => '09:00', 'end_time' => '17:00'])]);
    }

    public function store(Request $request)
    {
        $group = Group::create($this->validated($request) + ['tenant_id' => tenant()->id, 'status' => 'active']);

        return redirect()->route('app.groups.show', $group)->with('success', 'Guruh yaratildi');
    }

    public function show(Group $group)
    {
        $group->load(['activeKidLinks.kid', 'activeEmployeeLinks.employee']);
        // Tarbiyachi va yordamchi faqat bitta guruhda bo'ladi: boshqa guruhda band bo'lganlar ro'yxatda ko'rinmaydi
        $busy = GroupEmployee::where('ended_on', null)->pluck('employee_id');

        return view('app.groups.show', [
            'group' => $group,
            'availableEmployees' => Employee::active()->groupStaff()->with('role')->whereNotIn('id', $busy)->orderBy('full_name')->get(),
            'busyCount' => Employee::active()->groupStaff()->whereIn('id', $busy)->count(),
            'availableKids' => $group->isDaily() ? Kid::active()->whereNotIn('id', $group->activeKidLinks->pluck('kid_id'))->orderBy('full_name')->get(['id', 'full_name']) : collect(),
        ]);
    }

    public function edit(Group $group)
    {
        return view('app.groups.form', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $group->update($this->validated($request));

        return redirect()->route('app.groups.show', $group)->with('success', 'Guruh saqlandi');
    }

    public function archive(Group $group)
    {
        if ($group->status === 'active' && $group->activeKidLinks()->exists()) {
            return back()->with('error', "Guruhda bolalar bor. Avval ularni boshqa guruhga o'tkazing.");
        }
        $group->update(['status' => $group->status === 'active' ? 'archived' : 'active']);
        if ($group->status === 'archived') {
            $group->activeEmployeeLinks()->update(['ended_on' => today()]);
        }

        return redirect()->route('app.groups.index')->with('success', $group->status === 'archived' ? 'Guruh arxivlandi' : 'Guruh qayta faollashtirildi');
    }

    public function assignEmployee(Request $request, Group $group)
    {
        $data = $request->validate(['employee_id' => 'required|integer']);
        $emp = Employee::active()->with('role')->findOrFail($data['employee_id']);
        if (! in_array($emp->role?->key, Employee::GROUP_ROLES, true)) {
            return back()->with('error', __('Guruhga faqat tarbiyachi yoki yordamchi tarbiyachi biriktiriladi.'));
        }
        $other = GroupEmployee::where('employee_id', $emp->id)->whereNull('ended_on')->with('group')->first();
        if ($other) {
            return back()->with('error', __(':emp allaqachon «:group» guruhida. Avval o\'sha guruhdan chiqaring.', ['emp' => $emp->full_name, 'group' => $other->group?->name]));
        }
        GroupEmployee::create(['group_id' => $group->id, 'employee_id' => $emp->id, 'tenant_id' => tenant()->id,
            'role_in_group' => $emp->role->key === 'assistant' ? 'assistant' : 'teacher', 'started_on' => today(), 'started_by' => $request->user()->id]);

        return back()->with('success', __(':emp guruhga biriktirildi', ['emp' => $emp->full_name]));
    }

    public function removeEmployee(Request $request, Group $group, GroupEmployee $link)
    {
        abort_unless($link->group_id === $group->id, 404);
        $link->update(['ended_on' => today(), 'ended_by' => $request->user()->id]);

        return back()->with('success', 'Xodim guruhdan chiqarildi');
    }

    /** Shanba/kechki guruhga bolani qo'shish (bolalar doimiy kelmaydi, faqat kelgan kun uchun to'lanadi). */
    public function enrollKid(Request $request, Group $group)
    {
        abort_unless($group->isDaily(), 404);
        $data = $request->validate(['kid_id' => 'required|integer']);
        $kid = Kid::active()->findOrFail($data['kid_id']);
        if ($group->isFull()) {
            return back()->with('error', __('«:group» guruhi to\'lgan', ['group' => $group->name]));
        }
        GroupKid::firstOrCreate(['group_id' => $group->id, 'kid_id' => $kid->id, 'ended_on' => null],
            ['tenant_id' => tenant()->id, 'started_on' => today(), 'started_by' => $request->user()->id]);

        return back()->with('success', __(':kid guruhga qo\'shildi', ['kid' => $kid->full_name]));
    }

    public function leaveKid(Request $request, Group $group, GroupKid $link)
    {
        abort_unless($link->group_id === $group->id && $group->isDaily(), 404);
        $link->update(['ended_on' => today(), 'ended_by' => $request->user()->id]);

        return back()->with('success', 'Bola guruhdan chiqarildi');
    }

    private function validated(Request $request): array
    {
        $type = $request->input('type', 'main');
        abort_if($type !== 'main' && ! feature('extra_groups'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:100', 'type' => 'required|in:main,saturday,evening', 'size' => 'nullable|in:large,small',
            'monthly_fee' => 'nullable|integer|min:0', 'daily_fee' => 'nullable|integer|min:0',
            'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i|after:start_time',
            'capacity' => 'nullable|integer|min:1|max:200',
            'age_range' => 'nullable|string|max:40', 'room' => 'nullable|string|max:40', 'description' => 'nullable|string|max:500',
        ]);
        $data['size'] = $data['size'] ?? 'large';
        $data['monthly_fee'] = (int) ($data['monthly_fee'] ?? 0);
        if ($data['type'] === 'main' && $data['monthly_fee'] === 0) {
            $data['monthly_fee'] = (int) tenant()->setting('groups.price_'.$data['size'], 0); // sozlamadagi katta/kichik guruh narxi
        }
        $data['daily_fee'] = (int) ($data['daily_fee'] ?? 0);
        if ($data['type'] === 'main') {
            $data['daily_fee'] = 0;
            $data['start_time'] = $data['start_time'] ?? '09:00';
            $data['end_time'] = $data['end_time'] ?? '17:00';
        } else {
            $data['monthly_fee'] = 0;
            if ($data['type'] === 'evening') {
                $data['start_time'] = $data['start_time'] ?? '17:00';
                $data['end_time'] = $data['end_time'] ?? '20:00';
            }
        }

        return $data;
    }
}
