<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Event;
use App\Models\EventCost;
use App\Models\EventKid;
use App\Models\EventStaff;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Services\Events\EventService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(private EventService $events)
    {
    }

    public function index()
    {
        return view('app.events.index', ['events' => Event::withCount('kids')->orderByDesc('date')->get()]);
    }

    public function create()
    {
        return view('app.events.form', ['event' => new Event(['date' => today()->addDays(14)->toDateString()])]);
    }

    public function store(Request $request)
    {
        $event = Event::create($this->validated($request) + ['tenant_id' => tenant()->id, 'status' => 'draft', 'created_by' => $request->user()->id]);
        AuditLog::record('event.created', $event);

        return redirect()->route('app.events.show', $event)->with('success', "Tadbir yaratildi. Endi xarajatlar smetasini kiriting.");
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request));

        return back()->with('success', 'Saqlandi');
    }

    public function show(Event $event)
    {
        $event->load('costs', 'staff.employee', 'kids.kid');
        $registered = $event->kids->pluck('kid_id');

        return view('app.events.show', [
            'event' => $event, 'price' => $this->events->currentPrice($event), 'variance' => $this->events->variance($event),
            'kidsAvailable' => Kid::active()->whereNotIn('id', $registered)->orderBy('full_name')->get(['id', 'full_name']),
            'employees' => Employee::active()->orderBy('full_name')->get(['id', 'full_name']),
            'groups' => Group::main()->active()->orderBy('name')->get(['id', 'name']),
            'collectBefore' => (bool) tenant()->setting('event.collect_before', false),
        ]);
    }

    public function destroy(Event $event)
    {
        if (in_array($event->status, ['draft', 'pending'], true)) {
            $event->delete();

            return redirect()->route('app.events.index')->with('success', "Tadbir o'chirildi");
        }
        $this->events->cancel($event);

        return back()->with('success', 'Tadbir bekor qilindi');
    }

    /* -------- smeta -------- */
    public function costStore(Request $request, Event $event)
    {
        $this->editable($event);
        $data = $request->validate(['kind' => 'required|in:transport,ticket,food,other', 'title' => 'required|string|max:120', 'planned' => 'required|integer|min:0']);
        EventCost::create($data + ['tenant_id' => tenant()->id, 'event_id' => $event->id]);
        $this->events->afterCostsChanged($event);

        return back()->with('success', "Xarajat qo'shildi");
    }

    public function costDestroy(Event $event, EventCost $cost)
    {
        $this->editable($event);
        $cost->delete();

        return back()->with('success', "Xarajat o'chirildi");
    }

    public function costActual(Request $request, Event $event)
    {
        abort_unless(in_array($event->status, ['locked', 'held'], true), 403);
        $data = $request->validate(['actual' => 'required|array', 'actual.*' => 'nullable|integer|min:0']);
        foreach ($data['actual'] as $id => $value) {
            EventCost::where('event_id', $event->id)->where('id', $id)->update(['actual' => $value === null || $value === '' ? null : (int) $value]);
        }

        return back()->with('success', 'Haqiqiy xarajatlar saqlandi');
    }

    public function staffStore(Request $request, Event $event)
    {
        $this->editable($event);
        $data = $request->validate(['employee_id' => 'required|integer', 'pay' => 'required|integer|min:0']);
        Employee::findOrFail($data['employee_id']);
        EventStaff::updateOrCreate(['event_id' => $event->id, 'employee_id' => $data['employee_id']], ['tenant_id' => tenant()->id, 'pay' => $data['pay']]);
        $this->events->afterCostsChanged($event);

        return back()->with('success', 'Xodim qo\'shildi');
    }

    public function staffDestroy(Event $event, EventStaff $staff)
    {
        $this->editable($event);
        $staff->delete();

        return back()->with('success', "Xodim olib tashlandi");
    }

    /* -------- jarayon -------- */
    public function submit(Event $event)
    {
        return $this->run(fn () => $this->events->submit($event), 'Direktor tasdig\'iga yuborildi');
    }

    public function approve(Request $request, Event $event)
    {
        return $this->run(fn () => $this->events->approve($event, $request->user()), "Tasdiqlandi. Bolalarni ro'yxatga olish mumkin");
    }

    public function reject(Event $event)
    {
        return $this->run(fn () => $this->events->reject($event), 'Qaytarildi');
    }

    public function lock(Event $event)
    {
        return $this->run(fn () => $this->events->lock($event), "Ro'yxat yopildi, narx qotirildi");
    }

    public function hold(Event $event)
    {
        return $this->run(fn () => $this->events->hold($event), "Tadbir o'tkazildi deb belgilandi");
    }

    public function close(Event $event)
    {
        return $this->run(fn () => $this->events->close($event), 'Tadbir yopildi');
    }

    public function kidAdd(Request $request, Event $event)
    {
        $data = $request->validate(['kid_ids' => 'required|array|min:1', 'kid_ids.*' => 'integer']);

        return $this->run(function () use ($event, $data) {
            foreach (Kid::active()->whereIn('id', $data['kid_ids'])->get() as $kid) {
                $this->events->register($event, $kid);
            }
        }, "Bolalar ro'yxatga olindi");
    }

    public function groupAdd(Request $request, Event $event)
    {
        $data = $request->validate(['group_id' => 'required|integer']);
        $ids = GroupKid::where('group_id', $data['group_id'])->whereNull('ended_on')->pluck('kid_id');

        return $this->run(function () use ($event, $ids) {
            foreach (Kid::active()->whereIn('id', $ids)->get() as $kid) {
                $this->events->register($event, $kid);
            }
        }, "Guruh bolalari ro'yxatga olindi");
    }

    public function kidRemove(Event $event, Kid $kid)
    {
        return $this->run(fn () => $this->events->unregister($event, $kid), "Ro'yxatdan chiqarildi");
    }

    public function mark(Request $request, Event $event, Kid $kid)
    {
        $data = $request->validate(['status' => 'required|in:present,absent']);

        return $this->run(fn () => $this->events->mark($event, $kid, $data['status']), 'Belgilandi');
    }

    private function run(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }

    private function editable(Event $event): void
    {
        abort_if(in_array($event->status, ['held', 'closed', 'cancelled'], true), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:150', 'date' => 'required|date|after_or_equal:today',
            'deadline' => 'nullable|date|before_or_equal:date', 'note' => 'nullable|string|max:1000',
        ]);
    }
}
