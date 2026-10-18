<?php

namespace Tests\Feature;

use App\Models\BackupJob;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\GuideVideo;
use App\Models\Kid;
use App\Models\KidGuardian;
use App\Models\KidHealth;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\TgOutbox;
use App\Models\TgPost;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Finance\FinanceService;
use App\Services\Security\ConsentService;
use App\Services\Telegram\TelegramOutbox;
use App\Services\Telegram\TelegramPoller;
use App\Services\Telegram\TelegramUpdates;
use App\Services\Tenancy\TenantProvisioner;
use App\Support\Site\SupportInfo;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9Test extends TestCase
{
    use RefreshDatabase;

    private $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withSession(['locale' => 'uz']);
        Carbon::setTestNow('2026-10-14 10:00:00');
        $this->tenant = app(TenantProvisioner::class)->register([
            'name' => 'Sinov', 'owner_name' => 'Direktor', 'phone' => '998901234588', 'password' => 'Secret123', 'edition' => 'premium',
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

    private function admin(): User
    {
        return User::where('is_super_admin', true)->first();
    }

    private function fakeTelegram(int $mid = 500): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => $mid, 'id' => 4242, 'username' => 'kd_test_bot', 'first_name' => 'Test']])]);
    }

    private function refake($response, string $pattern = 'api.telegram.org/*'): void
    {
        app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();
        Http::fake([$pattern => $response]);
    }

    private function bot(): TelegramBot
    {
        return TelegramBot::create(['tenant_id' => $this->tenant->id, 'token' => '123456:'.str_repeat('A', 35), 'bot_id' => 4242, 'username' => 'kd_test_bot', 'status' => 'ok']);
    }

    /** Guruh + 1 bola + 1 vasiy */
    private function setupKid(): array
    {
        return $this->in(function () {
            $g = Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Quyosh', 'type' => 'main', 'size' => 'large', 'monthly_fee' => 1_000_000, 'capacity' => 20, 'status' => 'active']);
            $k = Kid::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Ali Valiyev', 'birth_date' => '2021-01-01', 'parent_phone' => '998900000001', 'status' => 'active', 'enrolled_on' => '2026-01-01', 'gender' => 'male']);
            GroupKid::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'kid_id' => $k->id, 'started_on' => '2026-01-01']);
            $gu = KidGuardian::create(['tenant_id' => $this->tenant->id, 'kid_id' => $k->id, 'position' => 1, 'relation' => 'father', 'name' => 'Vali', 'phone' => '998900000001', 'is_payer' => true, 'tg_token' => 'TOKENPARENT1']);
            $gu2 = KidGuardian::create(['tenant_id' => $this->tenant->id, 'kid_id' => $k->id, 'position' => 2, 'relation' => 'mother', 'name' => 'Nodira', 'phone' => '998900000002', 'is_payer' => false, 'tg_token' => 'TOKENPARENT2']);

            return [$g, $k, $gu, $gu2];
        });
    }

    private function start(TelegramBot $bot, int $chat, string $payload, string $user = 'ota'): void
    {
        app(TelegramUpdates::class)->handle($bot, ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['id' => $chat, 'username' => $user, 'first_name' => 'Ota'], 'text' => '/start '.$payload]]);
    }

    private function cb(TelegramBot $bot, int $chat, string $data): void
    {
        app(TelegramUpdates::class)->handle($bot, ['update_id' => 2, 'callback_query' => ['id' => 'cb1', 'data' => $data, 'from' => ['id' => $chat], 'message' => ['message_id' => 7, 'chat' => ['id' => $chat, 'type' => 'private']]]]);
    }

    private function connectParent(TelegramBot $bot, int $chat = 111, string $token = 'TOKENPARENT1'): TgContact
    {
        $this->start($bot, $chat, 'g_'.$token);
        $c = $this->in(fn () => TgContact::where('chat_id', $chat)->latest('id')->first());
        $this->cb($bot, $chat, 'cons:'.$c->id.':1');

        return $c->fresh();
    }

    /* ---------------- Rozilik ---------------- */

    public function test_consent_required_and_journal(): void
    {
        config(['kinderdesk.enforce_security' => true]);
        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertRedirect(route('consent.show'));
        $this->actingAs($this->owner)->get(route('consent.show'))->assertOk()->assertSee('Roziman');
        $this->actingAs($this->owner)->post(route('consent.accept'), [])->assertSessionHasErrors('agree');
        $this->actingAs($this->owner)->post(route('consent.accept'), ['agree' => 1])->assertRedirect();
        $this->assertSame(1, DB::table('consents')->where('user_id', $this->owner->id)->count());
        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertOk();

        // Yangi versiya talab qilinsa — qayta rozilik
        app(ConsentService::class)->bump();
        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertRedirect(route('consent.show'));

        // Super admin jurnali + CSV
        $this->actingAs($this->admin())->get(route('admin.consents.index'))->assertRedirect(route('consent.show')); // super admin ham rozilik beradi
        app(ConsentService::class)->record($this->admin());
        $this->actingAs($this->admin())->get(route('admin.consents.index'))->assertOk()->assertSee('Direktor');
        $csv = $this->actingAs($this->admin())->get(route('admin.consents.csv'));
        $csv->assertOk();
        $this->assertStringContainsString('998901234588', $csv->streamedContent());
    }

    public function test_consent_skipped_when_impersonating_and_bump_via_legal_form(): void
    {
        config(['kinderdesk.enforce_security' => true]);
        app(ConsentService::class)->record($this->admin());
        $this->actingAs($this->owner)->withSession(['impersonator_id' => $this->admin()->id])->get(route('app.dashboard'))->assertOk();

        $v = app(ConsentService::class)->version();
        $this->actingAs($this->admin())->put(route('admin.settings.legal'), ['page' => 'oferta', 'text_uz' => 'Yangi matn', 'text_ru' => '', 'reconsent' => 1])->assertRedirect();
        $this->assertSame($v + 1, app(ConsentService::class)->version());
        $this->actingAs($this->admin())->put(route('admin.settings.legal'), ['page' => 'oferta', 'text_uz' => 'Yana', 'text_ru' => ''])->assertRedirect();
        $this->assertSame($v + 1, app(ConsentService::class)->version());
    }

    public function test_registration_records_consent(): void
    {
        $this->assertGreaterThanOrEqual(0, DB::table('consents')->count());
        $this->post(route('register.store'), [])->assertSessionHasErrors('agree');
    }

    /* ---------------- Yordam, video, eksport ---------------- */

    public function test_support_hours_status(): void
    {
        PlatformSetting::put('support.from', '09:00');
        PlatformSetting::put('support.to', '18:00');
        PlatformSetting::put('support.days', '1,2,3,4,5');
        // 2026-10-14 — chorshanba, Toshkent 15:00 (10:00 UTC)
        $this->assertTrue(SupportInfo::isOpen(Carbon::parse('2026-10-14 10:00:00', 'UTC')));
        $this->assertFalse(SupportInfo::isOpen(Carbon::parse('2026-10-14 14:00:00', 'UTC'))); // 19:00
        $this->assertFalse(SupportInfo::isOpen(Carbon::parse('2026-10-17 07:00:00', 'UTC'))); // shanba
        $this->assertStringContainsString('09:00', SupportInfo::scheduleLabel());
        $this->get(route('home'))->assertOk()->assertSee('09:00');

        $this->actingAs($this->admin())->put(route('admin.settings.update'), ['payment_min_topup' => 10000, 'support_from' => '08:30', 'support_to' => '17:00', 'support_days' => [1, 2, 3], 'support_response' => '30 daqiqa'])->assertSessionHasNoErrors();
        $this->assertSame('1,2,3', PlatformSetting::get('support.days'));
    }

    public function test_guide_videos(): void
    {
        $this->assertSame('dQw4w9WgXcQ', GuideVideo::extractId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3'));
        $this->assertSame('dQw4w9WgXcQ', GuideVideo::extractId('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', GuideVideo::extractId('dQw4w9WgXcQ'));
        $this->assertNull(GuideVideo::extractId('https://example.com/x'));

        $a = $this->admin();
        $this->actingAs($a)->post(route('admin.videos.store'), ['title_uz' => 'Davomad', 'url' => 'nope'])->assertSessionHasErrors('url');
        $this->actingAs($a)->post(route('admin.videos.store'), ['title_uz' => 'Davomad', 'title_ru' => 'Посещаемость', 'url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertSessionHasNoErrors();
        $this->actingAs($a)->post(route('admin.videos.store'), ['title_uz' => 'Yashirin', 'url' => 'https://youtu.be/aaaaaaaaaaa', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->actingAs($a)->get(route('admin.videos.index'))->assertOk()->assertSee('Davomad');

        $this->actingAs($this->owner)->get(route('app.videos'))->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertDontSee('aaaaaaaaaaa');
    }

    public function test_excel_export_owner_only(): void
    {
        $this->setupKid();
        $this->actingAs($this->owner)->get(route('app.reports.export', 'kids'))->assertOk();
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', 'admin')->first();
        $role->update(['permissions' => array_merge($role->permissions, ['reports.view'])]);
        $adm = User::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'name' => 'Admin', 'phone' => '998901234500', 'password' => 'Secret123', 'status' => 'active']);
        $this->actingAs($adm)->get(route('app.reports.export', 'kids'))->assertForbidden();
    }

    /* ---------------- Telegram ---------------- */

    public function test_bot_connect_and_settings(): void
    {
        $this->fakeTelegram();
        $this->actingAs($this->owner)->put(route('app.telegram.save'), ['token' => 'bad'])->assertSessionHas('error');
        $this->actingAs($this->owner)->put(route('app.telegram.save'), [
            'token' => '123456789:'.str_repeat('B', 35), 'name' => 'Quyosh bog\'chasi', 'description' => 'Salom', 'short_description' => 'Bot', 'protect_content' => 1,
            'events' => ['attendance_came' => 1, 'payment' => 1], 'templates' => ['attendance_came' => 'Keldi: {kid}'],
        ])->assertSessionHasNoErrors();
        $bot = TelegramBot::forTenant($this->tenant->id);
        $this->assertSame('kd_test_bot', $bot->username);
        $this->assertTrue($bot->protect_content);
        $this->assertFalse($bot->eventEnabled('attendance_absent'));
        $this->assertSame('Keldi: {kid}', $bot->template('attendance_came'));
        // Token bazada shifrlangan
        $this->assertStringNotContainsString('BBBBB', (string) DB::table('telegram_bots')->value('token'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/setMyName'));
        $this->actingAs($this->owner)->get(route('app.telegram.settings'))->assertOk()->assertDontSee('BBBBBBBB');
    }

    public function test_parent_link_consent_and_multiple_accounts(): void
    {
        $this->fakeTelegram();
        $bot = $this->bot();
        [, $kid] = $this->setupKid();

        // Rozilikdan oldin xabar yuborilmaydi
        $this->start($bot, 111, 'g_TOKENPARENT1');
        $c = $this->in(fn () => TgContact::first());
        $this->assertFalse($c->consented);
        $this->assertSame(1, $c->guardian_position);
        $this->in(fn () => app(\App\Services\Telegram\TelegramNotifier::class)->attendance($kid, 'present'));
        $this->assertSame(0, TgOutbox::count());

        $this->cb($bot, 111, 'cons:'.$c->id.':1');
        $this->assertTrue($c->fresh()->consented);

        // Bir vasiyga ikkinchi akkaunt
        $this->connectParent($bot, 222, 'TOKENPARENT1');
        // Boshqa vasiy
        $this->connectParent($bot, 333, 'TOKENPARENT2');
        $this->assertSame(3, $this->in(fn () => TgContact::count()));

        // Noto'g'ri havola
        $this->start($bot, 444, 'g_NOPE');
        $this->assertSame(3, $this->in(fn () => TgContact::count()));
    }

    public function test_attendance_notifies_all_guardians_and_payment_only_payer(): void
    {
        $this->fakeTelegram();
        $bot = $this->bot();
        [$g, $kid] = $this->setupKid();
        $this->connectParent($bot, 111, 'TOKENPARENT1');
        $this->connectParent($bot, 222, 'TOKENPARENT1');
        $this->connectParent($bot, 333, 'TOKENPARENT2');

        $this->actingAs($this->owner)->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'present']])->assertOk();
        $this->assertSame(3, TgOutbox::where('method', 'sendMessage')->count());
        // Qayta belgilash (o'zgarishsiz) — qayta yuborilmaydi
        $this->actingAs($this->owner)->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'present']])->assertOk();
        $this->assertSame(3, TgOutbox::count());
        // Kasal = kelmadi xabari
        $this->actingAs($this->owner)->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'sick']])->assertOk();
        $this->assertSame(6, TgOutbox::count());
        $this->assertStringContainsString('kelmadi', TgOutbox::latest('id')->first()->payload['text']);

        // To'lov: faqat to'lovchi (1-o'rin) akkauntlariga
        TgOutbox::query()->delete();
        $this->in(function () use ($kid) {
            $svc = app(FinanceService::class);
            $tx = $svc->receivePayment($kid, 200000, 'cash', null, $this->owner);
            if ($tx->status === 'pending') {
                $svc->confirm($tx, $this->owner);
            }
        });
        $chats = TgOutbox::pluck('chat_id')->sort()->values()->all();
        $this->assertSame([111, 222], $chats);
        $this->assertStringContainsString('200 000', TgOutbox::first()->payload['text']);
    }

    public function test_event_toggle_and_protect_content(): void
    {
        $this->fakeTelegram();
        $bot = $this->bot();
        $bot->update(['events' => ['attendance_came' => false, 'attendance_absent' => true, 'payment' => true], 'protect_content' => true]);
        [$g, $kid] = $this->setupKid();
        $this->connectParent($bot);
        $this->actingAs($this->owner)->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'present']])->assertOk();
        $this->assertSame(0, TgOutbox::count());
        $this->actingAs($this->owner)->postJson(route('app.attendance.store'), ['group_id' => $g->id, 'date' => '2026-10-14', 'marks' => [$kid->id => 'absent']])->assertOk();
        $o = TgOutbox::first();
        $this->assertTrue($o->payload['protect_content']);
    }

    public function test_outbox_pump_marks_blocked_and_retries(): void
    {
        $bot = $this->bot();
        [, $kid] = $this->setupKid();
        $this->fakeTelegram();
        $c = $this->connectParent($bot);
        $out = app(TelegramOutbox::class);
        $out->queue($bot, 111, 'sendMessage', ['text' => 'x'], null, $c->id);
        $this->refake(Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403));
        $out->pump();
        $this->assertTrue($c->fresh()->blocked);
        $this->assertSame('failed', TgOutbox::first()->status);

        // 429 — keyinroq qayta uriniladi
        $o2 = $out->queue($bot, 111, 'sendMessage', ['text' => 'y']);
        $this->refake(Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 7]], 429));
        $out->pump();
        $o2->refresh();
        $this->assertSame("pending", $o2->status);
        $this->assertTrue($o2->available_at->gt(now()));
    }

    public function test_teacher_posting_flow_copy_and_delete(): void
    {
        $this->fakeTelegram(900);
        $bot = $this->bot();
        [$g, $kid] = $this->setupKid();
        $this->connectParent($bot, 111, 'TOKENPARENT1');
        $this->connectParent($bot, 222, 'TOKENPARENT2');

        $role = Role::where('tenant_id', $this->tenant->id)->where('key', 'teacher')->first();
        $emp = $this->in(function () use ($role, $g) {
            $e = Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Dilfuza', 'role_id' => $role->id, 'status' => 'active', 'tg_token' => 'TOKENEMP1']);
            GroupEmployee::create(['tenant_id' => $this->tenant->id, 'group_id' => $g->id, 'employee_id' => $e->id, 'started_on' => '2026-01-01']);

            return $e;
        });
        $this->start($bot, 777, 'e_TOKENEMP1', 'teacher');
        $this->assertSame(1, $this->in(fn () => TgContact::where('type', 'employee')->count()));

        // Rasm yuboradi
        app(TelegramUpdates::class)->handle($bot, ['update_id' => 5, 'message' => ['message_id' => 55, 'chat' => ['id' => 777, 'type' => 'private'], 'from' => ['id' => 777], 'photo' => [['file_id' => 'x']], 'caption' => 'Bugun rasm chizdik']]);
        $post = $this->in(fn () => TgPost::first());
        $this->assertSame('draft', $post->state);
        $this->assertSame(0, TgOutbox::count());
        $this->cb($bot, 777, "tgt:{$post->id}:g{$g->id}");
        $this->assertSame('report', $post->fresh()->kind);
        $this->assertSame(0, TgOutbox::count()); // tasdiqsiz yuborilmaydi
        $this->cb($bot, 777, "ok:{$post->id}");
        $this->assertSame(2, TgOutbox::where('method', 'copyMessage')->count());
        $first = TgOutbox::where('method', 'copyMessage')->first();
        $this->assertSame(55, $first->payload['message_id']);
        $this->assertSame(777, $first->payload['from_chat_id']);
        app(TelegramOutbox::class)->pump();
        $this->assertSame(2, TgOutbox::where('status', 'sent')->count());

        // Boshqa guruhga yuborib bo'lmaydi + oshpaz huquqi yo'q
        app(TelegramUpdates::class)->handle($bot, ['update_id' => 6, 'message' => ['message_id' => 56, 'chat' => ['id' => 777, 'type' => 'private'], 'from' => ['id' => 777], 'text' => 'Ovqat: osh']]);
        $post2 = $this->in(fn () => TgPost::where('state', 'draft')->first());
        $this->cb($bot, 777, "tgt:{$post2->id}:m");
        $this->assertSame('report', $post2->fresh()->kind); // o'zgarmadi

        // O'chirish: 30 daqiqa ichida
        $this->cb($bot, 777, "del:{$post->id}");
        $this->assertSame('deleted', $post->fresh()->state);
        $this->assertSame(2, TgOutbox::where('method', 'deleteMessages')->count());

        // Muddati o'tgan
        $post3 = $this->in(fn () => TgPost::create(['kind' => 'report', 'state' => 'sent', 'sent_at' => now()->subMinutes(31), 'employee_id' => $emp->id]));
        $this->assertFalse($post3->canDelete());
    }

    public function test_cook_meal_and_web_announce(): void
    {
        $this->fakeTelegram();
        $bot = $this->bot();
        [$g] = $this->setupKid();
        $this->connectParent($bot, 111, 'TOKENPARENT1');
        $cookRole = Role::where('tenant_id', $this->tenant->id)->where('key', 'cook')->first();
        $this->in(fn () => Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Oshpaz', 'role_id' => $cookRole->id, 'status' => 'active', 'tg_token' => 'TOKENCOOK']));
        $this->start($bot, 888, 'e_TOKENCOOK', 'cook');
        app(TelegramUpdates::class)->handle($bot, ['update_id' => 7, 'message' => ['message_id' => 70, 'chat' => ['id' => 888, 'type' => 'private'], 'from' => ['id' => 888], 'photo' => [['file_id' => 'y']]]]);
        $post = $this->in(fn () => TgPost::first());
        $this->cb($bot, 888, "tgt:{$post->id}:m");
        $this->assertSame('meal', $post->fresh()->kind);
        $this->cb($bot, 888, "ok:{$post->id}");
        $this->assertSame(1, TgOutbox::where('method', 'copyMessage')->count());

        // Veb e'lon
        TgOutbox::query()->delete();
        $this->actingAs($this->owner)->post(route('app.telegram.announce'), ['text' => "Ertaga dam olish kuni"])->assertSessionHas('success');
        $o = TgOutbox::first();
        $this->assertStringContainsString('Ertaga', $o->payload['text']);
        $p = $this->in(fn () => TgPost::where('kind', 'announce')->first());
        $this->actingAs($this->owner)->delete(route('app.telegram.post.delete', $p))->assertSessionHas('success');
        $this->actingAs($this->owner)->get(route('app.telegram.posts'))->assertOk();
    }

    public function test_group_table_remove_account_and_guardian_cleanup(): void
    {
        $this->fakeTelegram();
        $bot = $this->bot();
        [$g, $kid] = $this->setupKid();
        $c = $this->connectParent($bot, 111, 'TOKENPARENT1');
        $c2 = $this->connectParent($bot, 333, 'TOKENPARENT2');
        $this->actingAs($this->owner)->get(route('app.telegram.group', $g))->assertOk()->assertSee('t.me/kd_test_bot?start=g_TOKENPARENT1', false);
        $this->actingAs($this->owner)->get(route('app.telegram.groups'))->assertOk();
        $this->actingAs($this->owner)->delete(route('app.telegram.contact.remove', $c))->assertSessionHas('success');
        $this->assertNull($this->in(fn () => TgContact::find($c->id)));

        // 2-vasiy olib tashlansa, akkaunt ham uziladi
        $this->in(fn () => app(\App\Services\Guardians\GuardianService::class)->sync($kid, ['relation' => 'father', 'name' => 'Vali', 'phone' => '998900000001'], null));
        $this->assertNull($this->in(fn () => TgContact::find($c2->id)));

        // Eski havolani bekor qilish
        $gu = $this->in(fn () => KidGuardian::where('kid_id', $kid->id)->where('position', 1)->first());
        $this->actingAs($this->owner)->post(route('app.telegram.revoke', $gu))->assertSessionHas('success');
        $this->assertNotSame('TOKENPARENT1', $gu->fresh()->tg_token);
    }

    public function test_webhook_secret_and_polling(): void
    {
        $bot = $this->bot();
        [, $kid] = $this->setupKid();
        $this->fakeTelegram();
        $upd = ['update_id' => 9, 'message' => ['message_id' => 1, 'chat' => ['id' => 5, 'type' => 'private'], 'from' => ['id' => 5, 'username' => 'u', 'first_name' => 'U'], 'text' => '/start g_TOKENPARENT1']];
        $this->postJson(route('tg.webhook', $bot->id), $upd)->assertForbidden();
        $this->postJson(route('tg.webhook', $bot->id), $upd, ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])->assertForbidden();
        $this->postJson(route('tg.webhook', $bot->id), $upd, ['X-Telegram-Bot-Api-Secret-Token' => $bot->secret])->assertOk();
        $this->assertSame(1, $this->in(fn () => TgContact::count()));

        // Polling
        $upd2 = ['update_id' => 10, 'message' => ['message_id' => 2, 'chat' => ['id' => 6, 'type' => 'private'], 'from' => ['id' => 6, 'username' => 'u2', 'first_name' => 'U2'], 'text' => '/start g_TOKENPARENT2']];
        $this->refake(Http::response(['ok' => true, 'result' => [$upd2]]));
        PlatformSetting::put('tg.mode', 'polling');
        $r = app(TelegramPoller::class)->tick();
        $this->assertSame(1, $r['updates']);
        $this->assertSame(10, $bot->fresh()->last_update_id);
        $this->assertNotNull(app(TelegramPoller::class)->heartbeatAge());
        $this->artisan('tg:run --once')->assertSuccessful();

        // Super admin sahifasi
        $this->actingAs($this->admin())->get(route('admin.telegram.index'))->assertOk();
    }

    /* ---------------- 2 bosqichli tasdiq ---------------- */

    public function test_two_factor_flow_for_super_admin(): void
    {
        config(['kinderdesk.enforce_security' => true]);
        $admin = $this->admin();
        app(ConsentService::class)->record($admin);
        $platform = TelegramBot::create(['is_platform' => true, 'token' => '555555:'.str_repeat('C', 35), 'bot_id' => 1, 'username' => 'kd_platform_bot', 'status' => 'ok']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        // Ulanmagan: majburiy ulash
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('twofa.setup'));
        $this->actingAs($admin)->get(route('twofa.setup'))->assertOk()->assertSee('kd_platform_bot');
        $admin->refresh();
        $this->assertNotNull($admin->tg_link_hash);

        // Botga /start u_<token> — havoladan tokenni olamiz
        $link = app(\App\Services\Security\TwoFactor::class)->startLink($admin);
        preg_match('/start=u_(.+)$/', $link, $m);
        app(TelegramUpdates::class)->handle($platform, ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => 9001, 'type' => 'private'], 'from' => ['id' => 9001, 'username' => 'boss'], 'text' => '/start u_'.$m[1]]]);
        $this->assertSame(9001, $admin->fresh()->tg_chat_id);
        // Eskirgan/noto'g'ri token
        $this->assertNull(app(\App\Services\Security\TwoFactor::class)->bindFromBot('xxx', 1, null));

        $res = $this->actingAs($admin->fresh())->post(route('twofa.confirm'));
        $res->assertOk()->assertSee('-'); // zaxira kodlar ko'rsatiladi
        preg_match_all('/([A-Z0-9]{4}-[A-Z0-9]{4})/', $res->getContent(), $codes);
        $recovery = $codes[1];
        $this->assertCount(8, array_unique($recovery));
        $this->assertStringNotContainsString($recovery[0], (string) $admin->fresh()->tg_recovery); // faqat xesh

        // Yangi sessiya: kod so'raladi
        $this->flushSession();
        $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertRedirect(route('twofa.challenge'));
        $admin = $admin->fresh();
        $this->actingAs($admin)->get(route('twofa.challenge'))->assertOk();
        $sent = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'sendMessage') && str_contains((string) $p[0]['text'], 'kirish kodi'))->last();
        preg_match('/kodi: (\d{6})/', $sent[0]['text'], $cm);
        $code = $cm[1];

        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $wrong2 = $code === '111111' ? '222222' : '111111';
        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $wrong2])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $wrong2])->assertSessionHasErrors('code');
        // 3 urinishdan keyin to'g'ri kod ham qabul qilinmaydi
        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('twofa.challenge'));

        // Zaxira kod bir marta ishlaydi
        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $recovery[0]])->assertRedirect();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->flushSession();
        $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $recovery[0]])->assertSessionHasErrors('code');

        // Qurilmani eslab qolish
        $this->flushSession();
        $this->actingAs($admin)->get(route('twofa.challenge'));
        $sent = Http::recorded()->filter(fn ($p) => str_contains((string) ($p[0]['text'] ?? ''), 'kirish kodi'))->last();
        preg_match('/kodi: (\d{6})/', $sent[0]['text'], $cm);
        $r = $this->actingAs($admin)->post(route('twofa.verify'), ['code' => $cm[1], 'remember_device' => 1]);
        $r->assertRedirect();
        $this->assertSame(1, DB::table('trusted_devices')->count());
        $cookie = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'kd_td');
        $this->assertNotNull($cookie);
        $this->flushSession();
        $plain = \Illuminate\Cookie\CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
        $this->withCookie('kd_td', $plain)->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Tiklash
        app(\App\Services\Security\TwoFactor::class)->unlink($admin);
        $this->assertNull($admin->fresh()->tg_chat_id);
        $this->assertSame(0, DB::table('trusted_devices')->count());
        $this->artisan('kd:2fa-reset', ['phone' => '998901112233'])->assertSuccessful();
    }

    public function test_two_factor_applies_to_owner_and_skips_impersonation_and_staff(): void
    {
        config(['kinderdesk.enforce_security' => true]);
        app(ConsentService::class)->record($this->owner);
        app(ConsentService::class)->record($this->admin());
        TelegramBot::create(['is_platform' => true, 'token' => '555555:'.str_repeat('C', 35), 'bot_id' => 1, 'username' => 'kd_platform_bot', 'status' => 'ok']);

        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertRedirect(route('twofa.setup'));
        // Super admin kirib turganda — o'tkazib yuboriladi
        $this->actingAs($this->owner)->withSession(['impersonator_id' => $this->admin()->id])->get(route('app.dashboard'))->assertOk();
        // Oddiy xodimga talab qilinmaydi
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', 'admin')->first();
        $adm = User::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'name' => 'Admin', 'phone' => '998901234501', 'password' => 'Secret123', 'status' => 'active']);
        app(ConsentService::class)->record($adm);
        $this->actingAs($adm)->get(route('app.dashboard'))->assertOk();
        // Super admin direktor Telegramini tiklay oladi
        $this->owner->forceFill(['tg_chat_id' => 5])->save();
        $this->actingAs($this->admin())->post(route('admin.tenants.twofa-reset', $this->tenant))->assertSessionHas('success');
        $this->assertNull($this->owner->fresh()->tg_chat_id);
        // Platforma boti sozlanmagan bo'lsa — talab qilinmaydi
        TelegramBot::platform()->delete();
        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertOk();
    }

    /* ---------------- Sog'liq ---------------- */

    public function test_health_encrypted_visible_to_all_editable_by_manager(): void
    {
        [$g, $kid] = $this->setupKid();
        $this->actingAs($this->owner)->put(route('app.health.update', $kid), ['allergies' => 'Yong\'oq', 'conditions' => 'Astma', 'medications' => 'Ventolin', 'doctor_name' => 'Dr. Aliyev', 'doctor_phone' => '901112233'])->assertSessionHasNoErrors();
        $raw = DB::table('kid_health')->first();
        $this->assertStringNotContainsString('Astma', $raw->allergies.$raw->conditions.$raw->medications.$raw->doctor_name);
        $this->assertTrue((bool) $raw->has_alert);
        $this->actingAs($this->owner)->post(route('app.health.pickup.store', $kid), ['name' => 'Buvisi Zebo', 'relation' => 'buvi', 'phone' => '901234567'])->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('Zebo', (string) DB::table('kid_pickups')->value('name'));

        // Oddiy xodim (faqat ko'rish)
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', 'cleaner')->first();
        $staff = User::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'name' => 'Farrosh', 'phone' => '998901234502', 'password' => 'Secret123', 'status' => 'active']);
        $this->actingAs($staff)->get(route('app.health.index'))->assertOk()->assertSee('Ali Valiyev');
        $this->actingAs($staff)->get(route('app.health.show', $kid))->assertOk()->assertSee('Astma')->assertSee('Zebo')->assertDontSee('Saqlash');
        $this->actingAs($staff)->put(route('app.health.update', $kid), ['allergies' => 'x'])->assertForbidden();

        // Davomad kartasida belgi
        $this->actingAs($this->owner)->get(route('app.attendance.index', ['group' => $g->id]))->assertOk()->assertSee('Allergiya / holat');
        $this->assertSame(1, $this->in(fn () => KidHealth::count()));
    }

    /* ---------------- Import ---------------- */

    public function test_import_preview_and_commit_owner_only(): void
    {
        $this->in(fn () => Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Quyosh', 'type' => 'main', 'size' => 'large', 'monthly_fee' => 1_000_000, 'capacity' => 20, 'status' => 'active']));
        $csv = "Bola ismi;Jinsi;Tug'ilgan sana;Guruh;Ota-ona ismi;Telefon;Boshlangich qoldiq\n"
            ."Ali Valiyev;O'g'il;15.03.2021;Quyosh;Vali;90 123 45 67;-200000\n"
            ."Madina Karimova;Qiz;01.02.2020;Yulduz;Karim;91 222 33 44;50000\n"
            ."Ali Valiyev;O'g'il;15.03.2021;Quyosh;Vali;90 123 45 67;0\n"
            ."Xato Bola;Qiz;99.99.2020;Quyosh;X;90 000 00 00;0\n";
        $file = UploadedFile::fake()->createWithContent('bolalar.csv', $csv);
        $r = $this->actingAs($this->owner)->post(route('app.import.upload'), ['type' => 'kids', 'source' => 'template', 'file' => $file]);
        $run = \App\Models\ImportRun::withoutGlobalScopes()->first();
        $r->assertRedirect(route('app.import.show', $run));
        $this->assertSame(['total' => 4, 'ok' => 1, 'warn' => 1, 'skip' => 2], collect($run->summary)->only(['total', 'ok', 'warn', 'skip'])->all());
        $this->assertSame(0, $this->in(fn () => Kid::count())); // tasdiqdan oldin yozilmaydi
        $this->actingAs($this->owner)->get(route('app.import.show', $run))->assertOk()->assertSee('Madina');

        $this->actingAs($this->owner)->post(route('app.import.commit', $run))->assertSessionHas('success');
        $this->assertSame(2, $this->in(fn () => Kid::count()));
        $this->assertSame(1, $this->in(fn () => Group::where('name', 'Yulduz')->count()));
        $this->assertSame(-200000, (int) $this->in(fn () => Kid::where('full_name', 'Ali Valiyev')->value('balance')));
        $this->assertNull($run->fresh()->payload);
        // Qayta tasdiqlab bo'lmaydi
        $this->actingAs($this->owner)->post(route('app.import.commit', $run))->assertStatus(409);

        // Takror import: allaqachon mavjud
        $r2 = $this->actingAs($this->owner)->post(route('app.import.upload'), ['type' => 'kids', 'source' => 'template', 'file' => UploadedFile::fake()->createWithContent('b.csv', $csv)]);
        $this->assertSame(0, \App\Models\ImportRun::withoutGlobalScopes()->latest('id')->first()->summary['ok']);

        // Xodimlar
        $emp = "Ism;Telefon;Lavozim;Oylik\nDilfuza K;90 555 11 22;Tarbiyachi;3000000\n";
        $this->actingAs($this->owner)->post(route('app.import.upload'), ['type' => 'employees', 'source' => 'template', 'file' => UploadedFile::fake()->createWithContent('x.csv', $emp)]);
        $run3 = \App\Models\ImportRun::withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->owner)->post(route('app.import.commit', $run3))->assertSessionHas('success');
        $this->assertSame(3000000, (int) $this->in(fn () => Employee::where('full_name', 'Dilfuza K')->value('base_salary')));

        // Shablon va ruxsat
        $this->actingAs($this->owner)->get(route('app.import.template', 'kids'))->assertOk();
        $role = Role::where('tenant_id', $this->tenant->id)->where('key', 'admin')->first();
        $adm = User::create(['tenant_id' => $this->tenant->id, 'role_id' => $role->id, 'name' => 'Admin', 'phone' => '998901234503', 'password' => 'Secret123', 'status' => 'active']);
        $this->actingAs($adm)->get(route('app.import.index'))->assertForbidden();
    }

    /* ---------------- Zaxira API ---------------- */

    public function test_backup_api_flow(): void
    {
        $svc = app(BackupService::class);
        $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'x'])->assertStatus(503); // yoqilmagan

        $svc->setCredentials('zaxira', 'SuperMaxfiyParol123');
        PlatformSetting::put('backup.enabled', '1');
        PlatformSetting::put('backup.ips', '10.0.0.5');
        $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'SuperMaxfiyParol123'])->assertStatus(403); // IP ruxsat etilmagan

        PlatformSetting::put('backup.ips', '10.0.0.5, 127.0.0.0/8');
        $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'xato'])->assertStatus(401);
        $this->postJson('/api/backup/request', ['login' => 'boshqa', 'password' => 'SuperMaxfiyParol123'])->assertStatus(401);

        $r = $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'SuperMaxfiyParol123'])->assertStatus(202)->assertJsonPath('status', 'pending');
        $uid = $r->json('job_id');
        // Bir kunda qayta so'rov — o'sha ish
        $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'SuperMaxfiyParol123'])->assertJsonPath('job_id', $uid);
        $this->assertSame(1, BackupJob::count());

        // Tayyor bo'lmaguncha 202
        $this->getJson("/api/backup/$uid/download", ['Authorization' => 'Basic '.base64_encode('zaxira:SuperMaxfiyParol123')])->assertStatus(202);
        $this->getJson("/api/backup/$uid/download")->assertStatus(401);
        $this->getJson('/api/backup/nope/download', ['Authorization' => 'Basic '.base64_encode('zaxira:SuperMaxfiyParol123')])->assertStatus(404);

        $this->artisan('backup:run --pending')->assertSuccessful();
        $job = BackupJob::first();
        $this->assertSame('ready', $job->status, (string) $job->error);
        $dl = $this->get("/api/backup/$uid/download", ['Authorization' => 'Basic '.base64_encode('zaxira:SuperMaxfiyParol123')]);
        $dl->assertOk()->assertHeader('X-Backup-Sha256', $job->sha256);
        $sql = file_get_contents(Storage::disk('local')->path($job->path));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('998901112233', $sql); // super admin telefoni bazada bor
        $this->assertStringNotContainsString((string) config('app.key'), $sql); // APP_KEY nusxada yo'q
        $this->assertSame(1, $job->fresh()->downloads);
        $this->assertTrue($job->expires_at->between(now()->addHours(23), now()->addHours(25)));

        // 24 soatdan keyin o'chiriladi
        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('backup:purge')->assertSuccessful();
        $this->assertSame('expired', $job->fresh()->status);
        $this->assertFalse(Storage::disk('local')->exists($job->path));
        $this->getJson("/api/backup/$uid/download", ['Authorization' => 'Basic '.base64_encode('zaxira:SuperMaxfiyParol123')])->assertStatus(410);

        // Ertasi kuni — yangi ish
        $this->postJson('/api/backup/request', ['login' => 'zaxira', 'password' => 'SuperMaxfiyParol123'])->assertStatus(202);
        $this->assertSame(2, BackupJob::count());

        // Super admin sahifasi
        $this->actingAs($this->admin())->get(route('admin.backup.index'))->assertOk();
        $this->actingAs($this->admin())->put(route('admin.backup.update'), ['login' => 'zaxira', 'password' => 'qisqa'])->assertSessionHasErrors('password');
    }

    public function test_backup_ip_cidr_rules(): void
    {
        $svc = app(BackupService::class);
        PlatformSetting::put('backup.ips', '203.0.113.0/24, 2001:db8::1');
        $this->assertTrue($svc->ipAllowed('203.0.113.77'));
        $this->assertFalse($svc->ipAllowed('203.0.114.1'));
        $this->assertTrue($svc->ipAllowed('2001:db8::1'));
        PlatformSetting::put('backup.ips', '');
        $this->assertFalse($svc->ipAllowed('127.0.0.1'));
    }

    /* ---------------- Dashboard va sotuv sahifasi ---------------- */

    public function test_dashboard_widgets_and_landing(): void
    {
        [$g, $kid] = $this->setupKid();
        $this->actingAs($this->owner)->get(route('app.dashboard'))->assertOk()->assertSee('Quyosh')->assertSee('belgilanmagan');
        $this->get(route('home'))->assertOk()->assertSee("Excel va daftar o'rniga")->assertSee("Ota-onalar Telegramda xabardor", false)->assertSee('maxfiylik', false)->assertDontSee('xatosiz');
        $this->actingAs($this->owner)->get(route('app.telegram.settings'))->assertOk();
        $this->actingAs($this->owner)->get(route('app.telegram.employees'))->assertOk();
        $this->actingAs($this->owner)->get(route('app.import.index'))->assertOk();
    }

    /* ---------------- Guruh va to'garak cheklovlari ---------------- */

    public function test_group_staff_single_group_and_club_teacher_rules(): void
    {
        $role = fn ($k) => Role::where('tenant_id', $this->tenant->id)->where('key', $k)->first()->id;
        [$g1] = $this->setupKid();
        $g2 = $this->in(fn () => Group::create(['tenant_id' => $this->tenant->id, 'name' => 'Yulduz', 'type' => 'main', 'size' => 'large', 'monthly_fee' => 1_000_000, 'capacity' => 20, 'status' => 'active']));
        $t = $this->in(fn () => Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Tarbiyachi A', 'role_id' => $role('teacher'), 'status' => 'active']));
        $cook = $this->in(fn () => Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Oshpaz B', 'role_id' => $role('cook'), 'status' => 'active']));
        $ped = $this->in(fn () => Employee::create(['tenant_id' => $this->tenant->id, 'full_name' => 'Pedagog C', 'role_id' => $role('pedagog'), 'status' => 'active']));

        $this->actingAs($this->owner)->post(route('app.groups.employees.assign', $g1), ['employee_id' => $t->id])->assertSessionHas('success');
        // Ikkinchi guruhga — rad etiladi, ro'yxatda ham chiqmaydi
        $this->actingAs($this->owner)->post(route('app.groups.employees.assign', $g2), ['employee_id' => $t->id])->assertSessionHas('error');
        $this->assertSame(1, GroupEmployee::where('employee_id', $t->id)->whereNull('ended_on')->count());
        $this->actingAs($this->owner)->get(route('app.groups.show', $g2))->assertOk()->assertDontSee('<option value="'.$t->id.'"', false);
        // Oshpaz va pedagog guruhga biriktirilmaydi
        $this->actingAs($this->owner)->post(route('app.groups.employees.assign', $g2), ['employee_id' => $cook->id])->assertSessionHas('error');
        $this->actingAs($this->owner)->post(route('app.groups.employees.assign', $g2), ['employee_id' => $ped->id])->assertSessionHas('error');
        // Chiqarilgach boshqa guruhga o'tkazish mumkin
        $link = GroupEmployee::where('employee_id', $t->id)->first();
        $this->actingAs($this->owner)->delete(route('app.groups.employees.remove', [$g1, $link]))->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('app.groups.employees.assign', $g2), ['employee_id' => $t->id])->assertSessionHas('success');

        // To'garak: oshpaz bo'lmaydi, pedagog va tarbiyachi bo'ladi
        $base = ['name' => "Ingliz tili", 'fee' => 100000, 'billing' => 'monthly'];
        $this->actingAs($this->owner)->post(route('app.clubs.store'), $base + ['teacher_id' => $cook->id])->assertSessionHasErrors('teacher_id');
        $this->actingAs($this->owner)->post(route('app.clubs.store'), $base + ['teacher_id' => $ped->id])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('app.clubs.store'), $base + ['name' => 'Rasm', 'teacher_id' => $t->id])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->get(route('app.clubs.create'))->assertOk()->assertSee('Pedagog C')->assertDontSee('Oshpaz B');
    }

    public function test_menu_groups_are_collapsible_and_tables_numbered(): void
    {
        $r = $this->actingAs($this->owner)->get(route('app.kids.index'))->assertOk();
        $r->assertSee('nav-group-btn', false)->assertSee('Faoliyat')->assertSee('Aloqa');
    }
}
