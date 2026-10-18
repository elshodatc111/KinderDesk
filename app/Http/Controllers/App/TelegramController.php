<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Group;
use App\Models\GroupEmployee;
use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\KidGuardian;
use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\TgPost;
use App\Services\Telegram\BotManager;
use App\Services\Telegram\TelegramLinks;
use App\Services\Telegram\TgPostService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    private function bot(): ?TelegramBot
    {
        return TelegramBot::forTenant(tenant()->id);
    }

    /** Foydalanuvchi ko'ra oladigan guruhlar: direktor/guruh boshqaruvchisi — hammasi, tarbiyachi — o'zinikilar. */
    private function groups(Request $request)
    {
        $user = $request->user();
        $all = Group::active()->orderBy('name')->get();
        if ($user->is_owner || $user->can('groups.manage')) {
            return $all;
        }
        $emp = Employee::where('user_id', $user->id)->first();
        $mine = $emp ? GroupEmployee::where('employee_id', $emp->id)->whereNull('ended_on')->pluck('group_id') : collect();

        return $all->whereIn('id', $mine)->values();
    }

    /* ---------- Bot sozlamalari (direktor) ---------- */

    public function settings(Request $request)
    {
        $bot = $this->bot();

        return view('app.telegram.settings', [
            'bot' => $bot, 'events' => config('kinderdesk.telegram.events'), 'vars' => config('kinderdesk.telegram.vars'),
            'connected' => $bot ? TgContact::where('bot_id', $bot->id)->where('type', 'guardian')->where('consented', true)->where('blocked', false)->distinct('chat_id')->count('chat_id') : 0,
            'kidsTotal' => Kid::active()->count(),
        ]);
    }

    public function save(Request $request, BotManager $manager)
    {
        $data = $request->validate([
            'token' => 'nullable|string|max:200', 'name' => 'nullable|string|max:64', 'description' => 'nullable|string|max:512',
            'short_description' => 'nullable|string|max:120', 'protect_content' => 'nullable|boolean',
            'events' => 'nullable|array', 'templates' => 'nullable|array', 'templates.*' => 'nullable|string|max:600',
        ]);
        $bot = $this->bot() ?? new TelegramBot(['tenant_id' => tenant()->id, 'is_platform' => false]);
        $warnings = [];
        try {
            if (filled($data['token'] ?? null)) {
                $manager->connect($bot, $data['token']);
            }
        } catch (\DomainException $e) {
            return back()->withInput($request->except('token'))->with('error', $e->getMessage());
        }
        if (! $bot->exists) {
            return back()->with('error', "Avval bot tokenini kiriting");
        }
        $events = [];
        foreach (array_keys(config('kinderdesk.telegram.events')) as $k) {
            $events[$k] = (bool) ($data['events'][$k] ?? false);
        }
        $templates = [];
        foreach (array_keys(config('kinderdesk.telegram.templates')) as $k) {
            $v = trim((string) ($data['templates'][$k] ?? ''));
            $templates[$k] = $v === trim((string) config("kinderdesk.telegram.templates.$k")) ? '' : $v;
        }
        $bot->fill([
            'name' => $data['name'] ?? $bot->name, 'description' => $data['description'] ?? null, 'short_description' => $data['short_description'] ?? null,
            'protect_content' => $request->boolean('protect_content'), 'events' => $events, 'templates' => $templates,
        ])->save();
        $warnings = $manager->applyProfile($bot);

        $resp = back()->with('success', 'Saqlandi');

        return $warnings ? $resp->with('error', "Bot profili qisman yangilandi: ".implode('; ', $warnings).". Rasm va ba'zi sozlamalarni @BotFather orqali o'zgartiring.") : $resp;
    }

    public function check(BotManager $manager)
    {
        $bot = $this->bot();
        abort_unless($bot, 404);
        $r = $bot->api()->getMe();
        if ($r['ok'] ?? false) {
            $bot->forceFill(['status' => 'ok', 'last_error' => null, 'last_ok_at' => now()])->saveQuietly();
            $err = $manager->deliveryFor($bot);

            return back()->with($err ? 'error' : 'success', $err ?: 'Bot ishlayapti');
        }
        $bot->forceFill(['status' => 'error', 'last_error' => mb_substr((string) ($r['description'] ?? ''), 0, 250)])->saveQuietly();

        return back()->with('error', 'Bot javob bermadi: '.($r['description'] ?? ''));
    }

    public function disconnect()
    {
        $bot = $this->bot();
        if ($bot) {
            if ($bot->token) {
                $bot->api()->call('deleteWebhook');
            }
            $bot->delete();
        }

        return back()->with('success', 'Bot uzildi');
    }

    /* ---------- Guruhlar va ota-onalar jadvali ---------- */

    public function groupsIndex(Request $request)
    {
        $bot = $this->bot();
        $groups = $this->groups($request);
        $stats = [];
        foreach ($groups as $g) {
            $kidIds = GroupKid::where('group_id', $g->id)->whereNull('ended_on')->pluck('kid_id');
            $stats[$g->id] = [
                'kids' => $kidIds->count(),
                'connected' => $bot ? TgContact::where('bot_id', $bot->id)->where('type', 'guardian')->where('consented', true)->whereIn('kid_id', $kidIds)->distinct('kid_id')->count('kid_id') : 0,
            ];
        }

        return view('app.telegram.groups', compact('bot', 'groups', 'stats'));
    }

    public function group(Request $request, Group $group, TelegramLinks $links)
    {
        abort_unless($this->groups($request)->contains('id', $group->id), 403);
        $bot = $this->bot();
        $kidIds = GroupKid::where('group_id', $group->id)->whereNull('ended_on')->pluck('kid_id');
        $kids = Kid::whereIn('id', $kidIds)->with('guardians')->orderBy('full_name')->get();
        $contacts = $bot ? TgContact::where('bot_id', $bot->id)->where('type', 'guardian')->whereIn('kid_id', $kidIds)->orderBy('id')->get()->groupBy('kid_id') : collect();
        $rows = $kids->map(fn ($k) => [
            'kid' => $k,
            'guardians' => $k->guardians->map(fn ($g) => [
                'g' => $g, 'link' => $bot && $bot->username ? $links->guardianLink($bot, $g) : null,
                'contacts' => ($contacts[$k->id] ?? collect())->where('guardian_position', $g->position)->values(),
            ]),
        ]);

        return view('app.telegram.group', compact('bot', 'group', 'rows'));
    }

    public function removeContact(Request $request, TgContact $contact)
    {
        abort_unless($contact->type === 'guardian', 403);
        $u = $request->user();
        if (! $u->is_owner && ! $u->can('groups.manage')) {
            $allowed = GroupKid::whereIn('group_id', $this->groups($request)->pluck('id'))->whereNull('ended_on')->where('kid_id', $contact->kid_id)->exists();
            abort_unless($allowed, 403);
        }
        $bot = $contact->bot;
        if ($bot && $bot->token) {
            $bot->api()->send($contact->chat_id, "Bu akkaunt bog'cha botidan uzildi.");
        }
        $contact->delete();

        return back()->with('success', 'Akkaunt uzildi');
    }

    public function revoke(Request $request, KidGuardian $guardian, TelegramLinks $links)
    {
        $links->regenerate($guardian);

        return back()->with('success', "Eski havola bekor qilindi, yangisi yaratildi");
    }

    /* ---------- Xodimlar ---------- */

    public function employees(TelegramLinks $links)
    {
        $bot = $this->bot();
        $emps = Employee::whereNull('left_on')->orderBy('full_name')->get();
        $contacts = $bot ? TgContact::where('bot_id', $bot->id)->where('type', 'employee')->get()->keyBy('employee_id') : collect();
        $rows = $emps->map(fn ($e) => ['e' => $e, 'link' => $bot && $bot->username ? $links->employeeLink($bot, $e) : null, 'contact' => $contacts[$e->id] ?? null]);

        return view('app.telegram.employees', compact('bot', 'rows'));
    }

    public function removeEmployeeContact(TgContact $contact)
    {
        abort_unless($contact->type === 'employee', 403);
        $contact->delete();

        return back()->with('success', 'Xodim akkaunti uzildi');
    }

    /* ---------- E'lonlar va postlar ---------- */

    public function posts(Request $request)
    {
        $bot = $this->bot();

        return view('app.telegram.posts', [
            'bot' => $bot, 'groups' => Group::active()->orderBy('name')->get(),
            'posts' => TgPost::with(['group', 'employee'])->latest('id')->where('state', '!=', 'draft')->limit(60)->get(),
            'canSend' => $request->user()->can('tg.broadcast'),
        ]);
    }

    public function announce(Request $request, TgPostService $posts)
    {
        $data = $request->validate(['text' => 'required|string|max:3500', 'group_id' => 'nullable|integer']);
        $bot = $this->bot();
        if (! $bot || ! $bot->usable()) {
            return back()->with('error', 'Avval Telegram botni ulang');
        }
        if (! empty($data['group_id']) && ! Group::where('id', $data['group_id'])->exists()) {
            abort(404);
        }
        $post = TgPost::create(['kind' => 'announce', 'state' => 'draft', 'group_id' => $data['group_id'] ?? null, 'user_id' => $request->user()->id, 'text' => $data['text']]);
        $n = $posts->send($post, $bot);

        return back()->with('success', "{$n} ta ota-onaga yuborilmoqda");
    }

    public function deletePost(TgPost $post, TgPostService $posts)
    {
        $bot = $this->bot();
        abort_unless($bot, 404);

        return $posts->delete($post, $bot)
            ? back()->with('success', "Postni o'chirish boshlandi")
            : back()->with('error', "O'chirish muddati (".config('kinderdesk.telegram.delete_minutes')." daqiqa) o'tgan");
    }
}
