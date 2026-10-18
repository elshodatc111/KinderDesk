<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>@include('partials.head')@stack('head')</head>
<body class="bg-cloud" x-data="{ menu: false }">
<header class="sticky top-0 z-40 bg-cloud/90 backdrop-blur border-b border-line">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-3 sm:gap-4">
        <a href="{{ route('home') }}" aria-label="KinderDesk"><x-logo size="sm" /></a>
        <nav class="hidden md:flex items-center gap-6 ml-8 font-semibold text-sm">
            <a href="{{ route('home') }}#imkoniyatlar" class="hover:text-teal-700">Imkoniyatlar</a>
            <a href="{{ route('home') }}#nashrlar" class="hover:text-teal-700">Nashrlar</a>
            <a href="{{ route('home') }}#narxlar" class="hover:text-teal-700">Narxlar</a>
            <a href="{{ route('home') }}#savollar" class="hover:text-teal-700">Savollar</a>
        </nav>
        <div class="ml-auto flex items-center gap-2">
            @include('partials.lang-switch')
            @auth
                <a href="{{ auth()->user()->is_super_admin ? route('admin.dashboard') : route('app.dashboard') }}" class="btn btn-sm btn-primary">Kabinetga o'tish</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-sm hidden sm:inline-flex">Kirish</a>
                <a href="{{ route('register') }}" class="btn btn-sm btn-accent">14 kun bepul</a>
            @endauth
            <button type="button" class="md:hidden btn btn-sm" @click="menu = !menu" :aria-expanded="menu" aria-label="Menyu"><x-icon name="menu" /></button>
        </div>
    </div>
    <nav x-show="menu" x-cloak x-transition.opacity class="md:hidden border-t border-line bg-cloud px-4 py-3 grid gap-1 font-semibold text-sm" @click="menu = false">
        <a href="{{ route('home') }}#imkoniyatlar" class="py-2">{{ __('Imkoniyatlar') }}</a>
        <a href="{{ route('home') }}#nashrlar" class="py-2">{{ __('Nashrlar') }}</a>
        <a href="{{ route('home') }}#savollar" class="py-2">{{ __('Savollar') }}</a>
        @guest<a href="{{ route('login') }}" class="py-2">{{ __('Kirish') }}</a>@endguest
    </nav>
</header>
<main>@yield('content')</main>
@php
    $supPhone = \App\Models\PlatformSetting::get('support.phone');
    $supTg = trim((string) \App\Models\PlatformSetting::get('support.telegram'));
    $tgUrl = $supTg === '' ? null : (str_starts_with($supTg, 'http') ? $supTg : 'https://t.me/'.ltrim($supTg, '@'));
@endphp
<footer class="mt-16 bg-ink text-white/80">
    <div class="max-w-6xl mx-auto px-4 py-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr]">
        <div><x-logo size="sm" class="[&_*]:!text-white" /><p class="mt-3 text-sm font-medium text-white/60 max-w-xs">Xususiy bog'chalar uchun yagona boshqaruv platformasi.</p></div>
        <div class="text-sm font-medium space-y-2">
            <p class="text-white font-semibold mb-1">{{ __('Hujjatlar') }}</p>
            <a class="block hover:text-white" href="{{ route('legal', 'oferta') }}">Ommaviy oferta</a>
            <a class="block hover:text-white" href="{{ route('legal', 'privacy') }}">Maxfiylik siyosati</a>
            <a class="block hover:text-white" href="{{ route('legal', 'terms') }}">Foydalanish shartlari</a>
        </div>
        <div class="text-sm font-medium space-y-2">
            <p class="text-white font-semibold mb-1">{{ __('Aloqa') }}</p>
            @if($supPhone)<a class="flex items-center gap-2 hover:text-white" href="tel:+{{ preg_replace('/\D/', '', $supPhone) }}"><x-icon name="phone" class="size-4" />{{ $supPhone }}</a>@endif
            @if($tgUrl)<a class="flex items-center gap-2 hover:text-white" href="{{ $tgUrl }}" target="_blank" rel="noopener"><x-icon name="sms" class="size-4" />Telegram: {{ $supTg }}</a>@endif
            @if(\App\Support\Site\SupportInfo::configured())<p class="flex items-center gap-2 pt-1"><span class="size-2 rounded-full {{ \App\Support\Site\SupportInfo::isOpen() ? 'bg-leaf-500' : 'bg-ink-mute' }}"></span>{{ \App\Support\Site\SupportInfo::isOpen() ? __('Hozir ishlayapmiz') : __('Hozir dam olish vaqti') }}</p><p>{{ \App\Support\Site\SupportInfo::scheduleLabel() }}@if(\App\Support\Site\SupportInfo::response()) · {{ __('Javob') }}: {{ \App\Support\Site\SupportInfo::response() }}@endif</p>@endif
            @if(! $supPhone && ! $tgUrl && ! \App\Support\Site\SupportInfo::configured())<p class="text-white/40">—</p>@endif
        </div>
    </div>
    <p class="text-center text-xs font-medium text-white/40 pb-6">© {{ date('Y') }} KinderDesk</p>
</footer>
@include('partials.flash')
@stack('scripts')
</body>
</html>
