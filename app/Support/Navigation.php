<?php

namespace App\Support;

use App\Models\User;

/** Yon menyu: xususiyat (feature) va ruxsat (permission) bo'yicha filtrlanadi. */
class Navigation
{
    /** @return array<int, array{group:string, items:array}> */
    public static function tenant(User $user): array
    {
        $all = [
            ['group' => '', 'items' => [
                ['label' => 'Bosh sahifa', 'route' => 'app.dashboard', 'match' => 'app.dashboard', 'icon' => 'home', 'color' => 'teal'],
            ]],
            ['group' => 'Bolalar', 'items' => [
                ['label' => 'Bolalar', 'route' => 'app.kids.index', 'match' => 'app.kids.*', 'icon' => 'kids', 'color' => 'berry', 'feature' => 'kids', 'can' => 'kids.view'],
                ['label' => 'Guruhlar', 'route' => 'app.groups.index', 'match' => 'app.groups.*', 'icon' => 'groups', 'color' => 'sky', 'feature' => 'groups', 'can' => 'groups.view'],
                ['label' => 'Davomad', 'route' => 'app.attendance.index', 'match' => 'app.attendance.*', 'icon' => 'attendance', 'color' => 'leaf', 'feature' => 'attendance', 'can' => 'attendance.kids'],
                ['label' => 'Oilalar', 'route' => 'app.families.index', 'match' => 'app.families.*', 'icon' => 'users', 'color' => 'berry', 'feature' => 'family_discount', 'can' => 'family.manage'],
                ['label' => "Qora ro'yxat", 'route' => 'app.blacklist.index', 'match' => 'app.blacklist.*', 'icon' => 'ban', 'color' => 'berry', 'feature' => 'blacklist', 'can' => 'blacklist.view'],
                ['label' => 'Arizalar', 'route' => 'app.leads.index', 'match' => 'app.leads.*', 'icon' => 'inbox', 'color' => 'sun', 'feature' => 'leads', 'can' => 'leads.view'],
                ['label' => "Sog'liq", 'route' => 'app.health.index', 'match' => 'app.health.*', 'icon' => 'info', 'color' => 'berry', 'feature' => 'health'],
            ]],
            ['group' => 'Faoliyat', 'items' => [
                ['label' => "To'garaklar", 'route' => 'app.clubs.index', 'match' => 'app.clubs.*', 'icon' => 'sparkles', 'color' => 'grape', 'feature' => 'clubs', 'can' => 'clubs.view'],
                ['label' => 'Tadbirlar', 'route' => 'app.events.index', 'match' => 'app.events.*', 'icon' => 'calendar', 'color' => 'berry', 'feature' => 'events', 'can' => 'events.manage'],
                ['label' => 'Guruh tadbirlari', 'route' => 'app.group-events.index', 'match' => 'app.group-events.*', 'icon' => 'sparkles', 'color' => 'sun', 'feature' => 'events', 'can' => 'events.manage'],
                ['label' => 'Fanlar va darslar', 'route' => 'app.subjects.index', 'match' => 'app.subjects.*', 'icon' => 'sparkles', 'color' => 'grape', 'feature' => 'subjects', 'can' => 'subjects.manage'],
            ]],
            ['group' => 'Xodimlar', 'items' => [
                ['label' => 'Xodimlar', 'route' => 'app.employees.index', 'match' => 'app.employees.*', 'icon' => 'employees', 'color' => 'grape', 'feature' => 'employees', 'can' => 'employees.view'],
                ['label' => 'Xodimlar davomadi', 'route' => 'app.staff-attendance.index', 'match' => 'app.staff-attendance.*', 'icon' => 'staff-attendance', 'color' => 'leaf', 'feature' => 'staff_attendance', 'can' => 'attendance.staff'],
                ['label' => "Almashtirish", 'route' => 'app.substitutions.index', 'match' => 'app.substitutions.*', 'icon' => 'refresh', 'color' => 'sky', 'feature' => 'substitution', 'can' => 'substitution.manage'],
                ['label' => 'Ish kalendari', 'route' => 'app.calendar.index', 'match' => 'app.calendar.*', 'icon' => 'calendar', 'color' => 'sky', 'feature' => 'calendar', 'can' => 'calendar.view'],
                ['label' => 'Hisobot zanjiri', 'route' => 'app.chain.index', 'match' => 'app.chain.*', 'icon' => 'report', 'color' => 'leaf', 'feature' => 'report_chain'],
            ]],
            ['group' => 'Moliya', 'items' => [
                ['label' => 'Kassa', 'route' => 'app.desk.index', 'match' => 'app.desk.*', 'icon' => 'cash', 'color' => 'leaf', 'feature' => 'desk', 'can' => 'desk.view'],
                ['label' => 'Moliya', 'route' => 'app.finance.index', 'match' => 'app.finance.*', 'icon' => 'wallet', 'color' => 'marigold', 'feature' => 'finance', 'can' => 'finance.view'],
                ['label' => 'Ish haqi', 'route' => 'app.payroll.index', 'match' => 'app.payroll.*', 'icon' => 'cash', 'color' => 'marigold', 'feature' => 'payroll', 'can' => 'payroll.view'],
                ['label' => 'Chegirmalar', 'route' => 'app.excuses.index', 'match' => 'app.excuses.*', 'icon' => 'receipt', 'color' => 'marigold', 'feature' => 'excused_discount', 'can' => 'discount.enter'],
            ]],
            ['group' => 'Tahlil', 'items' => [
                ['label' => 'Statistika', 'route' => 'app.stats.index', 'match' => 'app.stats.*', 'icon' => 'chart', 'color' => 'sky', 'feature' => 'analytics', 'can' => 'stats.view'],
                ['label' => 'Hisobotlar', 'route' => 'app.reports.index', 'match' => 'app.reports.*', 'icon' => 'report', 'color' => 'grape', 'feature' => 'reports', 'can' => 'reports.view'],
                ['label' => 'AI yordamchi', 'route' => 'app.ai.index', 'match' => 'app.ai.index', 'icon' => 'sparkles', 'color' => 'sky', 'feature' => 'ai_chat', 'can' => 'ai.chat'],
                ['label' => 'AI hisobot', 'route' => 'app.ai.reports.index', 'match' => 'app.ai.reports.*', 'icon' => 'report', 'color' => 'grape', 'can' => 'ai.report'],
            ]],
            ['group' => 'Aloqa', 'items' => [
                ['label' => 'Telegram', 'route' => 'app.telegram.groups', 'match' => 'app.telegram.*', 'icon' => 'sms', 'color' => 'sky', 'feature' => 'telegram', 'can' => 'tg.contacts'],
                ['label' => 'Telegram sozlamalari', 'route' => 'app.telegram.settings', 'match' => 'app.telegram.settings', 'icon' => 'settings', 'color' => 'sky', 'feature' => 'telegram', 'can' => 'tg.manage'],
                ['label' => 'SMS xabarlar', 'route' => 'app.sms.index', 'match' => 'app.sms.*', 'icon' => 'sms', 'color' => 'teal', 'feature' => 'sms', 'can' => 'sms.manage'],
            ]],
            ['group' => 'Tizim', 'items' => [
                ['label' => 'Import', 'route' => 'app.import.index', 'match' => 'app.import.*', 'icon' => 'inbox', 'color' => 'teal', 'owner' => true],
                ['label' => 'Sozlamalar', 'route' => 'app.settings.index', 'match' => 'app.settings.*', 'icon' => 'settings', 'color' => 'sky', 'can' => 'settings.manage'],
                ['label' => 'Obuna va balans', 'route' => 'app.subscription.index', 'match' => 'app.subscription.*', 'icon' => 'sparkles', 'color' => 'sun', 'can' => 'subscription.manage'],
            ]],
            ['group' => 'Yordam', 'items' => [
                ['label' => "Video yo'riqnoma", 'route' => 'app.videos', 'match' => 'app.videos', 'icon' => 'info', 'color' => 'sun'],
                ['label' => "Yo'riqnoma", 'route' => 'app.guide', 'match' => 'app.guide', 'icon' => 'info', 'color' => 'teal'],
            ]],
        ];

        $out = [];
        foreach ($all as $group) {
            $items = array_values(array_filter($group['items'], function ($i) use ($user) {
                if (! \Illuminate\Support\Facades\Route::has($i['route'])) {
                    return false;
                }
                if (isset($i['feature']) && ! feature($i['feature'])) {
                    return false;
                }
                if (! empty($i['owner']) && ! $user->is_owner) {
                    return false;
                }
                if (isset($i['can']) && ! $user->can($i['can'])) {
                    return false;
                }

                return true;
            }));
            if ($items) {
                $out[] = ['group' => $group['group'], 'items' => $items];
            }
        }

        return $out;
    }

