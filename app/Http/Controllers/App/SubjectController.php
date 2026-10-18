<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Employee;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Subject;
use App\Services\Subjects\LessonPay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubjectController extends Controller
{
    public function index(Request $request, LessonPay $pay)
    {
        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01')->startOfMonth();
        $subjects = Subject::with('employee', 'club')->orderBy('name')->get();
        $lessons = Lesson::with('subject', 'employee', 'groups')->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('date')->orderByDesc('id')->get();

        $pedagogs = Employee::active()->orderBy('full_name')->get(['id', 'full_name']);

        return view('app.subjects.index', [
            'month' => $month, 'subjects' => $subjects, 'lessons' => $lessons, 'pedagogs' => $pedagogs,
            'groups' => Group::main()->active()->orderBy('name')->get(['id', 'name']),
            'clubs' => Club::active()->orderBy('name')->get(['id', 'name']),
            'profit' => $pay->clubProfit($month),
            'pays' => $pedagogs->mapWithKeys(fn ($e) => [$e->id => $pay->forEmployee($e, $month)['amount']])->filter(),
        ]);
    }

    public function store(Request $request)
    {
        $subject = Subject::create($this->validated($request) + ['tenant_id' => tenant()->id, 'status' => 'active']);
        AuditLog::record('subject.created', $subject);

        return back()->with('success', "Fan qo'shildi");
    }

    public function update(Request $request, Subject $subject)
    {
        $subject->update($this->validated($request) + ['status' => $request->boolean('archived') ? 'archived' : 'active']);

        return back()->with('success', 'Saqlandi');
    }

    public function destroy(Subject $subject)
    {
        if (Lesson::where('subject_id', $subject->id)->exists()) {
            $subject->update(['status' => 'archived']);

            return back()->with('success', "Darslari bor — arxivga o'tkazildi");
        }
        $subject->delete();

        return back()->with('success', "Fan o'chirildi");
    }

    public function lessonStore(Request $request)
    {
        $data = $request->validate([
            'subject_id' => 'required|integer', 'employee_id' => 'required|integer', 'date' => 'required|date|before_or_equal:today',
            'held' => 'nullable|boolean', 'note' => 'nullable|string|max:200', 'group_ids' => 'nullable|array', 'group_ids.*' => 'integer',
        ]);
        $subject = Subject::findOrFail($data['subject_id']);
        Employee::findOrFail($data['employee_id']);
        $lesson = Lesson::create([
            'tenant_id' => tenant()->id, 'subject_id' => $subject->id, 'employee_id' => $data['employee_id'], 'date' => $data['date'],
            'held' => $request->boolean('held', true), 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
        ]);
        $lesson->groups()->sync(Group::whereIn('id', $data['group_ids'] ?? [])->pluck('id'));

        return back()->with('success', 'Dars yozildi');
    }

    public function lessonDestroy(Lesson $lesson)
    {
        $lesson->delete();

        return back()->with('success', "Dars o'chirildi");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'kind' => 'required|in:club,lesson', 'club_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer', 'pay_mode' => 'required|in:per_lesson,fixed',
            'lesson_rate' => 'nullable|integer|min:0', 'fixed_salary' => 'nullable|integer|min:0',
        ]);
        $data['club_id'] = $data['kind'] === 'club' ? ($data['club_id'] ?? null) : null;
        $data['lesson_rate'] = (int) ($data['lesson_rate'] ?? 0);
        $data['fixed_salary'] = (int) ($data['fixed_salary'] ?? 0);

        return $data;
    }
}
