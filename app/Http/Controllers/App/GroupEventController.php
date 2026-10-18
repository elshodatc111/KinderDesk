<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEvent;
use App\Services\Events\EventService;
use Illuminate\Http\Request;

class GroupEventController extends Controller
{
    public function index()
    {
        $groups = Group::main()->active()->with('activeEmployeeLinks.employee')->orderBy('name')->get();

        return view('app.group-events.index', [
            'rows' => GroupEvent::with('group', 'staff')->orderByDesc('date')->limit(100)->get(),
            'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'staff' => $g->activeEmployeeLinks->map(fn ($l) => [
                'id' => $l->employee_id, 'name' => $l->employee?->full_name, 'role' => $l->role_in_group])->values()]),
            'bonusTeacher' => (int) tenant()->setting('event.bonus_teacher', 50000),
            'bonusAssistant' => (int) tenant()->setting('event.bonus_assistant', 25000),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|integer', 'date' => 'required|date|before_or_equal:today', 'kind' => 'required|in:matinee,excursion,contest,other',
            'title' => 'required|string|max:150', 'staff' => 'required|array|min:1', 'staff.*' => 'integer',
        ], ['staff.required' => "Ishtirok etgan xodimlarni tanlang"]);
        $group = Group::findOrFail($data['group_id']);
        $ev = GroupEvent::create(['tenant_id' => tenant()->id, 'group_id' => $group->id, 'date' => $data['date'], 'kind' => $data['kind'],
            'title' => $data['title'], 'status' => 'pending', 'created_by' => $request->user()->id]);
        $ev->staff()->sync(Employee::whereIn('id', $data['staff'])->pluck('id'));

        return back()->with('success', "Direktor tasdig'iga yuborildi");
    }

    public function approve(Request $request, GroupEvent $groupEvent, EventService $events)
    {
        $events->approveGroupEvent($groupEvent, $request->user());

        return back()->with('success', "Tasdiqlandi, bonus ish haqiga yozildi");
    }

    public function reject(Request $request, GroupEvent $groupEvent, EventService $events)
    {
        $events->rejectGroupEvent($groupEvent, $request->user());

        return back()->with('success', 'Rad etildi');
    }

    public function destroy(GroupEvent $groupEvent, EventService $events)
    {
        $events->rejectGroupEvent($groupEvent, request()->user());
        $groupEvent->delete();

        return back()->with('success', "O'chirildi");
    }
}
