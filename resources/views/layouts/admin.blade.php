<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>@include('partials.head')@stack('head')</head>
@php
    $user = auth()->user();
    $items = [
        ['Boshqaruv', 'admin.dashboard', 'admin.dashboard', 'home', 'teal'],
        ["Bog'chalar", 'admin.tenants.index', 'admin.tenants.*', 'groups', 'sky'],
        ["To'lov cheklari", 'admin.topups.index', 'admin.topups.*', 'wallet', 'marigold'],
        ['Tariflar', 'admin.plans.index', 'admin.plans.*', 'sparkles', 'sun'],
        ['Sozlamalar', 'admin.settings.index', 'admin.settings.*', 'settings', 'grape'],
        ['AI', 'admin.ai.index', 'admin.ai.*', 'sparkles', 'grape'],
        ['Tizim holati', 'admin.system.index', 'admin.system.*', 'refresh', 'teal'],
        ['Audit jurnali', 'admin.audit.index', 'admin.audit.*', 'report', 'leaf'],
        ['Rozilik jurnali', 'admin.consents.index', 'admin.consents.*', 'report', 'sky'],
        ['Zaxira API', 'admin.backup.index', 'admin.backup.*', 'refresh', 'leaf'],
        ['Telegram', 'admin.telegram.index', 'admin.telegram.*', 'sms', 'teal'],
        ["Video yo'riqnoma", 'admin.videos.index', 'admin.videos.*', 'sparkles', 'marigold'],
        ['Profil', 'admin.profile.index', 'admin.profile.*', 'user', 'berry'],
    ];
@endphp
<body class="bg-cloud" x-data="{ menu: false }" @keydown.escape.window="menu = false">
@include('partials.realtime-config')
<div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
    <div x-show="menu" x-cloak class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="menu = false"></div>
    <aside class="fixed lg:sticky top-0 z-50 h-screen w-64 overflow-y-auto bg-white border-r border-line flex flex-col transition-transform lg:translate-x-0"
           :class="menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="px-4 pt-5 pb-4"><x-logo size="sm" /><p class="mt-2 text-xs text-ink-mute">{{ __("Platforma boshqaruvi") }}</p></div>
        <nav class="px-3 space-y-1 flex-1">
            @foreach ($items as [$label, $route, $match, $icon, $color])
                <a href="{{ route($route) }}" style="{{ \App\Support\Navigation::colors($color) }}"
                   class="nav-item {{ request()->routeIs($match) ? 'is-active' : '' }}">
                    <span class="nav-block"><x-icon :name="$icon" /></span><span>{{ __($label) }}</span>
                </a>
            @endforeach
        </nav>
        <div class="p-3"><button type="button" class="btn btn-sm btn-block" x-data @click="$dispatch('kd:confirm-logout')"><x-icon name="logout" /> {{ __('Chiqish') }}</button></div>
    </aside>
    <div class="min-w-0 flex flex-col">
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-line">
            <div class="flex items-center gap-3 px-4 lg:px-8 min-h-16 py-2">
                <button class="lg:hidden btn btn-sm" @click="menu = true" aria-label="Menyu"><x-icon name="menu" /></button>
                <div class="min-w-0">@include('partials.breadcrumbs', ['admin' => true])<h1 class="page-title truncate">{{ kd_title() }}</h1></div>
                <div class="ml-auto flex items-center gap-3">
                    @yield('actions')
                    @include('partials.lang-switch')
                    <span class="live-dot" :class="{'is-live': $store.live.state==='live', 'is-poll': $store.live.state==='poll'}" title="Jonli aloqa"></span>
                    @include('partials.bell', ['bellUrl' => route('admin.notifications.recent'), 'readUrl' => route('admin.notifications.read')])
                    <a href="{{ route('admin.profile.index') }}" class="avatar" title="{{ $user->name }} — {{ __('Profil') }}" aria-label="{{ __('Profil') }}">{{ $user->initials() }}</a>
                </div>
            </div>
        </header>
        <main class="flex-1 px-4 lg:px-8 py-6 max-w-[88rem] w-full">@yield('content')</main>
    </div>
</div>
@include('partials.flash')
@include('partials.logout-confirm')
@stack('scripts')
</body>
</html>
