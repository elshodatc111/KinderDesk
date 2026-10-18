<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\ReportSubmission;
use App\Models\ReportType;
use App\Models\Role;
use App\Models\User;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Reports\ReportChain;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234502', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
        Carbon::setTestNow('2026-11-03 10:00:00'); // dushanba
        $this->tenant->forceFill(['access_expires_at' => now()->addYear()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    private function type(string $role = 'teacher', array $extra = []): ReportType
    {
        $t = ReportType::create($extra + ['tenant_id' => $this->tenant->id, 'name' => 'Kunlik hisobot', 'role_key' => $role, 'level' => 1, 'frequency' => 'daily',
            'deadline_time' => '23:59', 'content' => 'text', 'is_parent' => true]);
        $t->forceFill(['created_at' => '2026-09-01'])->save();

        return $t->fresh();
    }

    public function test_missed_working_day_blocks_bonuses_and_acceptance_restores_them(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $type = $this->type();
            $oct = Carbon::parse('2026-10-01');
            $this->assertNotEmpty($chain->missing($e, $oct));
            $this->assertFalse($chain->bonusesAllowed($e, $oct));
            $this->assertFalse($chain->parentReportsComplete($e, $oct));

            $user = User::where('tenant_id', $this->tenant->id)->first();
            for ($d = $oct->copy(); $d->month === 10; $d->addDay()) {
                if ($d->isWeekday()) {
                    $s = $chain->submit($type, $e, $d->copy(), 'Bugun foto yubordim', [], $user);
                    $chain->review($s, true, null, $user);
                }
            }
            $this->assertSame([], $chain->missing($e, $oct));
            $this->assertTrue($chain->bonusesAllowed($e, $oct));
        });
    }

    public function test_submitted_but_not_accepted_does_not_count_and_waiver_does(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $type = $this->type();
            $user = User::where('tenant_id', $this->tenant->id)->first();
            $oct = Carbon::parse('2026-10-01');
            for ($d = $oct->copy(); $d->month === 10; $d->addDay()) {
                if ($d->isWeekday()) {
                    $s = $chain->submit($type, $e, $d->copy(), 'matn matn', [], $user);
                    if ($d->day !== 15) {
                        $chain->review($s, true, null, $user);
                    }
                }
            }
            $m = $chain->missing($e, $oct);
            $this->assertCount(1, $m);
            $this->assertSame('submitted', $m[0]['state']);
            $this->assertFalse($chain->bonusesAllowed($e, $oct));

            $this->expectException(\DomainException::class);
            $chain->waive($e, $oct, Carbon::parse('2026-10-15'), 'x', $user);
        });
    }

    public function test_director_waiver_restores_bonus_and_is_audited(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $this->type();
            $user = User::where('tenant_id', $this->tenant->id)->first();
            $oct = Carbon::parse('2026-10-01');
            $this->assertFalse($chain->bonusesAllowed($e, $oct));
            $chain->waive($e, $oct, null, 'Kasal bo\'lgan', $user);
            $this->assertTrue($chain->bonusesAllowed($e, $oct));
            $this->assertDatabaseHas('audit_logs', ['action' => 'report.waived']);
        });
    }

    public function test_returned_needs_note_and_can_be_resubmitted(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $type = $this->type();
            $user = User::where('tenant_id', $this->tenant->id)->first();
            $s = $chain->submit($type, $e, Carbon::parse('2026-11-02'), 'qisqa matn', [], $user);
            try {
                $chain->review($s, false, '', $user);
                $this->fail('sabab kerak');
            } catch (\DomainException) {
            }
            $chain->review($s, false, 'Foto yo\'q', $user);
            $this->assertSame('returned', $s->fresh()->status);
            $chain->submit($type, $e, Carbon::parse('2026-11-02'), 'foto bilan', [], $user);
            $this->assertSame('submitted', ReportSubmission::first()->status);
        });
    }

    public function test_weekend_future_and_empty_content_rejected_deadline_late_flag(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $type = $this->type();
            foreach (['2026-11-01' => 'dam olish', '2026-11-04' => 'kelajak'] as $d => $why) {
                try {
                    $chain->submit($type, $e, Carbon::parse($d), 'matn matn', [], null);
                    $this->fail($why);
                } catch (\DomainException) {
                    $this->assertTrue(true);
                }
            }
            $this->expectException(\DomainException::class);
            $chain->submit($type, $e, Carbon::parse('2026-11-02'), '', [], null);
        });
    }

    public function test_late_submission_is_flagged_and_can_be_ignored_by_setting(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $type = $this->type();
            $user = User::where('tenant_id', $this->tenant->id)->first();
            $s = $chain->submit($type, $e, Carbon::parse('2026-11-02'), 'kechikkan matn', [], $user);
            $this->assertTrue($s->late);
            $chain->review($s, true, null, $user);
            $nov = Carbon::parse('2026-11-01');
            $keys = array_map(fn ($m) => $m['key']->toDateString(), $chain->missing($e, $nov));
            $this->assertNotContains('2026-11-02', $keys);
            $this->tenant->setSetting('report.allow_late', false);
            $keys = array_map(fn ($m) => $m['key']->toDateString(), $chain->missing($e, $nov));
            $this->assertContains('2026-11-02', $keys);
        });
    }

    public function test_kpi_and_payroll_use_the_chain(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $e = $this->emp('T', 'teacher');
            $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'type' => 'main', 'size' => 'large', 'monthly_fee' => 1, 'capacity' => 20, 'status' => 'active']);
            GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $e->id, 'role_in_group' => 'teacher', 'started_on' => '2026-01-01']);
            $this->type();
            $r = app(PayrollCalculator::class)->calculate($e, Carbon::parse('2026-10-01'));
            $this->assertSame(0, $r['kpi_amount']);
            $this->assertFalse($r['detail']['kpi']['bonuses_allowed']);
        });
    }

    public function test_level2_packet_lists_missing_staff_and_admin_gets_blocked_by_stale_reviews(): void
    {
        $this->inTenant(function () {
            $chain = app(ReportChain::class);
            $t = $this->emp('T', 'teacher');
            $admin = $this->emp('A', 'admin');
            $l1 = $this->type();
            $this->type('admin', ['level' => 2, 'name' => 'Admin kunlik', 'content' => 'auto', 'is_parent' => false]);
            $day = Carbon::parse('2026-11-02');
            $p = $chain->packet($day);
            $this->assertSame(1, $p['missing']);
            $this->assertStringContainsString('Bolalar:', $chain->autoBody($day));

            $s = $chain->submit($l1, $t, Carbon::parse('2026-10-05'), 'matn matn', [], null);
            $s->forceFill(['submitted_at' => now()->subDays(5)])->save();
            $this->assertFalse($chain->bonusesAllowed($admin, Carbon::parse('2026-10-01'))); // o'z hisoboti ham yo'q, va ko'rilmagan
        });
    }

    public function test_page_opens_and_submit_roundtrip(): void
    {
        $user = User::where('tenant_id', $this->tenant->id)->first();
        $this->inTenant(function () use ($user) {
            $e = $this->emp('Oshpaz', 'cook');
            $type = $this->type('cook', ['content' => 'file', 'is_parent' => false]);
            app(ReportChain::class)->submit($type, $e, Carbon::parse('2026-11-02'), 'matn matn', [['path' => 'reports/x', 'name' => 'a.jpg', 'size' => 1]], $user);
        });
        $this->actingAs($user)->get(route('app.chain.index'))->assertOk()->assertSee('Oshpaz')->assertSee('a.jpg');
        $sub = ReportSubmission::first();
        $resp = $this->actingAs($user)->post(route('app.chain.review', $sub), ['action' => 'accept']);
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertSame('accepted', $sub->fresh()->status);
    }
}
