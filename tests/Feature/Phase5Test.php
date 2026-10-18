<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Tenant;
use App\Models\TrialRegistry;
use App\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234503', 'password' => 'Secret123', 'edition' => 'start',
        ]);
        $this->admin = User::where('is_super_admin', true)->first() ?? User::factory()->create(['is_super_admin' => true, 'tenant_id' => null, 'phone' => '998900000001']);
    }

    public function test_manual_readonly_needs_reason_blocks_writes_and_beats_payment(): void
    {
        $this->actingAs($this->admin)->post(route('admin.tenants.readonly', $this->tenant), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertFalse($this->tenant->fresh()->isManualReadOnly());

        $this->actingAs($this->admin)->post(route('admin.tenants.readonly', $this->tenant), ['reason' => "Shartnoma buzilgan"])->assertSessionHasNoErrors();
        $t = $this->tenant->fresh();
        $this->assertTrue($t->isManualReadOnly());
        $this->assertSame('read_only', $t->accessState());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.manual_readonly_on']);

        // Faol obuna bo'lsa ham ochilmaydi
        app(\App\Services\Billing\SubscriptionService::class)->grant($t, $t->edition, 1, $this->admin->id);
        $this->assertSame('read_only', $t->fresh()->accessState());

        // Direktor yozolmaydi va sababni ko'radi
        $owner = $t->owner;
        $this->actingAs($owner)->get(route('app.dashboard'))->assertOk()->assertSee('Shartnoma buzilgan');
        $this->actingAs($owner)->post(route('app.groups.store'), ['name' => 'X'])->assertSessionHas('error');

        $this->actingAs($this->admin)->delete(route('admin.tenants.readonly.off', $t))->assertSessionHasNoErrors();
        $this->assertNotSame('read_only', $t->fresh()->accessState());
    }

    public function test_manual_readonly_with_end_date_expires(): void
    {
        $t = $this->tenant;
        $t->update(['manual_readonly_reason' => 'x sabab', 'manual_readonly_until' => now()->subMinute()]);
        $this->assertFalse($t->fresh()->isManualReadOnly());
    }

    public function test_lifetime_opens_everything_skips_lifecycle_and_is_not_readonly(): void
    {
        $t = $this->tenant;
        $this->assertFalse($t->hasFeature('pay_schemes')); // start nashri
        $this->actingAs($this->admin)->post(route('admin.tenants.lifetime', $t), ['reason' => 'Hamkor'])->assertSessionHasNoErrors();
        $t = $t->fresh();
        $this->assertTrue($t->lifetime);
        $this->assertSame('lifetime', $t->accessState());
        $this->assertTrue($t->hasFeature('pay_schemes'));
        $this->assertTrue($t->hasFeature('report_chain'));

        $t->forceFill(['access_expires_at' => now()->subDays(120)])->save();
        $this->assertSame('lifetime', $t->fresh()->accessState());
        $stats = app(SubscriptionLifecycle::class)->run();
        $this->assertSame(0, $stats['expired_notified'] + $stats['deletion_flagged'] + $stats['reminded']);
        $this->assertFalse($t->fresh()->isReadOnly());
    }

    public function test_admin_creates_tenant_without_demo_and_trial_registry(): void
    {
        $edition = Edition::where('key', 'premium')->first();
        $before = TrialRegistry::count();
        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Hamkor', 'owner_name' => 'Ali', 'phone' => '998907770001', 'password' => 'Secret1234', 'edition_id' => $edition->id, 'months' => 3,
        ])->assertSessionHasNoErrors();
        $t = Tenant::where('name', 'Hamkor')->first();
        $this->assertNotNull($t);
        $this->assertTrue($t->created_by_admin);
        $this->assertSame($before, TrialRegistry::count());
        $this->assertSame(0, \DB::table('kids')->where('tenant_id', $t->id)->count());
        $this->assertSame('active', $t->accessState());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.created_by_admin']);

        // Umrbod: muddat shart emas
        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Umrbod bogcha', 'owner_name' => 'Vali', 'phone' => '998907770002', 'password' => 'Secret1234', 'edition_id' => $edition->id, 'lifetime' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Tenant::where('name', 'Umrbod bogcha')->first()->lifetime);

        // Muddatsiz va umrbod emas — xato
        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Muddatsiz', 'owner_name' => 'V', 'phone' => '998907770003', 'password' => 'Secret1234', 'edition_id' => $edition->id, 'months' => 0,
        ])->assertSessionHasErrors('months');
    }

    public function test_admin_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tenants.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.tenants.show', $this->tenant))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
    }
}
