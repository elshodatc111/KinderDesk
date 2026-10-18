<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ReportSubmission;
use App\Models\ReportType;
use App\Models\ReportWaiver;
use App\Models\Role;
use App\Services\Reports\ReportChain;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportChainController extends Controller
{
    public function __construct(private ReportChain $chain)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $me = $user->employee;
        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01')->startOfMonth();

        $queue = collect();
        $levels = array_values(array_filter([$user->can('report.review') ? 1 : null, $user->can('report.approve') ? 2 : null]));
        if ($levels) {
            $queue = ReportSubmission::with('type', 'employee')->where('status', 'submitted')->whereHas('type', fn ($q) => $q->whereIn('level', $levels))
                ->when($me, fn ($q) => $q->where('employee_id', '!=', $me->id))->orderBy('date')->get();
        }

        $overview = [];
        if ($levels) {
            foreach (Employee::active()->with('role')->orderBy('full_name')->get() as $e) {
                if ($this->chain->typesFor($e)->isEmpty()) {
                    continue;
                }
                $missing = $this->chain->missing($e, $month);
                $overview[] = ['employee' => $e, 'missing' => $missing, 'allowed' => $this->chain->bonusesAllowed($e, $month),
                    'waivers' => ReportWaiver::where('employee_id', $e->id)->whereDate('period', $month)->count()];
            }
        }

        return view('app.chain.index', [
            'me' => $me, 'month' => $month, 'todo' => $me ? $this->chain->todo($me) : [], 'queue' => $queue, 'overview' => $overview,
            'types' => $user->can('report.types') ? ReportType::orderBy('level')->orderBy('name')->get() : collect(),
            'roles' => Role::pluck('name', 'key'),
            'employees' => ($user->can('report.review') || $user->can('report.approve')) ? Employee::active()->with('role')->orderBy('full_name')->get() : collect(),
            'chain' => $this->chain,
        ]);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'type_id' => 'required|integer', 'date' => 'required|date', 'employee_id' => 'nullable|integer', 'body' => 'nullable|string|max:5000',
            'files' => 'nullable|array|max:6', 'files.*' => 'file|max:20480|mimes:jpg,jpeg,png,webp,mp4,mov,pdf,docx',
        ]);
        $user = $request->user();
        $type = ReportType::findOrFail($data['type_id']);
        $emp = $user->employee;
        if (! empty($data['employee_id']) && (! $emp || (int) $data['employee_id'] !== $emp->id)) {
            abort_unless($user->can('report.review') || $user->can('report.approve'), 403);
            $emp = Employee::findOrFail($data['employee_id']);
        }
        abort_unless($emp, 403);
        abort_unless($user->can('report.submit') || $emp->id !== $user->employee?->id, 403);

        $files = [];
        foreach ($request->file('files', []) as $f) {
            $files[] = ['path' => $f->store('reports/'.tenant()->id), 'name' => $f->getClientOriginalName(), 'size' => $f->getSize()];
        }
        try {
            $this->chain->submit($type, $emp, Carbon::parse($data['date']), $data['body'] ?? null, $files, $user);
        } catch (\DomainException $e) {
            foreach ($files as $f) {
                Storage::delete($f['path']);
            }

            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Hisobot topshirildi');
    }

    public function review(Request $request, ReportSubmission $submission)
    {
        $data = $request->validate(['action' => 'required|in:accept,return', 'note' => 'nullable|string|max:300']);
        $submission->load('type');
        abort_unless($request->user()->can($submission->type->level === 2 ? 'report.approve' : 'report.review'), 403);
        abort_if($submission->employee_id === $request->user()->employee?->id, 403);
        try {
            $this->chain->review($submission, $data['action'] === 'accept', $data['note'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['action'] === 'accept' ? 'Qabul qilindi' : 'Qaytarildi');
    }

    public function file(Request $request, ReportSubmission $submission, int $i)
    {
        $user = $request->user();
        $own = $submission->employee_id === $user->employee?->id;
        abort_unless($own || $user->can('report.review') || $user->can('report.approve'), 403);
        $f = ($submission->files ?? [])[$i] ?? abort(404);
        abort_unless(str_starts_with($f['path'], 'reports/'.tenant()->id.'/') && Storage::exists($f['path']), 404);

        return Storage::download($f['path'], $f['name']);
    }

    public function typeStore(Request $request)
    {
        ReportType::create($this->validatedType($request) + ['tenant_id' => tenant()->id]);

        return back()->with('success', "Hisobot turi qo'shildi");
    }

    public function typeUpdate(Request $request, ReportType $type)
    {
        $type->update($this->validatedType($request));

        return back()->with('success', 'Saqlandi');
    }

    public function typeDestroy(ReportType $type)
    {
        if ($type->level && ReportSubmission::where('type_id', $type->id)->exists()) {
            $type->update(['active' => false]);

            return back()->with('success', "Topshirilgan hisobotlar bor — faolsiz qilindi");
        }
        $type->delete();

        return back()->with('success', "O'chirildi");
    }

    public function waive(Request $request)
    {
        $data = $request->validate(['employee_id' => 'required|integer', 'month' => 'required|date_format:Y-m', 'date' => 'nullable|date', 'reason' => 'required|string|max:300']);
        $emp = Employee::findOrFail($data['employee_id']);
        try {
            $this->chain->waive($emp, Carbon::parse($data['month'].'-01'), ! empty($data['date']) ? Carbon::parse($data['date']) : null, $data['reason'], $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kechirildi, jurnalga yozildi');
    }

    private function validatedType(Request $request): array
    {
        $d = $request->validate([
            'name' => 'required|string|max:120', 'role_key' => 'required|string|max:40', 'level' => 'required|in:1,2',
            'frequency' => 'required|in:daily,weekly,monthly', 'deadline_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'content' => 'required|in:text,file,media,auto', 'hint' => 'nullable|string|max:300',
        ]);
        $d['is_parent'] = $request->boolean('is_parent');
        $d['affects_bonus'] = $request->boolean('affects_bonus', true);
        $d['active'] = $request->boolean('active', true);
        if ((int) $d['level'] === 2) {
            $d['role_key'] = 'admin';
        }

        return $d;
    }
}
