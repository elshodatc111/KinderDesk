<?php

namespace Tests\Feature;

use App\Models\FinanceTransaction;
use App\Models\Group;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidAttendance;
use App\Models\Subsidy;
use App\Models\User;
use App\Services\Attendance\AttendanceStats;
use App\Services\Finance\FinanceService;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase8Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow('2026-10-14 10:00:00');
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234577', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
        $this->owner = User::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function in(callable $fn)
    {
        return app(TenantContext::class)->run($this->tenant, $fn);
    }

    private function group(int $kids = 4): Group
    {
        $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Quyosh', 'type' => 'main', 'size' => 'large', 'monthly_fee' => 1_000_000, 'capacity' => 20, 'status' => 'active']);
        for ($i = 1; $i <= $kids; $i++) {
            $k = Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => "Bola $i", 'birth_date' => '2021-01-01', 'parent_phone' => '99890000000'.$i, 'status' => 'active', 'enrolled_on' => '2026-01-01']);
            GroupKid::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $k->id, 'started_on' => '2026-01-01']);
        }

        return $g;
    }

    private function eskizPw(): ?string
    {
        config(['services.eskiz.password' => null]);
        \App\Support\Site\PlatformConfig::apply();

        return config('services.eskiz.password');
    }

    public function test_kid_attendance_only_for_today(): void
    {
        $g = $this->in(fn () => $this->group());
        $kid = $this->in(fn () => Kid::first());
        $this->actingAs($this->owner);

        $this->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-13', 'marks' => [$kid->id => 'present']])->assertStatus(422);
        $this->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'present']])->assertOk();
        $this->assertSame(1, $this->in(fn () => KidAttendance::count()));
    }

    public function test_staff_attendance_only_for_today(): void
    {
        $this->actingAs($this->owner);
        $this->postJson(route('app.staff-attendance.store'), ['date' => '2026-10-10', 'marks' => []])->assertStatus(422);
    }

    public function test_attendance_pages_and_stats(): void
    {
        $g = $this->in(fn () => $this->group(4));
        $ids = $this->in(fn () => Kid::orderBy('id')->pluck('id'));
        $this->in(function () use ($g, $ids) {
            foreach (['present', 'present', 'absent', 'sick'] as $i => $st) {
                KidAttendance::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $ids[$i], 'date' => '2026-10-13', 'status' => $st]);
            }
        });
        $day = $this->in(fn () => app(AttendanceStats::class)->day(Carbon::parse('2026-10-13')));
        $this->assertSame(4, $day['totals']['total']);
        $this->assertSame(2, $day['totals']['present']);
        $this->assertSame(1, $day['totals']['absent']);
        $this->assertSame(1, $day['totals']['sick']);
        $this->assertEquals(50, $day['totals']['rate']);

        $this->actingAs($this->owner);
        $this->get(route('app.attendance.index'))->assertOk()->assertSee('Quyosh');
        $this->get(route('app.attendance.index', ['view' => 'calendar', 'month' => '2026-10']))->assertOk();
        $this->get(route('app.attendance.index', ['view' => 'calendar', 'month' => '2026-10', 'date' => '2026-10-13']))->assertOk()->assertSee('Quyosh');
        $this->get(route('app.attendance.index', ['group' => $g->id]))->assertOk();
        $this->get(route('app.attendance.index', ['group' => $g->id, 'date' => '2026-10-13']))->assertOk();
        $this->get(route('app.staff-attendance.index', ['date' => '2026-10-10']))->assertOk();
    }

    public function test_desk_expense_cash_only_and_fund_desk(): void
    {
        $fin = app(FinanceService::class);
        $this->actingAs($this->owner);
        $this->in(function () use ($fin) {
            $fin->record('deposit', 'cash', 500_000, null, 'Direktor mablag\'i', $this->owner);
        });
        $this->assertSame(500_000, $this->in(fn () => $fin->balances('main')['cash']));

        // Kassada pul yo'q: xarajat rad etiladi
        $this->post(route('app.desk.expense'), ['amount' => '100000', 'account' => 'card'])->assertSessionHas('error');

        $this->post(route('app.finance.fund-desk'), ['amount' => '300 000'])->assertSessionHas('success');
        $this->assertSame(200_000, $this->in(fn () => $fin->balances('main')['cash']));
        $this->assertSame(300_000, $this->in(fn () => $fin->balances('desk')['cash']));

        // 'account' => 'card' yuborilsa ham xarajat naqddan yoziladi
        $this->post(route('app.desk.expense'), ['amount' => '100000', 'account' => 'card'])->assertSessionHas('success');
        $this->assertSame(200_000, $this->in(fn () => $fin->balances('desk')['cash']));
        $this->assertSame(0, $this->in(fn () => $fin->balances('desk')['card']));

        // Naqd yetmasa — berib bo'lmaydi
        $this->post(route('app.finance.fund-desk'), ['amount' => '999999999'])->assertSessionHas('error');
    }

    public function test_subsidy_calculated_separately_and_received_once(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('app.finance.subsidy.store'), ['title' => 'Oylik', 'period' => '2026-10', 'kids_count' => 20, 'rate' => '50 000'])->assertSessionHas('success');
        $s = $this->in(fn () => Subsidy::first());
        $this->assertSame(1_000_000, $s->expected);
        $this->assertSame(0, $this->in(fn () => app(FinanceService::class)->balances('main')['bank']));

        $this->post(route('app.finance.subsidy.receive', $s), ['amount' => '950 000', 'account' => 'bank'])->assertSessionHas('success');
        $this->assertSame(950_000, $this->in(fn () => app(FinanceService::class)->balances('main')['bank']));
        $this->post(route('app.finance.subsidy.receive', $s), ['amount' => '950000', 'account' => 'bank'])->assertSessionHas('error');
        $this->assertSame(950_000, $this->in(fn () => app(FinanceService::class)->balances('main')['bank']));
        // Daromadga (dashboard) qo'shilmaydi
        $this->assertSame(0, $this->in(fn () => FinanceTransaction::whereIn('type', ['kid_payment', 'income'])->count()));
    }

    public function test_finance_pages_and_accounts(): void
    {
        $this->actingAs($this->owner);
        $this->post(route('app.finance.accounts.store'), ['kind' => 'card', 'title' => 'Asosiy', 'number' => '8600123412341234', 'holder' => 'Direktor'])->assertRedirect();
        foreach (['overview', 'accounts', 'subsidy', 'journal', 'handovers'] as $tab) {
            $this->get(route('app.finance.index', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('app.finance.index', ['tab' => 'accounts']))->assertSee('8600123412341234');
        $this->get(route('app.desk.index'))->assertOk();
    }

    public function test_notifications_follow_permissions(): void
    {
        $notify = app(\App\Services\NotificationService::class);
        $role = \App\Models\Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('key', 'cashier')->first();
        $cashier = User::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kassir', 'phone' => '998901234578', 'password' => 'Secret123', 'role_id' => $role->id, 'status' => 'active', 'locale' => 'uz']);

        $this->in(function () use ($notify) {
            $notify->toTenantUsers($this->tenant, 'subscription.manage', 'Obuna', 'x');
            $notify->toTenantUsers($this->tenant, 'desk.handover', 'Kassa', 'x');
            $notify->toUser($this->owner, 'Hammaga', 'x');
        });
        $cashier = $cashier->fresh();
        $this->assertSame(1, $cashier->visibleNotifications()->count()); // faqat «Kassa»
        $this->assertSame(1, $cashier->unreadVisibleNotificationCount());
        $this->assertSame(4, $this->owner->fresh()->visibleNotifications()->count());

        $this->actingAs($cashier)->getJson(route('app.notifications.recent'))->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_stats_page_and_service(): void
    {
        $g = $this->in(fn () => $this->group(4));
        $ids = $this->in(fn () => Kid::orderBy('id')->pluck('id'));
        $this->in(function () use ($g, $ids) {
            foreach (['present', 'present', 'absent', 'sick'] as $i => $st) {
                KidAttendance::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $ids[$i], 'date' => '2026-10-13', 'status' => $st]);
            }
            app(FinanceService::class)->record('income', 'cash', 200_000, 'Boshqa', null, $this->owner);
            app(FinanceService::class)->record('expense', 'cash', 50_000, 'Oziq-ovqat', null, $this->owner);
        });

        foreach (['day', 'week', 'month', 'year'] as $p) {
            $d = $this->in(fn () => app(\App\Services\Stats\StatsService::class)->build($p, true, true, true, true));
            $this->assertCount(count($d['labels']), $d['attendance']['present']);
            $this->assertSame(200_000, $d['kpi']['income']);
            $this->assertSame(50_000, $d['kpi']['expense']);
            $this->assertEquals(50.0, (float) $d['kpi']['attendance']);
            $this->actingAs($this->owner)->get(route('app.stats.index', ['period' => $p]))->assertOk()->assertSee('statsPage', false);
        }
        $d = $this->in(fn () => app(\App\Services\Stats\StatsService::class)->build('day', true, true, true, true));
        $this->assertSame(30, count($d['labels']));
        $this->assertSame(4, $d['kids']['active']);
    }

    public function test_super_admin_settings_connections_faq_legal_profile(): void
    {
        $admin = User::where('is_super_admin', true)->first();
        $this->actingAs($admin);

        // Ulanishlar bazada, parol shifrlangan va sahifada ko'rinmaydi
        $this->put(route('admin.settings.connections'), ['sms_driver' => 'eskiz', 'eskiz_email' => 'a@b.uz', 'eskiz_password' => 'SuperSecretPw', 'payme_merchant_id' => 'M123', 'payme_key' => 'PaymeKey999'])->assertSessionHasNoErrors();
        $this->assertSame('SuperSecretPw', $this->eskizPw());
        $this->assertNotSame('SuperSecretPw', \App\Models\PlatformSetting::where('key', 'conn.eskiz_password')->value('value'));
        $this->get(route('admin.settings.index', ['tab' => 'conn']))->assertOk()->assertDontSee('SuperSecretPw')->assertDontSee('PaymeKey999');
        // bo'sh qoldirilsa eski qiymat saqlanadi
        $this->put(route('admin.settings.connections'), ['sms_driver' => 'eskiz', 'eskiz_password' => ''])->assertSessionHasNoErrors();
        $this->assertSame('SuperSecretPw', $this->eskizPw());

        // Qo'llab-quvvatlash va footer
        $this->put(route('admin.settings.update'), ['payment_min_topup' => 10000, 'support_phone' => '+998 90 111 22 33', 'support_telegram' => '@kdsupport'])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('kdsupport')->assertSee('998');

        // FAQ
        $this->put(route('admin.settings.faq'), ['faq' => [['q_uz' => 'Savol bir?', 'q_ru' => 'Вопрос один?', 'a_uz' => 'Javob bir', 'a_ru' => 'Ответ один']]])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Вопрос один?');

        // Huquqiy matn: standart namuna + tahrir + qaytarish
        $this->get(route('legal', 'oferta'))->assertOk();
        $this->put(route('admin.settings.legal'), ['page' => 'oferta', 'text_uz' => "# Mening ofertam\n\nMatn", 'text_ru' => "# Mening ofertam\n\nMatn"])->assertSessionHasNoErrors();
        $this->get(route('legal', 'oferta'))->assertSee('Mening ofertam');
        $this->delete(route('admin.settings.legal.reset'), ['page' => 'oferta']);
        $this->get(route('legal', 'oferta'))->assertDontSee('Mening ofertam');

        // Profil va parol
        $this->get(route('admin.profile.index'))->assertOk();
        $this->put(route('admin.profile.password'), ['current_password' => 'wrong', 'password' => 'NewPassw0rdX', 'password_confirmation' => 'NewPassw0rdX'])->assertSessionHasErrors();
    }

    public function test_impersonating_admin_is_not_blocked_on_admin_pages(): void
    {
        $admin = User::where('is_super_admin', true)->first();
        $this->actingAs($admin)->post(route('admin.tenants.impersonate', $this->tenant))->assertRedirect();
        $this->get(route('admin.tenants.index'))->assertRedirect(); // admin tiklanadi va qayta yo'naltiriladi
        $this->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_subscription_has_no_auto_renew(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('app.subscription.auto-renew'));
        $this->actingAs($this->owner)->get(route('app.subscription.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('app.settings.index', ['tab' => 'org']))->assertOk()->assertSee('readonly', false);
    }
}
