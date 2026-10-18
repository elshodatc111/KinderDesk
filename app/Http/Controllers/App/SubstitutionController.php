<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\Substitution;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubstitutionController extends Controller
{
    public function index()
    {
        $groups = Group::main()->active()->with('activeEmployeeLinks.employee')->orderBy('name')->get();

        return view('app.substitutions.index', [
            'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'staff' => $g->activeEmployeeLinks->map(fn ($l) => [
                'id' => $l->employee_id, 'name' => $l->employee?->full_name, 'role' => $l->role_in_group])->values()]),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
            'rows' => Substitution::whereDate('date', '>=', today()->subDays(60))->orderByDesc('date')->get(),
            'groupNames' => Group::pluck('name', 'id'),
            'employeeNames' => Employee::pluck('full_name', 'id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date|before_or_equal:today', 'group_id' => 'required|integer', 'absent_employee_id' => 'required|integer',
            'substitute_employee_id' => 'nullable|integer|different:absent_employee_id',
        ]);
        $group = Group::findOrFail($data['group_id']);
        $date = Carbon::parse($data['date']);
        $assigned = GroupEmployee::where('group_id', $group->id)->where('employee_id', $data['absent_employee_id'])
            ->whereDate('started_on', '<=', $date)->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date))->exists();
        if (! $assigned) {
            return back()->with('error', "Bu xodim o'sha kuni shu guruhga biriktirilmagan")->withInput();
        }
        Substitution::updateOrCreate(
            ['group_id' => $group->id, 'date' => $date->toDateString(), 'absent_employee_id' => $data['absent_employee_id']],
            ['tenant_id' => tenant()->id, 'substitute_employee_id' => $data['substitute_employee_id'] ?: null, 'created_by' => $request->user()->id],
        );

        return back()->with('success', 'Almashtirish saqlandi');
    }

    public function destroy(Substitution $substitution)
    {
        $substitution->delete();

        return back()->with('success', "Almashtirish o'chirildi");
    }
}
