<?php

namespace App\Services\Telegram;

use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\Kid;
use App\Models\KidGuardian;
use App\Models\Role;
use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\TgPost;
use App\Services\Security\TwoFactor;
use App\Support\Tenancy\TenantContext;

/** Botga kelgan yangilanishlar: ulanish (start-havola), rozilik, tarbiyachi/oshpaz posti, o'chirish. */
class TelegramUpdates
{
    public function __construct(private TgPostService $posts, private TwoFactor $twoFactor)
    {
    }

    public function handle(TelegramBot $bot, array $update): void
    {
        try {
            if ($bot->is_platform) {
                $this->platform($bot, $update);

                return;
            }
            $tenant = $bot->tenant;
            if (! $tenant) {
                return;
            }
            app(TenantContext::class)->run($tenant, function () use ($bot, $update) {
                if (isset($update['callback_query'])) {
                    $this->callback($bot, $update['callback_query']);
                } elseif (isset($update['message'])) {
                    $this->message($bot, $update['message']);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* ---------------- Platforma boti: 2 bosqichli tasdiq uchun ulash ---------------- */

    private function platform(TelegramBot $bot, array $update): void
    {
        $m = $update['message'] ?? null;
        if (! $m || ($m['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $chat = (int) $m['chat']['id'];
        $text = trim((string) ($m['text'] ?? ''));
        if (preg_match('~^/start\s+u_([A-Za-z0-9_-]+)$~', $text, $mm)) {
            $user = $this->twoFactor->bindFromBot($mm[1], $chat, $m['from']['username'] ?? null);
            $bot->api()->send($chat, $user
                ? "✅ Ulandi. Endi tizimga kirganda tasdiqlash kodi shu yerga keladi."
                : "Havola eskirgan yoki noto'g'ri. Tizimdagi sahifadan yangi havola oling.");

            return;
        }
        $bot->api()->send($chat, "KinderDesk xavfsizlik boti. Ulash uchun tizimdagi «Xavfsizlik» sahifasidagi havoladan foydalaning.");
    }

    /* ---------------- Bog'cha boti ---------------- */

    private function message(TelegramBot $bot, array $m): void
    {
        if (($m['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $chat = (int) $m['chat']['id'];
        $text = trim((string) ($m['text'] ?? ''));
        $api = $bot->api();

        if (str_starts_with($text, '/start')) {
            $payload = trim(substr($text, 6));
            $this->start($bot, $m, $payload);

            return;
        }
        if ($text === '/stop') {
            $n = TgContact::where('bot_id', $bot->id)->where('chat_id', $chat)->delete();
            $api->send($chat, $n ? "Obuna bekor qilindi. Qayta ulanish uchun bog'cha bergan havoladan foydalaning." : "Siz ulanmagansiz.");

            return;
        }
        if ($text === '/help' || ($text !== '' && str_starts_with($text, '/'))) {
            $api->send($chat, "Bu bot bog'chadan xabarlar yuboradi. Ulanish uchun bog'cha bergan shaxsiy havoladan foydalaning. Obunani bekor qilish: /stop");

            return;
        }

        $this->content($bot, $m);
    }

    private function start(TelegramBot $bot, array $m, string $payload): void
    {
        $chat = (int) $m['chat']['id'];
        $api = $bot->api();
        $from = $m['from'] ?? [];

        if (preg_match('/^g_([A-Za-z0-9]+)$/', $payload, $mm)) {
            $g = KidGuardian::where('tg_token', $mm[1])->first();
            if (! $g || $g->tenant_id !== $bot->tenant_id) {
                $api->send($chat, "Havola noto'g'ri yoki bekor qilingan. Bog'chadan yangi havola so'rang.");

                return;
            }
            $c = TgContact::firstOrNew(['bot_id' => $bot->id, 'type' => 'guardian', 'kid_id' => $g->kid_id, 'guardian_position' => $g->position, 'chat_id' => $chat]);
            $c->tenant_id = $bot->tenant_id;
            $c->fill(['tg_user_id' => $from['id'] ?? null, 'username' => $from['username'] ?? null, 'first_name' => mb_substr((string) ($from['first_name'] ?? ''), 0, 120), 'blocked' => false]);
            // Shu chat boshqa bola uchun avval rozilik bergan bo'lsa — qayta so'ramaymiz
            if (! $c->consented && TgContact::where('bot_id', $bot->id)->where('chat_id', $chat)->where('consented', true)->exists()) {
                $c->consented = true;
                $c->consented_at = now();
            }
            $c->save();
            if ($c->consented) {
                $api->send($chat, "✅ Ulandi: ".Kid::find($g->kid_id)?->full_name.". Endi shu yerda davomad, to'lov va guruh yangiliklari keladi.");

                return;
            }
            $api->send($chat, "Assalomu alaykum! «".($bot->tenant->name)."» bog'chasi botiga ulanmoqdasiz.\n\nDavom etish uchun rozilik bering: farzandingizning davomadi, to'lov holati haqidagi xabarlar hamda guruhdagi rasm va videolarni (boshqa ota-onalar ham ko'rishi mumkin) shu Telegram orqali olishga va ma'lumotlaringiz shu maqsadda qayta ishlanishiga roziman.", [
                'reply_markup' => ['inline_keyboard' => [[['text' => '✅ Roziman', 'callback_data' => "cons:{$c->id}:1"], ['text' => '❌ Rad etaman', 'callback_data' => "cons:{$c->id}:0"]]]],
            ]);

            return;
        }

        if (preg_match('/^e_([A-Za-z0-9]+)$/', $payload, $mm)) {
            $e = Employee::where('tg_token', $mm[1])->first();
            if (! $e || $e->tenant_id !== $bot->tenant_id) {
                $api->send($chat, "Havola noto'g'ri yoki bekor qilingan.");

                return;
            }
            TgContact::where('bot_id', $bot->id)->where('type', 'employee')->where('employee_id', $e->id)->delete();
            TgContact::create([
                'tenant_id' => $bot->tenant_id, 'bot_id' => $bot->id, 'type' => 'employee', 'employee_id' => $e->id, 'chat_id' => $chat,
                'tg_user_id' => $from['id'] ?? null, 'username' => $from['username'] ?? null, 'first_name' => mb_substr((string) ($from['first_name'] ?? ''), 0, 120),
                'consented' => true, 'consented_at' => now(),
            ]);
            $api->send($chat, "✅ Ulandi. Rasm, video yoki matn yuboring — men guruh ota-onalariga yuborishni so'rayman.");

            return;
        }

        $api->send($chat, "Assalomu alaykum! Ulanish uchun bog'cha bergan shaxsiy havoladan foydalaning.");
    }

    /* ---------------- Xodim kontenti ---------------- */

    private function perms(Employee $e): array
    {
        $role = $e->role_id ? Role::find($e->role_id) : null;
        $out = [];
        foreach (['report' => 'tg.report', 'meal' => 'tg.meal', 'announce' => 'tg.broadcast'] as $k => $perm) {
            if ($role?->allows($perm)) {
                $out[] = $k;
            }
        }

        return $out;
    }

    private function content(TelegramBot $bot, array $m): void
    {
        $chat = (int) $m['chat']['id'];
        $api = $bot->api();
        $contact = TgContact::where('bot_id', $bot->id)->where('chat_id', $chat)->where('type', 'employee')->first();
        if (! $contact || ! ($emp = Employee::find($contact->employee_id)) || ($emp->left_on && $emp->left_on->isPast())) {
            $api->send($chat, "Xabar yuborish faqat bog'chaga ulangan xodimlar uchun.");

            return;
        }
        $perms = $this->perms($emp);
        if (! $perms) {
            $api->send($chat, "Sizda ota-onalarga xabar yuborish huquqi yo'q. Direktorga murojaat qiling.");

            return;
        }
        $mid = (int) $m['message_id'];
        $album = $m['media_group_id'] ?? null;
        if ($album && ($draft = TgPost::where('employee_id', $emp->id)->where('media_group_id', $album)->where('state', 'draft')->first())) {
            $draft->update(['source_message_ids' => array_values(array_unique(array_merge($draft->source_message_ids ?? [], [$mid])))]);

            return;
        }
        $text = (string) ($m['text'] ?? '');
        $draft = TgPost::create([
            'kind' => in_array('report', $perms, true) ? 'report' : (in_array('meal', $perms, true) ? 'meal' : 'announce'), 'state' => 'draft',
            'employee_id' => $emp->id, 'source_chat_id' => $chat, 'source_message_ids' => [$mid], 'media_group_id' => $album,
        ]);

        $rows = [];
        if (in_array('report', $perms, true)) {
            $gids = GroupEmployee::where('employee_id', $emp->id)->whereNull('ended_on')->pluck('group_id');
            foreach (Group::active()->whereIn('id', $gids)->orderBy('name')->get() as $g) {
                $rows[] = [['text' => "📋 Kunlik hisobot: {$g->name}", 'callback_data' => "tgt:{$draft->id}:g{$g->id}"]];
            }
        }
        if (in_array('meal', $perms, true)) {
            $rows[] = [['text' => "🍲 Ovqat — barcha ota-onalarga", 'callback_data' => "tgt:{$draft->id}:m"]];
        }
        if (in_array('announce', $perms, true)) {
            $rows[] = [['text' => "📢 E'lon — barcha ota-onalarga", 'callback_data' => "tgt:{$draft->id}:a"]];
            foreach (Group::active()->orderBy('name')->get() as $g) {
                $rows[] = [['text' => "📢 E'lon: {$g->name}", 'callback_data' => "tgt:{$draft->id}:A{$g->id}"]];
            }
        }
        if (! $rows) {
            $draft->delete();
            $api->send($chat, "Sizga biriktirilgan guruh yo'q.");

            return;
        }
        $rows[] = [['text' => '✖️ Bekor qilish', 'callback_data' => "no:{$draft->id}"]];
        $api->send($chat, "Kimga yuboray?", ['reply_markup' => ['inline_keyboard' => $rows]]);
    }

    /* ---------------- Tugmalar ---------------- */

    private function callback(TelegramBot $bot, array $cb): void
    {
        $api = $bot->api();
        $data = (string) ($cb['data'] ?? '');
        $chat = (int) ($cb['message']['chat']['id'] ?? ($cb['from']['id'] ?? 0));
        $mid = $cb['message']['message_id'] ?? null;
        $answer = fn (string $t = '') => $api->call('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => $t]);
        $edit = fn (string $t, array $kb = []) => $mid ? $api->call('editMessageText', ['chat_id' => $chat, 'message_id' => $mid, 'text' => $t] + ($kb ? ['reply_markup' => ['inline_keyboard' => $kb]] : [])) : null;

        // Rozilik
        if (preg_match('/^cons:(\d+):([01])$/', $data, $mm)) {
            $c = TgContact::where('id', $mm[1])->where('bot_id', $bot->id)->where('chat_id', $chat)->first();
            if (! $c) {
                $answer();

                return;
            }
            if ($mm[2] === '1') {
                TgContact::where('bot_id', $bot->id)->where('chat_id', $chat)->update(['consented' => true, 'consented_at' => now()]);
                $answer('Rahmat!');
                $edit("✅ Ulandi: ".Kid::find($c->kid_id)?->full_name.". Endi shu yerda davomad, to'lov va guruh yangiliklari keladi.");
            } else {
                $c->delete();
                $answer();
                $edit("Rozilik berilmadi, xabarlar yuborilmaydi. Xohlasangiz, havola orqali qayta ulaning.");
            }

            return;
        }

        // Quyidagilar — faqat ulangan xodim uchun
        $contact = TgContact::where('bot_id', $bot->id)->where('chat_id', $chat)->where('type', 'employee')->first();
        $emp = $contact ? Employee::find($contact->employee_id) : null;
        if (! $emp) {
            $answer();

            return;
        }
        $perms = $this->perms($emp);

        if (preg_match('/^no:(\d+)$/', $data, $mm)) {
            TgPost::where('id', $mm[1])->where('employee_id', $emp->id)->where('state', 'draft')->delete();
            $answer();
            $edit('Bekor qilindi.');

            return;
        }

        if (preg_match('/^tgt:(\d+):(g|m|a|A)(\d*)$/', $data, $mm)) {
            $post = TgPost::where('id', $mm[1])->where('employee_id', $emp->id)->where('state', 'draft')->first();
            if (! $post) {
                $answer('Eskirgan');

                return;
            }
            [$kind, $need] = match ($mm[2]) { 'g' => ['report', 'report'], 'm' => ['meal', 'meal'], default => ['announce', 'announce'] };
            if (! in_array($need, $perms, true)) {
                $answer("Ruxsat yo'q");

                return;
            }
            $gid = $mm[3] !== '' ? (int) $mm[3] : null;
            if ($gid && ! Group::where('id', $gid)->exists()) {
                $answer();

                return;
            }
            if ($kind === 'report' && ! GroupEmployee::where('employee_id', $emp->id)->where('group_id', $gid)->whereNull('ended_on')->exists()) {
                $answer("Bu guruh sizga biriktirilmagan");

                return;
            }
            $post->update(['kind' => $kind, 'group_id' => $gid]);
            $n = $this->posts->recipients($post, $bot)->count();
            $answer();
            if ($n === 0) {
                $edit("Hozircha Telegramga ulangan va rozilik bergan ota-ona yo'q. Ota-onalarga shaxsiy havola yuboring.");

                return;
            }
            $edit("{$n} ta ota-onaga yuborilsinmi?", [[['text' => '✅ Yuborish', 'callback_data' => "ok:{$post->id}"], ['text' => '✖️ Yo\'q', 'callback_data' => "no:{$post->id}"]]]);

            return;
        }

        if (preg_match('/^ok:(\d+)$/', $data, $mm)) {
            $post = TgPost::where('id', $mm[1])->where('employee_id', $emp->id)->where('state', 'draft')->first();
            if (! $post) {
                $answer('Eskirgan');

                return;
            }
            $n = $this->posts->send($post, $bot);
            $answer();
            $edit("✅ {$n} ta ota-onaga yuborilmoqda.", [[['text' => "🗑 O'chirish (".config('kinderdesk.telegram.delete_minutes')." daqiqa ichida)", 'callback_data' => "del:{$post->id}"]]]);

            return;
        }

        if (preg_match('/^del:(\d+)$/', $data, $mm)) {
            $post = TgPost::where('id', $mm[1])->where('employee_id', $emp->id)->first();
            if ($post && $this->posts->delete($post, $bot)) {
                $answer("O'chirilmoqda");
                $edit("🗑 O'chirildi.");
            } else {
                $answer("O'chirish muddati o'tgan");
            }

            return;
        }
        $answer();
    }
}
