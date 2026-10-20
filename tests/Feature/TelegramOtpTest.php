<?php

namespace Tests\Feature;

use App\Models\OtpTelegramLink;
use App\Models\TelegramBot;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Telegram\TelegramUpdates;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SMS kod kelmasa — kodni Telegram (platforma boti) orqali olish. */
class TelegramOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '998901234588';

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withSession(['locale' => 'uz']);
        Carbon::setTestNow('2026-10-20 10:00:00');
        app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => self::PHONE, 'password' => 'Secret123', 'edition' => 'standard',
        ]);
        $this->bot = TelegramBot::create(['is_platform' => true, 'token' => '555555:'.str_repeat('C', 35), 'bot_id' => 1, 'username' => 'kd_platform_bot', 'status' => 'ok']);
        \App\Models\PlatformSetting::put('tg.mode', 'webhook'); // testda getUpdates chaqirilmasin
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function update(array $message): void
    {
        app(TelegramUpdates::class)->handle($this->bot, ['update_id' => random_int(1, 99999), 'message' => $message + ['message_id' => 1]]);
    }

    private function lastCode(): ?string
    {
        $m = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'sendMessage') && str_contains((string) $p[0]['text'], 'tasdiqlash kodi:'))->last();

        return $m && preg_match('/kodi: (\d{6})/', $m[0]['text'], $c) ? $c[1] : null;
    }

    /** Saytdan so'rov → start-havola tokenini qaytaradi. */
    private function requestLink(string $purpose = 'login', string $phone = '90 123 45 88'): array
    {
        $r = $this->postJson(route('otp.telegram'), ['phone' => $phone, 'purpose' => $purpose]); $this->assertSame(200, $r->status(), $r->getContent());
        $this->assertTrue($r->json('ok'));

        return $r->json();
    }

    public function test_full_flow_login_via_telegram_contact(): void
    {
        $j = $this->requestLink();
        $this->assertFalse($j['sent']);
        $this->assertStringContainsString('t.me/kd_platform_bot?start=o_', $j['link']);
        $this->getJson(route('otp.telegram.status', ['token' => $j['token']]))->assertJson(['state' => 'pending']);

        // /start → kontakt so'raladi
        $this->update(['chat' => ['id' => 7001, 'type' => 'private'], 'from' => ['id' => 7001, 'username' => 'dir'], 'text' => '/start o_'.$j['token']]);
        $this->getJson(route('otp.telegram.status', ['token' => $j['token']]))->assertJson(['state' => 'awaiting_contact']);
        $this->assertNull($this->lastCode());

        // O'zining kontakti → kod keladi
        $this->update(['chat' => ['id' => 7001, 'type' => 'private'], 'from' => ['id' => 7001, 'username' => 'dir'], 'contact' => ['phone_number' => '+'.self::PHONE, 'user_id' => 7001, 'first_name' => 'D']]);
        $code = $this->lastCode();
        $this->assertNotNull($code);
        $this->getJson(route('otp.telegram.status', ['token' => $j['token']]))->assertJson(['state' => 'sent']);
        $this->assertDatabaseHas('otp_codes', ['phone' => self::PHONE, 'purpose' => 'login', 'channel' => 'telegram']);
        $this->assertDatabaseHas('otp_telegram_links', ['phone' => self::PHONE, 'chat_id' => 7001]);

        // Kod bilan kirish
        $this->post(route('login.otp'), ['phone' => self::PHONE, 'code' => $code])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_foreign_contact_and_wrong_phone_are_rejected(): void
    {
        $j = $this->requestLink();
        $chat = ['id' => 7002, 'type' => 'private'];
        $this->update(['chat' => $chat, 'from' => ['id' => 7002], 'text' => '/start o_'.$j['token']]);

        // Boshqa odamning kontakti (user_id mos emas)
        $this->update(['chat' => $chat, 'from' => ['id' => 7002], 'contact' => ['phone_number' => self::PHONE, 'user_id' => 9999]]);
        $this->assertNull($this->lastCode());
        $this->assertDatabaseCount('otp_telegram_links', 0);

        // O'zining kontakti, lekin raqam boshqa
        $this->update(['chat' => $chat, 'from' => ['id' => 7002], 'contact' => ['phone_number' => '+998909999999', 'user_id' => 7002]]);
        $this->assertNull($this->lastCode());
        $this->assertDatabaseCount('otp_telegram_links', 0);
        $this->getJson(route('otp.telegram.status', ['token' => $j['token']]))->assertJson(['state' => 'failed']);
    }

    public function test_known_chat_gets_code_directly_and_old_sms_code_is_replaced(): void
    {
        OtpTelegramLink::create(['phone' => self::PHONE, 'chat_id' => 7003, 'tg_user_id' => 7003, 'verified_at' => now()]);
        app(OtpService::class)->send(self::PHONE, 'login');
        Carbon::setTestNow(now()->addSeconds(16));

        $r = $this->postJson(route('otp.telegram'), ['phone' => self::PHONE, 'purpose' => 'login'])->assertOk();
        $this->assertTrue($r->json('sent'));
        $code = $this->lastCode();
        $this->assertNotNull($code);
        $sent = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'sendMessage'))->last();
        $this->assertSame(7003, $sent[0]['chat_id']);
        $this->assertTrue(app(OtpService::class)->verify(self::PHONE, 'login', $code));
    }

    public function test_too_fast_after_sms_is_throttled_and_no_account_gets_no_code(): void
    {
        OtpTelegramLink::create(['phone' => self::PHONE, 'chat_id' => 7004, 'tg_user_id' => 7004, 'verified_at' => now()]);
        app(OtpService::class)->send(self::PHONE, 'login');
        $r = $this->postJson(route('otp.telegram'), ['phone' => self::PHONE, 'purpose' => 'login'])->assertOk();
        $this->assertFalse($r->json('ok'));
        $this->assertArrayHasKey('wait', $r->json());

        // Ro'yxatdan o'tmagan raqam: sayt havola beradi (oshkor qilmaydi), botda esa kod berilmaydi
        $j = $this->requestLink('login', '90 555 55 55');
        $this->update(['chat' => ['id' => 7005, 'type' => 'private'], 'from' => ['id' => 7005], 'text' => '/start o_'.$j['token']]);
        $this->update(['chat' => ['id' => 7005, 'type' => 'private'], 'from' => ['id' => 7005], 'contact' => ['phone_number' => '+998905555555', 'user_id' => 7005]]);
        $this->assertNull($this->lastCode());
        $this->getJson(route('otp.telegram.status', ['token' => $j['token']]))->assertJson(['state' => 'failed']);
    }

    public function test_register_purpose_checks_and_expired_token(): void
    {
        // Band raqam bilan ro'yxatdan o'tish — rad etiladi
        $this->postJson(route('otp.telegram'), ['phone' => self::PHONE, 'purpose' => 'register'])->assertStatus(422);

        // Yangi raqam: oqim ishlaydi va kod 'register' maqsadida saqlanadi
        $j = $this->requestLink('register', '90 777 66 55');
        $this->update(['chat' => ['id' => 7006, 'type' => 'private'], 'from' => ['id' => 7006], 'text' => '/start o_'.$j['token']]);
        $this->update(['chat' => ['id' => 7006, 'type' => 'private'], 'from' => ['id' => 7006], 'contact' => ['phone_number' => '998907776655', 'user_id' => 7006]]);
        $code = $this->lastCode();
        $this->assertTrue(app(OtpService::class)->verify('998907776655', 'register', $code));

        // Eskirgan token
        $j2 = $this->requestLink('register', '93 456 78 90');
        Carbon::setTestNow(now()->addMinutes(11));
        $this->getJson(route('otp.telegram.status', ['token' => $j2['token']]))->assertJson(['state' => 'expired']);
    }

    public function test_unavailable_without_platform_bot_and_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Kodni Telegramdan oling');
        $this->get(route('register'))->assertOk()->assertSee('Kodni Telegramdan oling');
        $this->get(route('password.forgot'))->assertOk()->assertSee('Kodni Telegramdan oling');

        $this->bot->delete();
        $this->get(route('login'))->assertOk()->assertDontSee('Kodni Telegramdan oling');
        $this->postJson(route('otp.telegram'), ['phone' => self::PHONE, 'purpose' => 'login'])->assertOk()->assertJson(['ok' => false]);
    }
}
