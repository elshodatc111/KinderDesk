<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $q = Employee::where('status', $status === 'archived' ? 'archived' : 'active')->with('activeGroupLinks.group');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('full_name', 'like', "%$s%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $s).'%'));
        }
        if ($p = $request->query('role')) {
            $q->where('role_id', $p);
        }

        return view('app.employees.index', ['employees' => $q->with('role')->orderBy('full_name')->paginate(25)->withQueryString(), 'status' => $status,
            'roles' => Role::where('tenant_id', tenant()->id)->where('key', '!=', 'director')->orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return view('app.employees.form', $this->formData(new Employee(['hired_on' => today(), 'full_name' => $request->query('name'), 'phone' => $request->query('phone')])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($stop = app(\App\Services\Guardians\BlacklistService::class)->guard($request, [$data['phone'] ?? null], null, 'staff', $data['full_name'])) {
            return $stop;
        }
        $emp = Employee::create($data + ['tenant_id' => tenant()->id, 'status' => 'active']);
        $this->syncLogin($request, $emp);
        if ($leadId = (int) $request->input('lead_id')) {
            \App\Services\Leads\LeadFlow::converted($leadId, $emp->id);
        }
        AuditLog::record('employee.created', $emp);

        return redirect()->route('app.employees.show', $emp)->with('success', __(':emp qo\'shildi', ['emp' => $emp->full_name]));
    }

    public function show(Employee $employee)
    {
        $employee->load('activeGroupLinks.group', 'user.role');
        $month = today()->startOfMonth();
        $att = $employee->attendance()->whereDate('date', '>=', $month)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('app.employees.show', [
            'employee' => $employee, 'att' => $att,
            'payslips' => tenant()->hasFeature('payroll') && auth()->user()->can('payroll.view') ? $employee->payslips()->limit(12)->get() : collect(),
            'notes' => $employee->notes()->with('author')->get(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('app.employees.form', $this->formData($employee));
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request));
        if ($employee->user && ! $employee->user->is_owner) {
            $employee->user->update(['role_id' => $employee->role_id, 'name' => $employee->full_name]);
        }
        $this->syncLogin($request, $employee);

        return redirect()->route('app.employees.show', $employee)->with('success', 'Saqlandi');
    }

    public function archive(Request $request, Employee $employee)
    {
        if ($employee->status === 'active') {
            $employee->update(['status' => 'archived', 'left_on' => today()]);
            $employee->activeGroupLinks()->update(['ended_on' => today(), 'ended_by' => $request->user()->id]);
            if ($employee->user && ! $employee->user->is_owner) {
                $employee->user->update(['status' => 'blocked']);
            }
            $msg = 'Xodim arxivga o\'tkazildi';
        } else {
            $employee->update(['status' => 'active', 'left_on' => null]);
            $msg = 'Xodim qayta faollashtirildi';
        }

        return redirect()->route('app.employees.index')->with('success', $msg);
    }

    public function note(Request $request, Employee $employee)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $employee->notes()->create(['tenant_id' => tenant()->id, 'body' => $data['body'], 'user_id' => $request->user()->id]);

        return back()->with('success', "Izoh qo'shildi");
    }

    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'roles' => Role::where('tenant_id', tenant()->id)->where('key', '!=', 'director')->orderBy('id')->get(),
            'payroll' => tenant()->hasFeature('payroll'),
            'strategy' => tenant()->setting('payroll.strategy', 'fixed'),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150', 'phone' => 'nullable|string', 'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('tenant_id', tenant()->id)->where('key', '!=', 'director')],
            'position_note' => 'nullable|string|max:100', 'birth_date' => 'nullable|date|before:today', 'passport' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200', 'hired_on' => 'nullable|date', 'salary_type' => 'nullable|in:fixed,attendance,hybrid', 'scheme_key' => 'nullable|in:fixed,group,cook,admin,pedagog',
            'base_salary' => 'nullable|integer|min:0', 'daily_rate' => 'nullable|integer|min:0', 'kpi_per_kid' => 'nullable|integer|min:0', 'note' => 'nullable|string|max:500',
        ]);
        if (! empty($data['phone'])) {
            $data['phone'] = normalize_phone($data['phone']);
            if (! $data['phone']) {
                throw ValidationException::withMessages(['phone' => "Telefon raqam noto'g'ri"]);
            }
        }
        foreach (['base_salary', 'daily_rate', 'kpi_per_kid'] as $f) {
            $data[$f] = (int) ($data[$f] ?? 0);
        }
        $data['salary_type'] = ($data['salary_type'] ?? null) ?: null;
        $data['scheme_key'] = $data['scheme_key'] ?? null ?: null;

        return $data;
    }

    /** Xodimga tizimga kirish huquqi (ixtiyoriy). */
    private function syncLogin(Request $request, Employee $emp): void
    {
        if (! $request->boolean('has_login')) {
            return;
        }
        $t = tenant();
        $phone = $emp->phone;
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Tizimga kirish uchun telefon raqam kerak']);
        }
        $role = Role::where('tenant_id', $t->id)->where('key', '!=', 'director')->findOrFail($emp->role_id);
        if ($emp->user) {
            $emp->user->update(['role_id' => $role->id, 'status' => 'active', 'name' => $emp->full_name]);

            return;
        }
        $request->validate(['password' => ['required', Password::min(8)->letters()->numbers()]]);
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Bu raqam bilan foydalanuvchi allaqachon mavjud']);
        }
        $user = User::create(['tenant_id' => $t->id, 'role_id' => $role->id, 'name' => $emp->full_name, 'phone' => $phone,
            'password' => $request->input('password'), 'status' => 'active', 'phone_verified_at' => now(), 'locale' => app()->getLocale()]);
        $emp->update(['user_id' => $user->id]);
    }
}
