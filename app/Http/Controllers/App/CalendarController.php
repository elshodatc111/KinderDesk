<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Calendar\WorkCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function __construct(private WorkCalendar $calendar)
    {
    }

    private function month(Request $request): Carbon
    {
        $m = (string) $request->input('month', now()->format('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $m) ? Carbon::parse($m.'-01') : now()->startOfMonth();
    }

    public function index(Request $request)
    {
        $month = $this->month($request);

        return view('app.calendar.index', [
            'month' => $month,
            'days' => $this->calendar->month($month),
            'workDays' => count($this->calendar->workingDays($month)),
            'confirmed' => $this->calendar->isConfirmed($month),
            'kinds' => WorkCalendar::kinds(),
        ]);
    }

    public function day(Request $request)
    {
        $data = $request->validate(['date' => 'required|date', 'kind' => 'nullable|in:working,off,holiday', 'title' => 'nullable|string|max:80']);
        $date = Carbon::parse($data['date']);
        $this->calendar->setDay($date, $data['kind'] ?? null, $data['title'] ?? null);

        return redirect()->route('app.calendar.index', ['month' => $date->format('Y-m')]);
    }

    public function confirm(Request $request)
    {
        $month = $this->month($request);
        if ($request->boolean('undo')) {
            $this->calendar->unconfirm($month);
            $msg = __('Tasdiq bekor qilindi');
        } else {
            $this->calendar->confirm($month, $request->user());
            $msg = __('Oy tasdiqlandi');
        }

        return redirect()->route('app.calendar.index', ['month' => $month->format('Y-m')])->with('status', $msg);
    }
}
