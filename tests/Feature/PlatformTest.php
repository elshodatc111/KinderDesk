<?php

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Plan;
use App\Models\Topup;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\Billing\TopupService;
use App\Services\Billing\WalletService;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TrialAlreadyUsed;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function register(string $phone, string $name = 'Test bog\'cha', ?string $email = null, ?string $tax = null)
    {
        return app(TenantProvisioner::class)->register([
            'name' => $name, 'owner_name' => 'Direktor', 'phone' => $phone,
            'password' => 'Secret123', 'email' => $email, 'edition' => 'standard', 'tax_id' => $tax,
        ]);
    }

    public function test_registration_starts_14_day_trial(): void
    {
        $t = $this->register('998901234567');
        $this->assertSame('trial', $t->fresh()->accessState());
        $this->assertTrue($t->trial_ends_at->between(now()->addDays(13), now()->addDays(15)));
    }

    public function test_trial_cannot_be_reused_by_phone_email_or_tax_id(): void
    {
        $this->register('998901234567', 'A', 'a@x.uz', '123456789');
        foreach ([['998901234567'], ['998907654321', 'B', 'a@x.uz'], ['998907654322', 'C', null, '123456789']] as $args) {
            try {
                $this->register(...$args);
                $this->fail('Second trial must be rejected');
            } catch (TrialAlreadyUsed $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->register('998901111111', 'A');
        $b = $this->register('998902222222', 'B');
        $ctx = app(TenantContext::class);
        $ctx->run($a, fn () => Kid::create(['tenant_id' => $a->id, 'full_name' => 'Ali Valiyev', 'birth_date' => '2021-01-01', 'parent_phone' => '998901234567', 'parent_name' => 'Ota']));
        $this->assertSame(1, $ctx->run($a, fn () => Kid::count()));
        $this->assertSame(0, $ctx->run($b, fn () => Kid::count()));
    }

    public function test_manual_topup_credits_full_amount_once(): void
    {
        Storage::fake('local');
        $t = $this->register('998901111111');
        $owner = $t->owner;
        $admin = User::where('is_super_admin', true)->firstOrFail();
        $svc = app(TopupService::class);
        $topup = $svc->createManual($t, $owner, 500000, UploadedFile::fake()->image('chek.jpg'), now()->toDateString(), '1234', null);
        $this->assertSame('pending', $topup->status);
        $svc->approve($topup, $admin);
        $this->assertSame(500000, (int) $t->fresh()->balance);
        $this->expectException(\DomainException::class);
        $svc->approve($topup, $admin);
    }

    public function test_purchase_from_balance_and_insufficient_balance(): void
    {
        $t = $this->register('998901111111');
        $plan = Plan::where('is_active', true)->orderBy('price')->firstOrFail();
        $this->expectException(\App\Services\Billing\InsufficientBalance::class);
        app(SubscriptionService::class)->purchase($t, $plan);
    }

    public function test_purchase_extends_access(): void
    {
        $t = $this->register('998901111111');
        $plan = Plan::where('is_active', true)->orderBy('price')->firstOrFail();
        app(WalletService::class)->credit($t, $plan->price, 'topup', 'test');
        app(SubscriptionService::class)->purchase($t->fresh(), $plan);
        $t->refresh();
        $this->assertSame('active', $t->accessState());
        $this->assertSame(0, (int) $t->balance);
    }

    public function test_lifecycle_runs_without_error(): void
    {
        $this->register('998901111111');
        $stats = app(SubscriptionLifecycle::class)->run();
        $this->assertIsArray($stats);
    }

    public function test_read_only_blocks_writes_but_allows_viewing(): void
    {
        $t = $this->register('998901111111');
        $t->update(['trial_ends_at' => now()->subDay(), 'access_expires_at' => now()->subDay()]);
        $owner = $t->owner;
        $this->actingAs($owner)->get(route('app.dashboard'))->assertOk();
        $this->actingAs($owner)->post(route('app.kids.store'), ['full_name' => 'Ali Valiyev'])->assertRedirect();
        $this->assertSame(0, \App\Models\Kid::withoutGlobalScopes()->count());
    }

    public function test_public_pages_and_login_render(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_admin_area_is_closed_to_tenants(): void
    {
        $t = $this->register('998901111111');
        $this->actingAs($t->owner)->get('/admin')->assertForbidden();
    }
}