    /** Blok ranglari (--block-bg / --block-fg) */
    public static function colors(string $name): string
    {
        return match ($name) {
            'berry' => '--block-bg:var(--color-berry-100);--block-fg:var(--color-berry-700)',
            'sky' => '--block-bg:var(--color-sky-100);--block-fg:var(--color-sky-700)',
            'leaf' => '--block-bg:var(--color-leaf-100);--block-fg:var(--color-leaf-700)',
            'sun' => '--block-bg:var(--color-sun-100);--block-fg:var(--color-sun-700)',
            'grape' => '--block-bg:var(--color-grape-100);--block-fg:var(--color-grape-700)',
            'marigold' => '--block-bg:var(--color-marigold-100);--block-fg:var(--color-marigold-700)',
            default => '--block-bg:var(--color-teal-100);--block-fg:var(--color-teal-700)',
        };
    }

    /**
     * Sahifa yo'li (non-yo'l): Bosh sahifa › Bo'lim guruhi › Bo'lim › Joriy sahifa.
     * @return array<int, array{label:string, url:?string}>
     */
    public static function breadcrumbs(?User $user, string $title, bool $admin = false): array
    {
        $route = \Illuminate\Support\Facades\Route::currentRouteName() ?? '';
        $home = $admin ? ['label' => 'Boshqaruv paneli', 'url' => \Illuminate\Support\Facades\Route::has('admin.dashboard') ? route('admin.dashboard') : null]
            : ['label' => 'Bosh sahifa', 'url' => route('app.dashboard')];
        $out = [$home];
        if ($admin || ! $user) {
            if ($title !== '' && $title !== __($home['label'])) {
                $out[] = ['label' => $title, 'url' => null];
            }

            return $out;
        }

        $extra = [
            'app.settings.roles.' => ['Lavozimlar', 'app.settings.index'],
            'app.desk.kids' => ["Bolalar to'lovi", null],
            'app.notifications.' => ['Bildirishnomalar', null],
            'app.profile.' => ['Profil', null],
            'app.guide' => ["Yo'riqnoma", null],
        ];
        foreach (self::tenant($user) as $group) {
            foreach ($group['items'] as $item) {
                if (\Illuminate\Support\Facades\Route::is($item['match']) && $item['route'] !== 'app.dashboard') {
                    if ($group['group']) {
                        $out[] = ['label' => $group['group'], 'url' => null];
                    }
                    $out[] = ['label' => $item['label'], 'url' => route($item['route'])];
                    foreach ($extra as $prefix => [$label, $to]) {
                        if (str_starts_with($route, $prefix)) {
                            $out[] = ['label' => $label, 'url' => $to ? route($to) : null];
                        }
                    }
                    $last = end($out);
                    if ($title !== '' && __($last['label']) !== $title) {
                        $out[] = ['label' => $title, 'url' => null];
                    }

                    return $out;
                }
            }
        }
        if ($title !== '' && $route !== 'app.dashboard') {
            $out[] = ['label' => $title, 'url' => null];
        }

        return $out;
    }
}
