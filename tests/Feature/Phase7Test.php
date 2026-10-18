<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlacklistEntry;
use App\Models\Group;
use App\Models\Kid;
use App\Models\KidGuardian;
use App\Models\Lead;
use App\Services\Guardians\BlacklistService;
use App\Services\Leads\LeadFlow;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase7Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234509', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
        Carbon::setTestNow('2026-11-04 10:00:00');
        $this->tenant->forceFill(['access_expires_at' => now()->addYear()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function inTenant(callable $fn, $tenant = null)
    {
        return app(TenantContext::class)->run($tenant ?? $this->tenant->fresh(), $fn);
    }

    private function group(): Group
    {
        return $this->inTenant(fn () => Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Quyosh', 'type' => 'main', 'monthly_fee' => 500000, 'capacity' => 20, 'status' => 'active']));
    }

    private function kidPayload(array $extra = []): array
    {
        return $extra + ['full_name' => 'Ali Valiyev', 'gender' => 'male', 'birth_date' => '2022-01-01', 'parent_name' => 'Vali', 'parent_phone' => '90 123 45 67', 'group_id' => $this->group()->id];
    }

    public function test_two_guardians_encrypted_and_start_edition_limited(): void
    {
        $owner = $this->tenant->owner;
        $this->actingAs($owner)->post(route('app.kids.store'), $this->kidPayload([
            'g1_relation' => 'father', 'g2_name' => 'Zebo', 'g2_relation' => 'mother', 'g2_phone' => '91 765 43 21', 'g2_is_payer' => 1,
        ]))->assertSessionHasNoErrors();
        $kid = Kid::withoutGlobalScopes()->first();
        $this->assertSame(2, KidGuardian::withoutGlobalScopes()->where('kid_id', $kid->id)->count());
        $raw = DB::table('kid_guardians')->where('kid_id', $kid->id)->where('position', 2)->first();
        $this->assertStringNotContainsString('Zebo', $raw->name);
        $this->assertStringNotContainsString('998917654321', $raw->phone);
        $this->assertNotNull($raw->phone_hash);
        $g1 = DB::table('kid_guardians')->where('kid_id', $kid->id)->where('position', 1)->first();
        $this->assertSame(0, (int) $g1->is_payer); // 2-vasiy asosiy to'lovchi
        $this->actingAs($owner)->get(route('app.kids.show', $kid))->assertOk()->assertSee('Zebo');
        $this->actingAs($owner)->get(route('app.kids.edit', $kid))->assertOk();

        // Start tarifida faqat bitta vasiy
        $start = app(TenantProvisioner::class)->register(['name' => 'St', 'owner_name' => 'D', 'phone' => '998901234510', 'password' => 'Secret123', 'edition' => 'start']);
        $start->forceFill(['access_expires_at' => now()->addYear()])->save();
        $g = $this->inTenant(fn () => Group::create(['tenant_id' => $start->id, 'name' => 'A', 'type' => 'main', 'monthly_fee' => 100000, 'capacity' => 10, 'status' => 'active']), $start);
        $this->actingAs($start->owner)->post(route('app.kids.store'), ['full_name' => 'Bola', 'gender' => 'female', 'birth_date' => '2022-02-02', 'parent_phone' => '93 111 22 33', 'group_id' => $g->id,
            'g2_phone' => '94 111 22 33'])->assertSessionHasNoErrors();
        $this->assertSame(1, KidGuardian::withoutGlobalScopes()->where('tenant_id', $start->id)->count());
    }

    public function test_blacklist_warns_and_override_needs_reason_and_is_logged(): void
    {
        $owner = $this->tenant->owner;
        $this->actingAs($owner)->post(route('app.blacklist.store'), ['type' => 'family', 'name' => 'Yomon Oila', 'phone' => '90 123 45 67', 'reason' => "To'lamagan"])->assertSessionHasNoErrors();
        $entry = BlacklistEntry::withoutGlobalScopes()->first();
        $this->assertStringNotContainsString('Yomon', DB::table('blacklist_entries')->value('name'));
        $this->assertNotEmpty($entry->phone_hashes);

        $payload = $this->kidPayload();
        $this->actingAs($owner)->post(route('app.kids.store'), $payload)->assertSessionHas('blacklist');
        $this->assertSame(0, Kid::withoutGlobalScopes()->count());

        $this->actingAs($owner)->post(route('app.kids.store'), $payload + ['blacklist_override' => 1, 'override_reason' => 'ab'])->assertSessionHas('blacklist');
        $this->assertSame(0, Kid::withoutGlobalScopes()->count());

        $this->actingAs($owner)->post(route('app.kids.store'), $payload + ['blacklist_override' => 1, 'override_reason' => 'Direktor kafolat berdi'])->assertSessionHasNoErrors();
        $this->assertSame(1, Kid::withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::where('action', 'blacklist.override')->exists());
    }

    public function test_blacklist_cert_match_and_staff_type_isolated(): void
    {
        $this->inTenant(function () {
            BlacklistEntry::create(BlacklistService::build('staff', 'X', ['998901112200'], null, 'sabab', null) + ['tenant_id' => $this->tenant->id]);
            $svc = app(BlacklistService::class);
            $this->assertCount(1, $svc->check(['90 111 22 00'], null, 'staff'));
            $this->assertCount(0, $svc->check(['998901112200'], null, 'family'));
            $this->assertCount(0, $svc->check(['998901112299'], null, 'staff'));
        });
        // boshqa bog'chada ko'rinmaydi
        $other = app(TenantProvisioner::class)->register(['name' => 'Boshqa', 'owner_name' => 'D', 'phone' => '998901234511', 'password' => 'Secret123', 'edition' => 'premium']);
        $this->inTenant(fn () => $this->assertCount(0, app(BlacklistService::class)->check(['998901112200'], null, 'staff')), $other);
    }

    public function test_blacklist_page_needs_permission_and_start_lacks_feature(): void
    {
        $this->actingAs($this->tenant->owner)->get(route('app.blacklist.index'))->assertOk();
        $start = app(TenantProvisioner::class)->register(['name' => 'St', 'owner_name' => 'D', 'phone' => '998901234512', 'password' => 'Secret123', 'edition' => 'start']);
        $this->assertFalse($start->hasFeature('blacklist'));
        $this->assertFalse($start->hasFeature('guardians2'));
        $this->assertTrue($start->fresh()->edition->key === 'start');
    }

    public function test_leads_stale_lost_reason_and_conversion(): void
    {
        $owner = $this->tenant->owner;
        $this->actingAs($owner)->post(route('app.leads.store'), ['type' => 'kid', 'name' => 'Nodira', 'phone' => '95 555 44 33', 'source' => 'Instagram', 'next_call_on' => '2026-11-05'])->assertSessionHasNoErrors();
        $lead = Lead::withoutGlobalScopes()->first();
        $this->assertSame('2026-11-05', $lead->next_call_on->toDateString());
        $this->inTenant(fn () => $this->assertCount(0, LeadFlow::stale()));
        $lead->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->inTenant(fn () => $this->assertCount(1, LeadFlow::stale()));
        $this->actingAs($owner)->get(route('app.dashboard'))->assertOk()->assertSee('24');

        $statuses = array_values((array) $this->tenant->fresh()->setting('leads.statuses'));
        $this->actingAs($owner)->post(route('app.leads.move', $lead), ['status' => end($statuses), 'lost_reason' => ''])->assertSessionHas('error');
        $this->assertSame('open', $lead->fresh()->result);
        $this->actingAs($owner)->post(route('app.leads.move', $lead), ['status' => end($statuses), 'lost_reason' => 'Qimmat'])->assertSessionHasNoErrors();
        $this->assertSame('lost', $lead->fresh()->result);
        $this->assertNotNull($lead->fresh()->stage_changed_at);
        $this->actingAs($owner)->get(route('app.leads.index'))->assertOk()->assertSee('Instagram');
        $this->actingAs($owner)->get(route('app.leads.index', ['type' => 'employee']))->assertOk();

        // Bolaga aylantirish arizani «won»ga o'tkazadi
        $lead2 = Lead::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'type' => 'kid', 'status' => $statuses[0], 'name' => 'X', 'phone' => '998951112233', 'result' => 'open']);
        $this->actingAs($owner)->post(route('app.kids.store'), $this->kidPayload(['parent_phone' => '97 000 11 22', 'lead_id' => $lead2->id]))->assertSessionHasNoErrors();
        $this->assertSame('won', $lead2->fresh()->result);
        $this->assertNotNull($lead2->fresh()->converted_id);
    }

    public function test_staff_lead_rejection_can_blacklist(): void
    {
        $owner = $this->tenant->owner;
        $statuses = array_values((array) $this->tenant->fresh()->setting('leads.statuses'));
        $lead = Lead::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'type' => 'employee', 'status' => $statuses[0], 'name' => 'Nomzod', 'phone' => '998931112233', 'result' => 'open']);
        $this->actingAs($owner)->post(route('app.leads.move', $lead), ['status' => end($statuses), 'lost_reason' => 'Ishonchsiz', 'add_blacklist' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, BlacklistEntry::withoutGlobalScopes()->where('type', 'staff')->count());
    }

    public function test_admin_bonus_requires_feature_and_matrix_synced(): void
    {
        $std = app(TenantProvisioner::class)->register(['name' => 'Std', 'owner_name' => 'D', 'phone' => '998901234513', 'password' => 'Secret123', 'edition' => 'standard']);
        $this->assertFalse($std->hasFeature('admin_bonus'));
        $this->assertTrue($std->hasFeature('blacklist'));
        $this->assertTrue($std->hasFeature('guardians2'));
        $this->assertTrue($this->tenant->hasFeature('admin_bonus'));
        $res = $this->inTenant(fn () => app(\App\Services\Payroll\SchemePayroll::class)->admin(Carbon::parse('2026-11-01')), $std->fresh());
        $this->assertSame(0, $res['new_kids_amount']);
        $this->assertSame(0, $res['full_amount']);
        foreach (config('kinderdesk.editions') as $def) {
            $this->assertNotContains('billing_prorate', $def['features']);
            $this->assertNotContains('billing_per_day', $def['features']);
        }
    }

    public function test_employee_and_lead_forms_render_and_staff_blacklist_blocks(): void
    {
        $owner = $this->tenant->owner;
        $this->actingAs($owner)->get(route('app.employees.create', ['lead' => 5]))->assertOk()->assertSee('name="lead_id"', false);
        $this->actingAs($owner)->post(route('app.blacklist.store'), ['type' => 'staff', 'name' => 'Yomon', 'phone' => '90 700 11 22', 'reason' => 'Ishdan haydalgan'])->assertSessionHasNoErrors();
        $role = \App\Models\Role::where('tenant_id', $this->tenant->id)->where('key', 'teacher')->first();
        $this->actingAs($owner)->post(route('app.employees.store'), ['full_name' => 'Yomon Xodim', 'phone' => '90 700 11 22', 'role_id' => $role->id, 'hired_on' => '2026-11-01', 'base_salary' => 0])->assertSessionHas('blacklist');
        $this->assertSame(0, \App\Models\Employee::withoutGlobalScopes()->count());
    }

    public function test_guide_pages_render_both_languages(): void
    {
        foreach (['uz', 'ru'] as $l) {
            $this->actingAs($this->tenant->owner)->withSession(['locale' => $l])->get(route('app.guide'))->assertOk();
        }
    }
}
