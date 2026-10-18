<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('partials.head')
    @stack('head')
</head>
@php
    $user = auth()->user();
    $state = $tenant->accessState();
    $daysLeft = $tenant->daysLeft();
    $nav = \App\Support\Navigation::tenant($user);
    $stateLabel = match ($state) {
        'trial' => ['Sinov muddati', 'badge-orange'],
        'active' => ['Obuna faol', 'badge-leaf'],
        'lifetime' => ['Umrbod', 'badge-leaf'],
        'suspended' => ['Bloklangan', 'badge-red'],
        default => ["Faqat ko'rish", 'badge-red'],
    };
@endphp
<body class="bg-cloud" x-data="{ menu: false }" @keydown.escape.window="menu = false">
@include('partials.realtime-config')

<div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
    {{-- ============ Yon menyu ============ --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-40 bg-ink/40 lg:hidden" @click="menu = false"></div>
    <aside class="fixed lg:sticky top-0 z-50 h-screen w-[17rem] shrink-0 overflow-y-auto bg-white border-r border-line flex flex-col transition-transform duration-200 lg:translate-x-0 "
           :class="menu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
        <div class="px-4 pt-5 pb-3 flex items-center justify-between">
            <a href="{{ route('app.dashboard') }}" aria-label="KinderDesk"><x-logo size="sm" /></a>
            <button class="lg:hidden btn-quiet" @click="menu = false" aria-label="Menyuni yopish"><x-icon name="x" /></button>
        </div>

        <div class="mx-3 mb-2 px-3 py-2.5 rounded-xl bg-cloud border border-line flex items-center gap-3">
            @if ($tenant->logo_path)
                <img src="{{ asset('storage/'.$tenant->logo_path) }}" alt="" class="size-10 rounded-lg object-cover">
            @else
                <span class="grid place-items-center size-10 rounded-lg bg-teal-500 text-white text-sm font-semibold">{{ $tenant->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="font-semibold leading-tight truncate">{{ $tenant->name }}</p>
                <p class="text-xs text-ink-mute">{{ $tenant->edition->name }} nashri</p>
            </div>
        </div>

        <script>
            window.kdNavGet = function (k, active) {
                if (active) return true;
                try { var v = JSON.parse(localStorage.getItem('kd.nav') || '{}'); return v[k] === true; } catch (e) { return false; }
            };
            window.kdNavSet = function (k, open) {
                try { var v = JSON.parse(localStorage.getItem('kd.nav') || '{}'); v[k] = open; localStorage.setItem('kd.nav', JSON.stringify(v)); } catch (e) {}
            };
        </script>
        <nav class="px-3 pb-4 flex-1" aria-label="Asosiy menyu">
            @foreach ($nav as $group)
                @php $isActive = collect($group['items'])->contains(fn ($i) => request()->routeIs($i['match'])); @endphp
                @if ($group['group'])
                <div x-data="{ open: kdNavGet(@js($group['group']), @js($isActive)) }" class="nav-section">
                    <button type="button" class="nav-group nav-group-btn" @click="open = !open; kdNavSet(@js($group['group']), open)" :aria-expanded="open">
                        <span>{{ __($group['group']) }}</span>
                        <span class="nav-count">{{ count($group['items']) }}</span>
                        <x-icon name="chevron-down" class="size-4 chev" ::class="open ? 'rotate-180' : ''" />
                    </button>
                    <div class="space-y-1" x-show="open" x-cloak>
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route']) }}" style="{{ \App\Support\Navigation::colors($item['color']) }}"
                               class="nav-item {{ request()->routeIs($item['match']) ? 'is-active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                                <span class="nav-block"><x-icon :name="$item['icon']" /></span>
                                <span class="truncate">{{ __($item['label']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="space-y-1">
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" style="{{ \App\Support\Navigation::colors($item['color']) }}"
                           class="nav-item {{ request()->routeIs($item['match']) ? 'is-active' : '' }}" @if(request()->routeIs($item['match'])) aria-current="page" @endif>
                            <span class="nav-block"><x-icon :name="$item['icon']" /></span>
                            <span class="truncate">{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                </div>
                @endif
            @endforeach
        </nav>

        {{-- Obuna holati kartasi --}}
        <div class="m-3 p-3.5 rounded-xl border border-line bg-cloud">
            <div class="flex items-center justify-between gap-2">
                <span class="badge {{ $stateLabel[1] }}"><span class="dot"></span>{{ $stateLabel[0] }}</span>
                @if (in_array($state, ['trial', 'active']))
                    <span class="text-sm font-semibold">{{ max(0, $daysLeft) }} {{ __("kun") }}</span>
                @endif
            </div>
            @if (in_array($state, ['trial', 'active']))
                @php $total = $state === 'trial' ? config('kinderdesk.trial_days') : 30; @endphp
                <div class="progress mt-2.5"><span style="width: {{ min(100, max(4, $daysLeft / max(1, $total) * 100)) }}%; background: {{ $daysLeft <= 3 ? 'var(--color-marigold-500)' : 'var(--color-teal-500)' }}"></span></div>
            @endif
            <p class="text-xs text-ink-soft mt-2">{{ __("Balans") }}: {{ money($tenant->balance) }} so'm</p>
            @can('subscription.manage')
              @if ($state !== 'lifetime')
                <a href="{{ route('app.subscription.index') }}" class="btn btn-sm btn-accent btn-block mt-2.5">
                    {{ $state === 'active' ? "Obunani boshqarish" : 'Obunani faollashtirish' }}
                </a>
              @endif
            @endcan
        </div>
        @php $supPhone = \App\Models\PlatformSetting::get('support.phone'); $supTg = trim((string) \App\Models\PlatformSetting::get('support.telegram')); @endphp
        @if ($supPhone || $supTg || \App\Support\Site\SupportInfo::configured())
        <div class="mx-3 mb-3 px-3 py-2.5 rounded-xl border border-line text-xs font-medium text-ink-soft space-y-1">
            <p class="font-semibold text-ink">{{ __("Qo'llab-quvvatlash") }}</p>
            @if($supPhone)<a class="flex items-center gap-1.5 hover:text-teal-700" href="tel:+{{ preg_replace('/\D/', '', $supPhone) }}"><x-icon name="phone" class="size-3.5" />{{ $supPhone }}</a>@endif
            @if($supTg)<a class="flex items-center gap-1.5 hover:text-teal-700" target="_blank" rel="noopener" href="{{ str_starts_with($supTg, 'http') ? $supTg : 'https://t.me/'.ltrim($supTg, '@') }}"><x-icon name="sms" class="size-3.5" />{{ $supTg }}</a>@endif
            @if(\App\Support\Site\SupportInfo::configured())<p class="flex items-center gap-1.5 pt-1"><span class="size-2 rounded-full {{ \App\Support\Site\SupportInfo::isOpen() ? 'bg-leaf-500' : 'bg-ink-mute' }}"></span>{{ \App\Support\Site\SupportInfo::isOpen() ? __('Hozir ishlayapmiz') : __('Hozir dam olish vaqti') }}</p><p>{{ \App\Support\Site\SupportInfo::scheduleLabel() }}@if(\App\Support\Site\SupportInfo::response()) · {{ __('Javob') }}: {{ \App\Support\Site\SupportInfo::response() }}@endif</p>@endif
        </div>
        @endif
    </aside>

    {{-- ============ Asosiy qism ============ --}}
    <div class="min-w-0 flex flex-col">
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-line">
            <div class="flex items-center gap-3 px-4 lg:px-8 min-h-16 py-2">
                <button class="lg:hidden btn btn-sm" @click="menu = true" aria-label="Menyuni ochish"><x-icon name="menu" /></button>
                <div class="min-w-0 flex-1">
                    <div class="hidden sm:block">@include('partials.breadcrumbs')</div>
                    <h1 class="page-title truncate">{{ kd_title() }}</h1>
                </div>
                <div class="ml-auto shrink-0 flex items-center gap-2 sm:gap-3">
                    @yield('actions')

                    @include('partials.lang-switch')

                    <span class="hidden sm:inline-flex items-center gap-2 text-xs font-medium text-ink-soft px-2.5 py-1.5 rounded-full bg-cloud border border-line" title="Jonli aloqa holati">
                        <span class="live-dot" :class="{'is-live': $store.live.state==='live', 'is-poll': $store.live.state==='poll'}"></span>
                        <span x-text="$store.live.state==='live' ? 'Jonli' : ($store.live.state==='poll' ? 'Yangilanib turadi' : 'Ulanmoqda')">Ulanmoqda</span>
                    </span>

                    @include('partials.bell', ['bellUrl' => route('app.notifications.recent'), 'readUrl' => route('app.notifications.read'), 'allUrl' => route('app.notifications.index')])

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-cloud" @click="open = !open" aria-haspopup="true" :aria-expanded="open">
                            <span class="avatar">{{ $user->initials() }}</span>
                            <span class="hidden md:block text-left leading-tight">
                                <span class="block text-sm font-semibold">{{ $user->name }}</span>
                                <span class="block text-xs text-ink-mute">{{ $user->roleName() }}</span>
                            </span>
                            <x-icon name="chevron-down" class="size-4 text-ink-mute" />
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 mt-2 w-60 card-sticker p-2 z-50">
                            <a href="{{ route('app.profile.index') }}" class="nav-item"><span class="nav-block"><x-icon name="user" /></span>Profil</a>
                            <button type="button" class="nav-item w-full text-left" @click="open = false; $dispatch('kd:confirm-logout')"><span class="nav-block"><x-icon name="logout" /></span>{{ __('Chiqish') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 lg:px-8 py-6 w-full min-w-0">
            @if (session('impersonator_id'))
                <div class="banner banner-info mb-5"><x-icon name="user" /><p class="flex-1">Siz super admin sifatida «{{ $tenant->name }}» kabinetidasiz.</p>
                    <form method="post" action="{{ route('impersonate.stop') }}">@csrf<button class="btn btn-sm">Adminga qaytish</button></form></div>
            @endif
            @if ($tenant->isManualReadOnly())
                <div class="banner banner-danger mb-5">
                    <x-icon name="lock" />
                    <div class="flex-1">
                        <p>{{ __("Tizim vaqtincha faqat ko'rish rejimida.") }}</p>
                        <p class="text-sm font-semibold text-ink-soft">{{ __('Sabab') }}: {{ $tenant->manual_readonly_reason }}@if($tenant->manual_readonly_until) · {{ $tenant->manual_readonly_until->format('d.m.Y') }} {{ __('gacha') }}@endif</p>
                        <p class="text-sm text-ink-soft">{{ __("Ma'lumotlaringiz saqlangan. Qo'llab-quvvatlash xizmatiga murojaat qiling.") }}</p>
                    </div>
                </div>
            @elseif ($state === 'read_only')
                <div class="banner banner-danger mb-5">
                    <x-icon name="lock" />
                    <div class="flex-1">
                        <p>Tizim faqat ko'rish rejimida. Obuna muddati tugagan.</p>
                        <p class="text-sm font-semibold text-ink-soft">Ma'lumotlaringiz saqlangan. Balansni to'ldirib obunani faollashtirsangiz, ishni davom ettirasiz.</p>
                    </div>
                    @can('subscription.manage')<a href="{{ route('app.subscription.index') }}" class="btn btn-sm btn-accent">Obunani faollashtirish</a>@endcan
                </div>
            @elseif ($state === 'suspended')
                <div class="banner banner-danger mb-5"><x-icon name="ban" /><p>Hisobingiz vaqtincha bloklangan. Qo'llab-quvvatlash xizmatiga murojaat qiling.</p></div>
            @elseif ($state === 'trial' && $daysLeft <= 5)
                <div class="banner banner-warn mb-5">
                    <x-icon name="clock" />
                    <p class="flex-1">Sinov muddati tugashiga {{ max(0, $daysLeft) }} kun qoldi. Keyin tizim faqat ko'rish rejimiga o'tadi.</p>
                    @can('subscription.manage')<a href="{{ route('app.subscription.index') }}" class="btn btn-sm btn-accent">Tarifni tanlash</a>@endcan
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@include('partials.flash')
@include('partials.logout-confirm')
@stack('scripts')
</body>
</html>
