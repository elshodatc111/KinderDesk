<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>@include('partials.head')@stack('head')</head>
<body class="bg-cloud min-h-screen">
<div class="fixed top-4 right-4 z-10">@include('partials.lang-switch')</div>
<div class="min-h-screen grid place-items-center px-4 py-10">
    <div class="w-full {{ $wide ?? false ? 'max-w-2xl' : 'max-w-md' }}">
        <a href="{{ route('home') }}" class="flex justify-center mb-6" aria-label="KinderDesk"><x-logo /></a>
        <div class="card p-6 sm:p-8 !shadow-[0_8px_28px_rgb(16_40_36/.08)]">
            <h1 class="text-2xl font-semibold mb-1">{{ kd_title() }}</h1>
            @hasSection('lead')<p class="text-ink-soft font-semibold mb-5">{{ __(html_entity_decode(trim(View::yieldContent('lead')), ENT_QUOTES)) }}</p>@endif
            @yield('content')
        </div>
        @yield('below')
    </div>
</div>
@include('partials.flash')
@stack('scripts')
</body>
</html>
