<?php

namespace Tests\Feature;

use App\Models\CalendarDay;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidExcuse;
use App\Models\User;
use App\Services\Calendar\WorkCalendar;
use App\Services\Finance\ChargeService;
use App\Services\Finance\DiscountService;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase1Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234500', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
    }

    private function inTenant(callable $fn)
    {
        return app(TenantContext::class)->run($this->tenant, $fn);
    }

    private function group(string $name, int $fee): Group
    {
        return Group::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'type' => 'main', 'monthly_fee' => $fee, 'capacity' => 20, 'status' => 'active']);
    }

    private function kid(string $name, string $phone, Group $g, ?int $custom = null): Kid
    {
        $k = Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => $name, 'birth_date' => '2021-01-01', 'parent_phone' => $phone, 'parent_name' => 'Ota', 'status' => 'active', 'custom_fee' => $custom]);
        GroupKid::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $k->id, 'started_on' => '2026-01-01']);

        return $k;
    }

    public function test_calendar_defaults_weekends_off_and_holidays_not_counted(): void
    {
        $this->inTenant(function () {
            $cal = app(WorkCalendar::class);
            $oct = Carbon::parse('2026-10-01');
            $this->assertSame(22, count($cal->workingDays($oct))); // oktyabr 2026: 22 ta Du–Ju
            $cal->setDay(Carbon::parse('2026-10-01'), 'holiday', 'Ustozlar kuni');
            $this->assertSame(21, $cal->countWorking($oct));
            $cal->setDay(Carbon::parse('2026-10-03'), 'working', 'Shanba ish kuni');
            $this->assertSame(22, $cal->countWorking($oct));
            $cal->setDay(Carbon::parse('2026-10-03'), null); // avtomatik holatga qaytdi
            $this->assertSame(21, $cal->countWorking($oct));
        });
    }

    public function test_month_confirmation_resets_when_a_day_changes(): void
    {
        $this->inTenant(function () {
            $cal = app(WorkCalendar::class);
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            $m = Carbon::parse('2026-10-01');
            $cal->confirm($m, $owner);
            $this->assertTrue($cal->isConfirmed($m));
            $cal->setDay(Carbon::parse('2026-10-14'), 'holiday');
            $this->assertFalse($cal->isConfirmed($m));
        });
    }

    public function test_fixed_monthly_charge_ignores_attendance(): void
    {
        $this->inTenant(function () {
            $g = $this->group('Katta', 1_100_000);
            $k = $this->kid('Ali', '998900000001', $g);
            app(ChargeService::class)->chargeKid($k, Carbon::parse('2026-10-01'));
            $this->assertSame(-1_100_000, $k->fresh()->balance);
            // takror ishga tushirish ikki marta yechmaydi
            app(ChargeService::class)->chargeKid($k, Carbon::parse('2026-10-01'));
            $this->assertSame(-1_100_000, $k->fresh()->balance);
        });
    }

    public function test_sibling_discount_applies_from_next_month_to_cheaper_kid(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('sibling.second_percent', 10);
            $a = $this->kid('Ali', '998900000001', $this->group('Kichik', 1_300_000));
            $b = $this->kid('Vali', '998900000001', $this->group('Katta', 1_100_000));
            $d = app(DiscountService::class);
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            $this->assertCount(1, $d->suggestions());
            $family = $d->linkFamily([$a->id, $b->id], $owner);
            // Tasdiqlangan oy: chegirma yo'q (keyingi oydan)
            $thisMonth = today()->startOfMonth();
            $this->assertSame(1, $d->siblingRank($b->fresh(), $thisMonth));
            $next = today()->addMonthNoOverflow()->startOfMonth();
            $this->assertSame(2, $d->siblingRank($b->fresh(), $next));
            $this->assertSame(1, $d->siblingRank($a->fresh(), $next));

            $cs = app(ChargeService::class);
            $cs->chargeKid($a->fresh(), $next);
            $cs->chargeKid($b->fresh(), $next);
            $this->assertSame(-1_300_000, $a->fresh()->balance);
            $this->assertSame(-990_000, $b->fresh()->balance); // 1 100 000 − 10 %
            $this->assertCount(0, $d->suggestions());
        });
    }

    public function test_no_sibling_discount_for_custom_fee_and_respects_apply_to(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('sibling.second_percent', 10);
            $a = $this->kid('Ali', '998900000001', $this->group('Kichik', 1_300_000));
            $b = $this->kid('Vali', '998900000001', $this->group('Katta', 1_100_000), 900_000);
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            app(DiscountService::class)->linkFamily([$a->id, $b->id], $owner);
            $next = today()->addMonthNoOverflow()->startOfMonth();
            app(ChargeService::class)->chargeKid($b->fresh(), $next);
            $this->assertSame(-900_000, $b->fresh()->balance);

            // Asosiy guruh chegirmadan chiqarilsa, hech kimga qo'llanmaydi
            $c = $this->kid('Sami', '998900000009', $this->group('Boshqa', 1_000_000));
            $e = $this->kid('Jasur', '998900000009', $this->group('Boshqa2', 900_000));
            app(DiscountService::class)->linkFamily([$c->id, $e->id], $owner);
            $this->tenant->setSetting('sibling.apply_to', ['club']);
            app(ChargeService::class)->chargeKid($e->fresh(), $next);
            $this->assertSame(-900_000, $e->fresh()->balance);
        });
    }

    public function test_sibling_discount_applies_to_evening_group_and_cancels(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('sibling.second_percent', 20);
            $a = $this->kid('Ali', '998900000001', $this->group('Kichik', 1_300_000));
            $b = $this->kid('Vali', '998900000001', $this->group('Katta', 1_100_000));
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            app(DiscountService::class)->linkFamily([$a->id, $b->id], $owner);
            $ev = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Kechki', 'type' => 'evening', 'daily_fee' => 25000, 'monthly_fee' => 0, 'capacity' => 5, 'status' => 'active']);
            $day = today()->addMonthNoOverflow()->startOfMonth()->addDays(2);
            $cs = app(ChargeService::class);
            $cs->syncDaily($b->fresh(), $ev, $day, 'present');
            $this->assertSame(-20_000, $b->fresh()->balance); // 25 000 − 20 %
            $cs->syncDaily($b->fresh(), $ev, $day, 'absent');
            $this->assertSame(0, $b->fresh()->balance);
        });
    }

    public function test_excused_absence_discount_after_sibling_and_director_approval(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('sibling.second_percent', 10);
            $a = $this->kid('Ali', '998900000001', $this->group('Kichik', 1_300_000));
            $b = $this->kid('Vali', '998900000001', $this->group('Katta', 1_100_000));
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            $d = app(DiscountService::class);
            $d->linkFamily([$a->id, $b->id], $owner);
            $next = today()->addMonthNoOverflow()->startOfMonth();
            // W = 22 bo'lishi uchun oktyabr 2026 ni olamiz va oilani shu oydan kuchga kiritamiz
            \App\Models\Family::query()->update(['effective_from' => '2026-10-01']);
            $oct = Carbon::parse('2026-10-01');
            app(ChargeService::class)->chargeKid($b->fresh(), $oct);
            $this->assertSame(-990_000, $b->fresh()->balance);

            $e = $d->enterExcuse($b->fresh(), $oct, 3, 'Kasal', $owner);
            $this->assertSame(0, $b->fresh()->balance + 990_000); // tasdiqdan oldin o'zgarmaydi
            $this->assertTrue($d->approveExcuse($e, $owner));
            // (1 100 000 − 110 000) / 22 × 3 = 135 000
            $this->assertSame(135_000, $e->fresh()->amount);
            $this->assertSame(-855_000, $b->fresh()->balance);
            $this->assertFalse($d->approveExcuse($e->fresh(), $owner)); // ikki marta emas
            $this->assertSame(-855_000, $b->fresh()->balance);
        });
    }

    public function test_staff_fine_only_for_unexcused_and_note_required(): void
    {
        $this->inTenant(function () {
            $this->tenant->setSetting('attendance.fine_late', 20_000);
            $this->tenant->setSetting('attendance.fine_absent', 100_000);
            $e = Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Tarbiyachi', 'position' => 'teacher', 'status' => 'active', 'base_salary' => 3_000_000, 'salary_type' => 'fixed', 'scheme_key' => 'fixed']);
            $m = '2026-10-';
            EmployeeAttendance::create(['tenant_id' => $this->tenant->id, 'employee_id' => $e->id, 'date' => $m.'01', 'status' => 'late', 'note' => 'Yo\'l', 'excuse' => 'unexcused']);
            EmployeeAttendance::create(['tenant_id' => $this->tenant->id, 'employee_id' => $e->id, 'date' => $m.'02', 'status' => 'late', 'note' => 'Shifokor', 'excuse' => 'excused']);
            EmployeeAttendance::create(['tenant_id' => $this->tenant->id, 'employee_id' => $e->id, 'date' => $m.'05', 'status' => 'absent', 'note' => 'Sababsiz', 'excuse' => 'unexcused']);
            EmployeeAttendance::create(['tenant_id' => $this->tenant->id, 'employee_id' => $e->id, 'date' => $m.'06', 'status' => 'absent', 'note' => 'Hali belgilanmagan']);
            $r = app(PayrollCalculator::class)->calculate($e, Carbon::parse('2026-10-01'));
            $this->assertSame(120_000, $r['penalty']); // 20 000 + 100 000
            $this->assertSame(2_880_000, $r['total']);
        });
    }

    public function test_staff_attendance_requires_note_for_late_and_absent(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->first();
        $emp = $this->inTenant(fn () => Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Xodim', 'position' => 'teacher', 'status' => 'active']));
        $payload = ['date' => today()->toDateString(), 'marks' => [$emp->id => ['status' => 'absent', 'note' => '']]];
        $this->actingAs($owner)->postJson(route('app.staff-attendance.store'), $payload)->assertStatus(422);
        $payload['marks'][$emp->id]['note'] = 'Kasal';
        $payload['marks'][$emp->id]['excuse'] = 'excused';
        $this->actingAs($owner)->postJson(route('app.staff-attendance.store'), $payload)->assertOk();
        $this->assertSame('excused', EmployeeAttendance::withoutGlobalScopes()->where('employee_id', $emp->id)->first()->excuse);
    }

    public function test_pages_render(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->first();
        foreach (['app.calendar.index', 'app.families.index', 'app.excuses.index', 'app.staff-attendance.index', 'app.settings.index', 'app.dashboard'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk();
        }
        $this->actingAs($owner)->get(route('app.settings.roles.create'))->assertOk()->assertSee('breadcrumbs', false)->assertSee('Lavozimlar');
    }
}
