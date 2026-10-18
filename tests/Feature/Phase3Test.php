<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Event;
use App\Models\EventCost;
use App\Models\EventStaff;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupEvent;
use App\Models\Kid;
use App\Models\Lesson;
use App\Models\PayslipItem;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\Events\EventService;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Subjects\LessonPay;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234501', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
    }

    private function inTenant(callable $fn)
    {
        return app(TenantContext::class)->run($this->tenant, $fn);
    }

    private function emp(string $name, string $roleKey): Employee
    {
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', $roleKey)->first();

        return Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => $name, 'role_id' => $role?->id, 'position' => $roleKey, 'status' => 'active', 'hired_on' => '2026-01-01', 'base_salary' => 0]);
    }

    private function kid(string $n): Kid
    {
        return Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => $n, 'birth_date' => '2021-01-01', 'parent_phone' => '99890'.random_int(1000000, 9999999), 'status' => 'active', 'enrolled_on' => '2026-01-01']);
    }

    private function event(): Event
    {
        $e = Event::create(['tenant_id' => $this->tenant->id, 'title' => 'Ekskursiya', 'date' => '2026-10-25', 'status' => 'draft']);
        EventCost::create(['tenant_id' => $this->tenant->id, 'event_id' => $e->id, 'kind' => 'transport', 'title' => 'Avtobus', 'planned' => 600_000]);
        EventCost::create(['tenant_id' => $this->tenant->id, 'event_id' => $e->id, 'kind' => 'ticket', 'title' => 'Chipta', 'planned' => 400_000]);

        return $e;
    }

    public function test_pedagog_is_paid_per_held_lesson_once_even_with_many_groups(): void
    {
        $this->inTenant(function () {
            $p = $this->emp('Pedagog', 'pedagog');
            $s = Subject::create(['tenant_id' => $this->tenant->id, 'name' => 'Ingliz tili', 'kind' => 'lesson', 'employee_id' => $p->id, 'pay_mode' => 'per_lesson', 'lesson_rate' => 250_000]);
            $g1 = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'type' => 'main', 'monthly_fee' => 1, 'capacity' => 20, 'status' => 'active']);
            $g2 = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'B', 'type' => 'main', 'monthly_fee' => 1, 'capacity' => 20, 'status' => 'active']);
            $l = Lesson::create(['tenant_id' => $this->tenant->id, 'subject_id' => $s->id, 'employee_id' => $p->id, 'date' => '2026-10-05', 'held' => true]);
            $l->groups()->sync([$g1->id, $g2->id]);
            Lesson::create(['tenant_id' => $this->tenant->id, 'subject_id' => $s->id, 'employee_id' => $p->id, 'date' => '2026-10-06', 'held' => true]);
            Lesson::create(['tenant_id' => $this->tenant->id, 'subject_id' => $s->id, 'employee_id' => $p->id, 'date' => '2026-10-07', 'held' => false]);

            $this->assertSame(500_000, app(LessonPay::class)->forEmployee($p, Carbon::parse('2026-10-01'))['amount']);
            $r = app(PayrollCalculator::class)->calculate($p, Carbon::parse('2026-10-01'));
            $this->assertSame('scheme', $r['strategy']);
            $this->assertSame(500_000, $r['total']);
        });
    }

    public function test_fixed_salary_subject_and_club_profit(): void
    {
        $this->inTenant(function () {
            $p = $this->emp('Pedagog', 'pedagog');
            Subject::create(['tenant_id' => $this->tenant->id, 'name' => 'Shaxmat', 'kind' => 'lesson', 'employee_id' => $p->id, 'pay_mode' => 'fixed', 'fixed_salary' => 1_200_000]);
            $this->assertSame(1_200_000, app(PayrollCalculator::class)->calculate($p, Carbon::parse('2026-10-01'))['total']);
        });
    }

    public function test_event_price_is_rounded_up_and_locked_with_markup(): void
    {
        $this->inTenant(function () {
            $svc = app(EventService::class);
            $this->assertSame(34_000, $svc->computePrice(1_000_000, 30));
            $this->tenant->setSetting('event.markup_percent', 10);
            $this->assertSame(37_000, $svc->computePrice(1_000_000, 30)); // 33333*1.1=36666 -> 37000
            $this->assertSame(0, $svc->computePrice(1_000_000, 0));
        });
    }

    public function test_event_flow_charges_only_present_kids_and_reports_variance(): void
    {
        $this->inTenant(function () {
            $svc = app(EventService::class);
            $director = User::where('tenant_id', $this->tenant->id)->first();
            $t = $this->emp('Tarbiyachi', 'teacher');
            $e = $this->event();
            EventStaff::create(['tenant_id' => $this->tenant->id, 'event_id' => $e->id, 'employee_id' => $t->id, 'pay' => 100_000]);
            $svc->submit($e);
            $svc->approve($e->fresh(), $director);
            $e = $e->fresh();
            $this->assertSame('registration', $e->status);
            $this->assertSame(1_100_000, $e->approved_total);

            $a = $this->kid('A'); $b = $this->kid('B');
            $svc->register($e, $a); $svc->register($e, $b);
            $svc->lock($e);
            $e = $e->fresh();
            $this->assertSame(550_000, $e->price);

            $svc->mark($e, $a, 'present');
            $svc->mark($e, $b, 'absent');
            $this->assertSame(-550_000, (int) \App\Models\KidLedger::where('kid_id', $a->id)->where('charge_key', 'ev:'.$e->id)->where('status', 'posted')->value('amount'));
            $this->assertFalse(\App\Models\KidLedger::where('kid_id', $b->id)->where('charge_key', 'ev:'.$e->id)->where('status', 'posted')->exists());

            // xato bosish: keldi -> kelmadi -> keldi, qayta yechilmaydi/ikki marta yechilmaydi
            $svc->mark($e, $a, 'absent');
            $svc->mark($e, $a, 'present');
            $this->assertSame(1, \App\Models\KidLedger::where('kid_id', $a->id)->where('charge_key', 'ev:'.$e->id)->where('status', 'posted')->count());

            $svc->hold($e);
            $svc->close($e->fresh());
            $item = PayslipItem::where('employee_id', $t->id)->where('source', 'ev:'.$e->id)->first();
            $this->assertSame(100_000, $item->amount);
            $v = $svc->variance($e->fresh());
            $this->assertSame(550_000, $v['collected']);
            $this->assertSame(1_100_000, $v['actual']);
        });
    }

    public function test_collect_before_charges_all_on_lock(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('event.collect_before', true);
            $svc = app(EventService::class);
            $director = User::where('tenant_id', $this->tenant->id)->first();
            $e = $this->event();
            $svc->submit($e); $svc->approve($e->fresh(), $director);
            $a = $this->kid('A'); $b = $this->kid('B');
            $svc->register($e->fresh(), $a); $svc->register($e->fresh(), $b);
            $svc->lock($e->fresh());
            $this->assertSame(2, \App\Models\KidLedger::where('charge_key', 'ev:'.$e->id)->where('status', 'posted')->count());
        });
    }

    public function test_cost_increase_after_approval_requires_reapproval(): void
    {
        $this->inTenant(function () {
            $svc = app(EventService::class);
            $director = User::where('tenant_id', $this->tenant->id)->first();
            $e = $this->event();
            $svc->submit($e); $svc->approve($e->fresh(), $director);
            EventCost::create(['tenant_id' => $this->tenant->id, 'event_id' => $e->id, 'kind' => 'food', 'title' => 'Tushlik', 'planned' => 200_000]);
            $svc->afterCostsChanged($e->fresh());
            $e = $e->fresh();
            $this->assertSame('pending', $e->status);
            $this->assertTrue($e->reapproval);
            $svc->approve($e, $director);
            $this->assertSame(1_200_000, $e->fresh()->approved_total);
        });
    }

    public function test_group_event_bonus_goes_only_to_that_groups_staff_by_role(): void
    {
        $this->inTenant(function () {
            $svc = app(EventService::class);
            $director = User::where('tenant_id', $this->tenant->id)->first();
            $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'type' => 'main', 'monthly_fee' => 1, 'capacity' => 20, 'status' => 'active']);
            $t = $this->emp('T', 'teacher'); $a = $this->emp('Y', 'assistant'); $other = $this->emp('Boshqa', 'teacher');
            GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $t->id, 'role_in_group' => 'teacher', 'started_on' => '2026-01-01']);
            GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $a->id, 'role_in_group' => 'assistant', 'started_on' => '2026-01-01']);
            $ev = GroupEvent::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'date' => '2026-10-10', 'kind' => 'matinee', 'title' => 'Navro\'z', 'status' => 'pending']);
            $ev->staff()->sync([$t->id, $a->id, $other->id]);
            $svc->approveGroupEvent($ev, $director);

            $this->assertSame(50_000, (int) PayslipItem::where('employee_id', $t->id)->where('source', 'ge:'.$ev->id)->value('amount'));
            $this->assertSame(25_000, (int) PayslipItem::where('employee_id', $a->id)->where('source', 'ge:'.$ev->id)->value('amount'));
            $this->assertFalse(PayslipItem::where('employee_id', $other->id)->where('source', 'ge:'.$ev->id)->exists());
            $this->assertTrue($svc->participated($t, Carbon::parse('2026-10-01')));

            // Qatnashmagan xodim: guruhda tadbir bor, lekin u ro'yxatda yo'q
            $late = $this->emp('Kech', 'teacher');
            GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $late->id, 'role_in_group' => 'teacher', 'started_on' => '2026-01-01']);
            $this->assertFalse($svc->participated($late, Carbon::parse('2026-10-01')));
            $this->assertTrue($svc->participated($late, Carbon::parse('2026-09-01')));

            $svc->rejectGroupEvent($ev, $director);
            $this->assertSame(0, PayslipItem::where('source', 'ge:'.$ev->id)->count());
        });
    }

    public function test_pages_open_for_director(): void
    {
        $user = User::where('tenant_id', $this->tenant->id)->first();
        $e = $this->inTenant(fn () => $this->event());
        foreach (['app.subjects.index', 'app.events.index', 'app.events.create', 'app.group-events.index'] as $r) {
            $this->actingAs($user)->get(route($r))->assertOk();
        }
        $this->actingAs($user)->get(route('app.events.show', $e))->assertOk();
    }
}
