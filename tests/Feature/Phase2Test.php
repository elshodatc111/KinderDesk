<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\Substitution;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayScheme;
use App\Services\Payroll\SchemePayroll;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase2Test extends TestCase
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

    private function emp(string $name, string $roleKey): Employee
    {
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', $roleKey)->first();

        return Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => $name, 'role_id' => $role?->id, 'position' => $roleKey, 'status' => 'active', 'hired_on' => '2026-01-01', 'base_salary' => 0]);
    }

    /** Katta guruh: 16 bola, oktyabr 2026 ning hamma ishchi kunida keldi. */
    private function groupWithKids(int $kids = 16, string $size = 'large'): Group
    {
        $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Quyoshchalar', 'type' => 'main', 'size' => $size, 'monthly_fee' => 1_100_000, 'capacity' => 40, 'status' => 'active']);
        for ($i = 1; $i <= $kids; $i++) {
            $k = Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => "Bola $i", 'birth_date' => '2021-01-01', 'parent_phone' => '99890000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'status' => 'active', 'enrolled_on' => '2026-01-01']);
            GroupKid::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $k->id, 'started_on' => '2026-01-01']);
            for ($d = Carbon::parse('2026-10-01'); $d->lte(Carbon::parse('2026-10-31')); $d->addDay()) {
                if ($d->isWeekday()) {
                    KidAttendance::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $k->id, 'date' => $d->toDateString(), 'status' => 'present']);
                }
            }
        }

        return $g;
    }

    private function assign(Group $g, Employee $e, string $role): void
    {
        GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $e->id, 'role_in_group' => $role, 'started_on' => '2026-01-01']);
    }

    public function test_tier_tables_match_the_agreed_numbers(): void
    {
        $this->inTenant(function () {
            $tl = PayScheme::for('teacher_large');
            foreach ([5 => 770_000, 15 => 2_310_000, 16 => 2_410_000, 19 => 2_710_000, 20 => 3_080_000, 31 => 4_770_000] as $n => $v) {
                $this->assertSame($v, $tl->tier($n), "tarbiyachi katta n=$n");
            }
            $al = PayScheme::for('assistant_large');
            $this->assertSame(1_205_000, $al->tier(16));
            $this->assertSame(2_385_000, $al->tier(31));
            $ts = PayScheme::for('teacher_small');
            $this->assertSame(1_200_000, $ts->tier(6));
            $this->assertSame(4_160_000, $ts->tier(20));
            $this->assertSame(600_000, PayScheme::for('assistant_small')->tier(6));
            $cook = PayScheme::for('cook');
            $this->assertSame(2_010_000, $cook->tier(46));
            $this->assertSame(4_620_000, $cook->tier(105));
            $this->assertEqualsWithDelta(7333.33, $tl->rate(15, 21), 0.01);
        });
    }

    public function test_group_staff_get_tier_amount_when_everyone_attends(): void
    {
        $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $teacher = $this->emp('Tarbiyachi', 'teacher');
            $assistant = $this->emp('Yordamchi', 'assistant');
            $this->assign($g, $teacher, 'teacher');
            $this->assign($g, $assistant, 'assistant');
            $calc = app(PayrollCalculator::class);
            $period = Carbon::parse('2026-10-01');
            $t = $calc->calculate($teacher, $period);
            $a = $calc->calculate($assistant, $period);
            $this->assertSame('scheme', $t['strategy']);
            $this->assertSame(2_410_000, $t['base_amount']); // hamma kelsa, oylik W ga bog'liq emas
            $this->assertSame(1_205_000, $a['base_amount']);
            $this->assertSame(16, $t['kids_count']);
        });
    }

    public function test_substitute_gets_the_full_day_share(): void
    {
        $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $teacher = $this->emp('Tarbiyachi', 'teacher');
            $sub = $this->emp('O\'rindosh', 'teacher');
            $this->assign($g, $teacher, 'teacher');
            $day = '2026-10-06';
            EmployeeAttendance::create(['tenant_id' => $this->tenant->id, 'employee_id' => $teacher->id, 'date' => $day, 'status' => 'absent', 'note' => 'Kasal', 'excuse' => 'excused']);
            $calc = app(PayrollCalculator::class);
            $period = Carbon::parse('2026-10-01');

            // O'rindosh yo'q: shu kun hech kimga yozilmaydi
            $r = $calc->calculate($teacher, $period);
            $share = 2_410_000 / 22; // bir kun: plus 16 × r = T / W
            $this->assertEqualsWithDelta(2_410_000 - $share, $r['base_amount'], 1);

            Substitution::create(['tenant_id' => $this->tenant->id, 'date' => $day, 'group_id' => $g->id, 'absent_employee_id' => $teacher->id, 'substitute_employee_id' => $sub->id]);
            $s = $calc->calculate($sub, $period);
            $this->assertEqualsWithDelta($share, $s['base_amount'], 1);
            $this->assertEqualsWithDelta($share, $s['detail']['substitution'], 1);
            $this->assertEqualsWithDelta(2_410_000 - $share, $calc->calculate($teacher, $period)['base_amount'], 1);
        });
    }

    public function test_cook_is_paid_by_whole_kindergarten_attendance(): void
    {
        $this->inTenant(function () {
            $this->groupWithKids(16);
            $cook = $this->emp('Oshpaz', 'cook');
            $r = app(PayrollCalculator::class)->calculate($cook, Carbon::parse('2026-10-01'));
            $this->assertSame(704_000, $r['base_amount']); // T(16) = 16 × 44 000
        });
    }

    public function test_holiday_changes_w_but_not_the_total_when_everyone_attends(): void
    {
        $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $teacher = $this->emp('Tarbiyachi', 'teacher');
            $this->assign($g, $teacher, 'teacher');
            // 1 oktyabr — bayram: shu kuni davomat yo'q deb hisoblaymiz
            app(\App\Services\Calendar\WorkCalendar::class)->setDay(Carbon::parse('2026-10-01'), 'holiday', 'Ustozlar kuni');
            $r = app(PayrollCalculator::class)->calculate($teacher, Carbon::parse('2026-10-01'));
            $this->assertSame(2_410_000, $r['base_amount']);
            $this->assertSame(21, $r['detail']['W']);
        });
    }

    public function test_premium_advance_fine_reduce_total_and_admin_formula(): void
    {
        $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $admin = $this->emp('Administrator', 'admin');
            // 2 ta yangi bola birinchi to'lovini oktyabrda to'ladi
            foreach (Kid::limit(2)->get() as $k) {
                FinanceTransaction::create(['tenant_id' => $this->tenant->id, 'wallet' => 'desk', 'account' => 'cash', 'type' => 'kid_payment', 'amount' => 1_100_000, 'status' => 'confirmed', 'kid_id' => $k->id, 'occurred_on' => '2026-10-05']);
            }
            $r = app(PayrollCalculator::class)->calculate($admin, Carbon::parse('2026-10-01'));
            $this->assertSame(3_000_000, $r['base_amount']);
            $this->assertSame(200_000 + 16 * 25_000, $r['kpi_amount']); // 2 yangi bola + 16 faol bola
            $this->assertSame(3_600_000, $r['total']);
            $this->assertSame(16, $r['kids_count']);
        });
    }

    public function test_payroll_cycle_draft_approve_items_and_pay(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->first();
        $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $this->assign($g, $this->emp('Tarbiyachi', 'teacher'), 'teacher');
        });
        $period = '2026-10';
        $this->inTenant(fn () => FinanceTransaction::create(['tenant_id' => $this->tenant->id, 'wallet' => 'main', 'account' => 'cash', 'type' => 'income', 'amount' => 10_000_000, 'status' => 'confirmed', 'occurred_on' => '2026-10-01']));
        $this->actingAs($owner)->post(route('app.payroll.generate'), ['month' => $period])->assertRedirect();
        $slip = Payslip::withoutGlobalScopes()->first();
        $this->assertSame('draft', $slip->status);
        $this->assertSame(2_410_000, $slip->base_amount);

        // Qoralamani to'lab bo'lmaydi
        $this->actingAs($owner)->post(route('app.payroll.pay', $slip), ['amount' => '100000', 'account' => 'cash'])->assertSessionHas('error');

        // Premiya va jarima qatori
        $this->actingAs($owner)->post(route('app.payroll.items.store'), ['employee_id' => $slip->employee_id, 'month' => $period, 'type' => 'premium', 'amount' => '300000', 'reason' => 'Bayram'])->assertSessionHas('success');
        $this->actingAs($owner)->post(route('app.payroll.items.store'), ['employee_id' => $slip->employee_id, 'month' => $period, 'type' => 'fine', 'amount' => '50000', 'reason' => 'Test'])->assertSessionHas('success');
        $slip->refresh();
        $this->assertSame(300_000, $slip->bonus);
        $this->assertSame(50_000, $slip->penalty);
        $kpi = $slip->kpi_amount; // hisobot zanjiri/tadbir yoqilmagan: shartlar bajarilgan hisoblanadi
        $this->assertSame(2_410_000 + $kpi + 300_000 - 50_000, $slip->total);

        // Tasdiqlash, to'lash, qayta ochish mumkin emas (to'lov boshlangach)
        $this->actingAs($owner)->post(route('app.payroll.approve', $slip))->assertSessionHas('success');
        $this->assertSame('approved', $slip->fresh()->status);
        $this->actingAs($owner)->post(route('app.payroll.items.store'), ['employee_id' => $slip->employee_id, 'month' => $period, 'type' => 'premium', 'amount' => '1000'])->assertSessionHas('error');
        $this->actingAs($owner)->post(route('app.payroll.reopen', $slip))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('app.payroll.approve', $slip))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('app.payroll.pay', $slip), ['amount' => '1000000', 'account' => 'cash'])->assertSessionHas('success');
        $this->actingAs($owner)->post(route('app.payroll.reopen', $slip))->assertSessionHas('error');
    }

    public function test_advance_is_deducted_and_recorded_as_expense(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->first();
        $emp = $this->inTenant(function () {
            $g = $this->groupWithKids(16);
            $e = $this->emp('Tarbiyachi', 'teacher');
            $this->assign($g, $e, 'teacher');
            // direktor hisobida mablag' bo'lsin
            FinanceTransaction::create(['tenant_id' => $this->tenant->id, 'wallet' => 'main', 'account' => 'cash', 'type' => 'income', 'amount' => 10_000_000, 'status' => 'confirmed', 'occurred_on' => '2026-10-01']);

            return $e;
        });
        $this->actingAs($owner)->post(route('app.payroll.items.store'), ['employee_id' => $emp->id, 'month' => '2026-10', 'type' => 'advance', 'amount' => '500000', 'account' => 'cash'])->assertSessionHas('success');
        $this->actingAs($owner)->post(route('app.payroll.generate'), ['month' => '2026-10'])->assertRedirect();
        $slip = Payslip::withoutGlobalScopes()->first();
        $this->assertSame(500_000, $slip->advance);
        $this->assertSame($slip->base_amount + $slip->kpi_amount - 500_000, $slip->total);
        $this->assertTrue(FinanceTransaction::withoutGlobalScopes()->where('type', 'salary')->where('amount', -500_000)->exists());
    }

    public function test_payroll_draft_command_and_pages(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->first();
        $this->inTenant(function () {
            $g = $this->groupWithKids(6);
            $this->assign($g, $this->emp('Tarbiyachi', 'teacher'), 'teacher');
        });
        $this->artisan('payroll:draft', ['--force' => true, '--month' => '2026-10'])->assertSuccessful();
        $this->assertSame(1, Payslip::withoutGlobalScopes()->count());
        foreach (['app.payroll.index', 'app.payroll.schemes', 'app.payroll.complaints', 'app.substitutions.index', 'app.groups.create'] as $r) {
            $this->actingAs($owner)->get(route($r))->assertOk();
        }
        $this->actingAs($owner)->get(route('app.payroll.detail', Payslip::withoutGlobalScopes()->first()))->assertOk()->assertJsonPath('slip.strategy', 'scheme');
    }

    public function test_complaint_removes_the_satisfied_kpi(): void
    {
        $this->inTenant(function () {
            $g = $this->groupWithKids(6);
            $t = $this->emp('Tarbiyachi', 'teacher');
            $this->assign($g, $t, 'teacher');
            $period = Carbon::parse('2026-10-01');
            $before = app(PayrollCalculator::class)->calculate($t, $period)['kpi_amount'];
            \App\Models\Complaint::create(['tenant_id' => $this->tenant->id, 'date' => '2026-10-10', 'employee_id' => $t->id, 'note' => 'Shikoyat']);
            $after = app(PayrollCalculator::class)->calculate($t, $period)['kpi_amount'];
            $this->assertSame(100_000, $before - $after);
        });
    }
}
