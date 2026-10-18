<?php

namespace Tests\Feature;

use App\Models\AiChat;
use App\Models\AiReport;
use App\Models\AiUsage;
use App\Models\Employee;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\ChatService;
use App\Services\Ai\FactsPacket;
use App\Services\Ai\PiiScanner;
use App\Services\Ai\ReportService;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase6Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234506', 'password' => 'Secret123', 'edition' => 'premium',
        ]);
        Carbon::setTestNow('2026-11-04 10:00:00'); // chorshanba
        $this->tenant->forceFill(['access_expires_at' => now()->addYear()])->save();
        PlatformSetting::put('ai.api_key', Crypt::encryptString('sk-test'));
        PlatformSetting::put('ai.price_in', '1');
        PlatformSetting::put('ai.price_out', '5');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function inTenant(callable $fn)
    {
        return app(TenantContext::class)->run($this->tenant->fresh(), $fn);
    }

    private ?array $reply = null;

    private function fakeText(string $text, int $in = 100, int $out = 50): void
    {
        $this->reply = ['content' => [['type' => 'text', 'text' => $text]], 'usage' => ['input_tokens' => $in, 'output_tokens' => $out]];
        Http::fake(['api.anthropic.com/*' => fn () => Http::response($this->reply)]);
    }

    public function test_pii_scanner_masks_and_blocks(): void
    {
        $p = new PiiScanner;
        $this->assertContains('telefon', $p->find('Qo\'ng\'iroq: +998 90 123 45 67'));
        $this->assertContains('email', $p->find('a.b@mail.uz'));
        [$m, $was] = $p->mask('Tel 998901234567 yoki x@y.uz');
        $this->assertTrue($was);
        $this->assertStringNotContainsString('998901234567', $m);
        $this->assertStringNotContainsString('x@y.uz', $m);
        $p->assertClean(['summa' => 1500000, 'matn' => 'Davomat 92%']);
        $this->expectException(AiException::class);
        $p->assertClean(['matn' => 'tel 998901234567']);
    }

    public function test_client_logs_usage_and_cost_and_blocks_pii(): void
    {
        $this->fakeText('salom', 100, 50);
        $c = app(AiClient::class);
        $r = $c->complete('sys', [['role' => 'user', 'content' => 'savol']], 'chat', $this->tenant);
        $this->assertSame('salom', $r['text']);
        $u = AiUsage::latest('id')->first();
        $this->assertSame('ok', $u->status);
        $this->assertSame(100 + 250, (int) $u->cost_micro);
        Http::assertSentCount(1);

        try {
            $c->complete('sys', [['role' => 'user', 'content' => 'raqam 998901234567']], 'chat', $this->tenant);
            $this->fail('PII o\'tib ketdi');
        } catch (AiException $e) {
            $this->assertSame('pii', $e->reason);
        }
        Http::assertSentCount(1);
        $this->assertSame('blocked', AiUsage::latest('id')->first()->status);
    }

    public function test_client_off_without_key(): void
    {
        PlatformSetting::put('ai.api_key', null);
        $this->expectException(AiException::class);
        app(AiClient::class)->complete('s', [['role' => 'user', 'content' => 'x']], 'chat');
    }

    public function test_chat_masks_question_and_respects_limits(): void
    {
        $this->fakeText('Javob matni');
        $owner = $this->tenant->owner;
        $chat = $this->inTenant(fn () => app(ChatService::class)->ask($owner, "Ali 998901234567 qanday?", 'uz'));
        $this->assertTrue((bool) $chat->masked);
        $this->assertStringNotContainsString('998901234567', $chat->question);
        $this->assertSame('Javob matni', $chat->answer);

        $this->tenant->forceFill(['ai_token_limit' => 10])->save();
        AiUsage::create(['tenant_id' => $this->tenant->id, 'kind' => 'chat', 'model' => 'm', 'input_tokens' => 20, 'output_tokens' => 0, 'status' => 'ok', 'created_at' => now()]);
        $this->expectException(AiException::class);
        $this->inTenant(fn () => app(ChatService::class)->ask($owner, 'yana', 'uz'));
    }

    public function test_standard_edition_has_no_daily_report_and_start_no_ai(): void
    {
        $std = app(TenantProvisioner::class)->register(['name' => 'Std', 'owner_name' => 'D', 'phone' => '998901234507', 'password' => 'Secret123', 'edition' => 'standard']);
        $this->assertTrue($std->hasFeature('ai_chat'));
        $this->assertTrue($std->hasFeature('ai_report_weekly'));
        $this->assertFalse($std->hasFeature('ai_report_daily'));
        $st = app(TenantProvisioner::class)->register(['name' => 'St', 'owner_name' => 'D', 'phone' => '998901234508', 'password' => 'Secret123', 'edition' => 'start']);
        $this->assertFalse($st->hasFeature('ai_chat'));
        $this->assertTrue($this->tenant->hasFeature('ai_report_daily'));
    }

    public function test_facts_packet_has_no_names_or_phones(): void
    {
        $this->inTenant(function () {
            Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Sarvinoz Karimova', 'phone' => '998909998877', 'position' => 'teacher', 'status' => 'active', 'hired_on' => '2026-01-01', 'base_salary' => 0]);
            $facts = app(FactsPacket::class)->build(Carbon::parse('2026-11-03'));
            $json = json_encode($facts, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('Sarvinoz', $json);
            $this->assertStringNotContainsString('998909998877', $json);
            app(PiiScanner::class)->assertClean($facts);
        });
    }

    public function test_verify_catches_wrong_numbers_and_evidence(): void
    {
        $svc = app(ReportService::class);
        $facts = ['attendance' => ['rate' => 92.5], 'money' => ['debt' => 1500000]];
        $ok = ['today' => ['Davomat 92.5% bo\'ldi'], 'issues' => [['title' => 'Qarz', 'evidence' => [['key' => 'money.debt', 'value' => 1500000]], 'cause' => 'x', 'action' => 'y']], 'good' => [], 'tomorrow' => []];
        $this->assertSame([], $svc->verify($ok, $facts));
        $bad = $ok;
        $bad['issues'][0]['evidence'][0]['value'] = 900000;
        $this->assertNotEmpty($svc->verify($bad, $facts));
        $bad2 = $ok;
        $bad2['today'] = ['Davomat 77% bo\'ldi'];
        $this->assertNotEmpty($svc->verify($bad2, $facts));
    }

    public function test_report_generate_ready_and_unreliable(): void
    {
        $good = json_encode(['today' => ['Tinch kun'], 'issues' => [], 'good' => ['Yaxshi'], 'tomorrow' => ['Davom']]);
        $this->fakeText($good);
        $date = Carbon::parse('2026-11-03');
        $r = $this->inTenant(fn () => app(ReportService::class)->generate($this->tenant->fresh(), $date, 'daily'));
        $this->assertSame('ready', $r->status);

        $bad = json_encode(['today' => ['Qarz 98765432 so\'m'], 'issues' => [], 'good' => [], 'tomorrow' => []]);
        $this->fakeText($bad);
        $r2 = $this->inTenant(fn () => app(ReportService::class)->generate($this->tenant->fresh(), Carbon::parse('2026-11-02'), 'daily'));
        $this->assertSame('unreliable', $r2->status);
        $this->assertNotEmpty($r2->result['problems']);
    }

    public function test_report_fails_gracefully_when_service_down(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([], 500)]);
        $r = $this->inTenant(fn () => app(ReportService::class)->generate($this->tenant->fresh(), Carbon::parse('2026-11-03'), 'daily'));
        $this->assertSame('failed', $r->status);
    }

    public function test_ai_daily_command_generates_for_premium(): void
    {
        $this->fakeText(json_encode(['today' => ['Tinch'], 'issues' => [], 'good' => [], 'tomorrow' => []]));
        $this->artisan('ai:daily', ['--date' => '2026-11-03', '--tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertTrue(AiReport::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('status', 'ready')->exists());
    }

    public function test_chat_streams_deltas_and_saves_answer(): void
    {
        $sse = "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":12}}}\n\n"
            ."event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Salom \"}}\n\n"
            ."event: content_block_delta\ndata: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"dunyo\"}}\n\n"
            ."event: message_delta\ndata: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":7}}\n\n";
        Http::fake(['api.anthropic.com/*' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream'])]);
        $owner = $this->tenant->owner;
        $res = $this->actingAs($owner)->post(route('app.ai.stream'), ['question' => 'Davomat qayerda?']);
        $res->assertOk();
        $out = $res->streamedContent();
        $this->assertStringContainsString('"t":"delta"', $out);
        $this->assertStringContainsString('"t":"done"', $out);
        $this->assertSame('Salom dunyo', $this->inTenant(fn () => AiChat::latest('id')->first()->answer));
    }

    public function test_pages_render(): void
    {
        $owner = $this->tenant->owner;
        $this->actingAs($owner)->get(route('app.ai.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.ai.reports.index'))->assertOk();
        $this->actingAs($owner)->get(route('app.dashboard'))->assertOk();

        $this->fakeText('Bu javob');
        $this->actingAs($owner)->postJson(route('app.ai.ask'), ['question' => 'Davomat qayerda?'])->assertOk()->assertJsonFragment(['answer' => 'Bu javob']);

        $admin = User::where('is_super_admin', true)->first();
        $this->actingAs($admin)->get(route('admin.ai.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.tenants.show', $this->tenant))->assertOk();
        $this->actingAs($admin)->put(route('admin.ai.tenant', $this->tenant), ['ai_disabled' => '1', 'ai_token_limit' => 1234])->assertSessionHasNoErrors();
        $t = $this->tenant->fresh();
        $this->assertTrue($t->ai_disabled);
        $this->assertSame(1234, (int) $t->ai_token_limit);
    }

    public function test_admin_key_is_encrypted_and_never_shown(): void
    {
        $admin = User::where('is_super_admin', true)->first();
        PlatformSetting::put('ai.api_key', null);
        $this->actingAs($admin)->put(route('admin.ai.update'), [
            'enabled' => '1', 'api_key' => 'sk-secret-xyz', 'ai_model_chat' => 'claude-haiku-5-5', 'ai_model_report' => 'claude-sonnet-5-5',
            'ai_price_in' => '1', 'ai_price_out' => '5', 'ai_price_cache' => '0.1',
            'ai_questions_standard' => 30, 'ai_questions_premium' => 100, 'ai_tokens_standard' => 600000, 'ai_tokens_premium' => 1500000,
        ])->assertSessionHasNoErrors();
        $stored = PlatformSetting::get('ai.api_key');
        $this->assertStringNotContainsString('sk-secret-xyz', $stored);
        $this->actingAs($admin)->get(route('admin.ai.index'))->assertOk()->assertDontSee('sk-secret-xyz');
        $this->assertSame('sk-secret-xyz', app(AiClient::class)->key());
    }
}
