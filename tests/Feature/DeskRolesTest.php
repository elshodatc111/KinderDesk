<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Kid;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\ChargeService;
use App\Services\Finance\FinanceService;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeskRolesTest extends TestCase
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

    private function kid(): Kid
    {
        return Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Ali Valiyev', 'birth_date' => '2021-01-01', 'parent_phone' => '998901234567', 'parent_name' => 'Ota', 'status' => 'active']);
    }

    public function test_daily_group_charges_only_present_days_and_cancels(): void
    {
        $this->inTenant(function () {
            $kid = $this->kid();
            $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Kechki', 'type' => 'evening', 'daily_fee' => 25000, 'monthly_fee' => 0, 'capacity' => 5, 'status' => 'active']);
            $cs = app(ChargeService::class);
            $d = today();
            $cs->syncDaily($kid, $g, $d, 'present');
            $cs->syncDaily($kid, $g, $d, 'present'); // takror — ikki marta yechilmasin
            $this->assertSame(-25000, $kid->fresh()->balance);
            $cs->syncDaily($kid, $g, $d, 'absent');
            $this->assertSame(0, $kid->fresh()->balance);
        });
    }

    public function test_handover_confirm_moves_money_from_desk_to_main(): void
    {
        $this->inTenant(function () {
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            $fin = app(FinanceService::class);
            $fin->receivePayment($this->kid(), 500000, 'cash', null, $owner);
            $this->assertSame(500000, $fin->deskAvailable('cash'));
            $h = $fin->requestHandover('cash', 300000, null, $owner);
            $this->assertSame(200000, $fin->deskAvailable('cash')); // kutilayotgani band
            $this->assertSame(500000, $fin->balances('desk')['cash']); // tasdiqlanmaguncha kassada
            $fin->confirmHandover($h, $owner);
            $this->assertSame(200000, $fin->deskAvailable('cash'));
            $this->assertSame(300000, $fin->balances('main')['cash']);
            $this->expectException(\DomainException::class);
            $fin->confirmHandover($h->fresh(), $owner);
        });
    }

    public function test_handover_reject_keeps_money_and_over_amount_fails(): void
    {
        $this->inTenant(function () {
            $owner = User::where('tenant_id', $this->tenant->id)->first();
            $fin = app(FinanceService::class);
            $fin->receivePayment($this->kid(), 100000, 'cash', null, $owner);
            $h = $fin->requestHandover('cash', 100000, null, $owner);
            $fin->rejectHandover($h, $owner, 'xato');
            $this->assertSame(100000, $fin->deskAvailable('cash')); // rad etildi — band bo'shadi
            $this->assertSame(0, $fin->balances('main')['cash']);
            $this->expectException(\DomainException::class);
            $fin->requestHandover('cash', 999999, null, $owner);
        });
    }

    public function test_director_creates_position_with_permissions(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->where('is_owner', true)->first();
        $this->actingAs($owner)->post(route('app.settings.roles.store'), ['name' => 'Kassir', 'permissions' => ['desk.view', 'kids.view']])->assertRedirect();
        $role = Role::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Kassir')->first();
        $this->assertNotNull($role);
        $this->assertContains('desk.view', $role->permissions);
        $this->assertContains('kids.view', $role->permissions);
        $this->actingAs($owner)->delete(route('app.settings.roles.destroy', $role))->assertRedirect();
        $this->assertNull(Role::withoutGlobalScopes()->find($role->id));
    }

    public function test_system_health_is_super_admin_only(): void
    {
        $owner = User::where('tenant_id', $this->tenant->id)->where('is_owner', true)->first();
        $this->actingAs($owner)->get('/admin/tizim')->assertStatus(403);
    }

    public function test_language_is_negotiated_and_switchable(): void
    {
        // Brauzer tili ruscha bo'lsa — ruscha; til aniqlanmasa — o'zbekcha (asosiy auditoriya)
        $this->withHeaders(['Accept-Language' => 'ru-RU,ru;q=0.9'])->get('/kirish')->assertSee('Войти');
        $this->flushSession();
        $this->withHeaders(['Accept-Language' => '']);
        $this->get('/kirish')->assertSee('Kirish');
        $this->get('/til/ru');
        $this->get('/kirish')->assertSee('Войти');
        $this->get('/til/uz');
        $this->get('/kirish')->assertSee('Kirish');
    }
}
