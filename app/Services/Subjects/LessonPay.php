<?php

namespace App\Services\Subjects;

use App\Models\Employee;
use App\Models\KidLedger;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Support\Carbon;

/**
 * Pedagog ish haqi: o'tilgan dars uchun (bir dars bir nechta guruhga o'tilsa ham — bitta dars) yoki oylik belgilangan.
 */
class LessonPay
{
    /** @return array{amount:int, rows: array<int, array>} */
    public function forEmployee(Employee $e, Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $rows = [];
        $total = 0;

        $held = Lesson::with('subject')->where('employee_id', $e->id)->where('held', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get()->groupBy('subject_id');
        foreach ($held as $subjectId => $lessons) {
            $subject = $lessons->first()->subject;
            if (! $subject || $subject->pay_mode !== 'per_lesson') {
                continue;
            }
            $sum = $lessons->count() * (int) $subject->lesson_rate;
            $total += $sum;
            $rows[] = ['subject' => $subject->name, 'mode' => 'per_lesson', 'count' => $lessons->count(), 'rate' => (int) $subject->lesson_rate, 'amount' => $sum];
        }
        foreach (Subject::where('employee_id', $e->id)->where('pay_mode', 'fixed')->where('status', 'active')->get() as $subject) {
            $total += (int) $subject->fixed_salary;
            $rows[] = ['subject' => $subject->name, 'mode' => 'fixed', 'count' => 0, 'rate' => 0, 'amount' => (int) $subject->fixed_salary];
        }

        return ['amount' => $total, 'rows' => $rows];
    }

    /**
     * Pullik to'garak foydasi: tushum − pedagog ish haqi.
     * @return array<int, array{subject:string, income:int, pay:int, profit:int}>
     */
    public function clubProfit(Carbon $period): array
    {
        $start = $period->copy()->startOfMonth();
        $out = [];
        foreach (Subject::with('employee')->where('kind', 'club')->whereNotNull('club_id')->get() as $s) {
            $income = -(int) KidLedger::where('club_id', $s->club_id)->where('status', 'posted')->whereIn('type', ['charge', 'discount'])
                ->whereDate('period', $start)->sum('amount');
            $pay = 0;
            if ($s->pay_mode === 'fixed') {
                $pay = (int) $s->fixed_salary;
            } else {
                $pay = (int) Lesson::where('subject_id', $s->id)->where('held', true)
                    ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->count() * (int) $s->lesson_rate;
            }
            $out[] = ['subject' => $s->name, 'income' => $income, 'pay' => $pay, 'profit' => $income - $pay];
        }

        return $out;
    }
}
