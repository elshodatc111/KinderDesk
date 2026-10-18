@extends('layouts.auth')
@section('title', 'Telegram orqali himoya')
@section('lead', "Kirishda tasdiqlash kodi Telegramga keladi")
@section('content')
@if($link)
<div x-data="{ linked: false, user: '', async poll() { try { const r = await fetch('{{ route('twofa.status') }}', {headers: {'Accept': 'application/json'}}); const j = await r.json(); this.linked = j.linked; this.user = j.username || ''; } catch (e) {} } }"
     x-init="setInterval(() => !linked && poll(), 2500)">
    @if($forced)<p class="banner banner-info text-sm mb-4">{{ __("Xavfsizlik uchun direktor va super admin hisobi Telegramga ulanishi shart. Bu bir marta qilinadi.") }}</p>@endif
    <ol class="space-y-3 text-sm font-medium mb-5 list-decimal pl-5">
        <li>{{ __("Quyidagi tugmani bosing va Telegramda «Start» tugmasini bosing.") }}</li>
        <li>{{ __("Ulanganini bu sahifa o'zi aniqlaydi.") }}</li>
    </ol>
    <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg btn-block" x-show="!linked">{{ __('Telegramda ochish') }} (&#64;{{ $bot->username }})</a>
    <p class="help mt-2 text-center" x-show="!linked">{{ __("Havola 15 daqiqa amal qiladi va faqat sizga tegishli.") }}</p>
    <form method="post" action="{{ route('twofa.confirm') }}" x-show="linked" x-cloak class="space-y-3">@csrf
        <p class="banner banner-info text-sm">✅ {{ __('Telegram ulandi') }} <b x-text="user ? '@'+user : ''"></b></p>
        <button class="btn btn-primary btn-lg btn-block">{{ __('Davom etish') }}</button>
    </form>
</div>
@else
<div class="space-y-4">
    <p class="text-sm font-medium">✅ {{ __('Telegram ulangan') }}@if($user->tg_username) — &#64;{{ $user->tg_username }}@endif</p>
    <p class="text-sm text-ink-soft">{{ __('Zaxira kodlar qoldi') }}: <b>{{ $left }}</b> · {{ __('Eslab qolingan qurilmalar') }}: <b>{{ $devices }}</b></p>
    <form method="post" action="{{ route('twofa.confirm') }}">@csrf<input type="hidden" name="new_codes" value="1"><button class="btn btn-block">{{ __('Yangi zaxira kodlar yaratish') }}</button></form>
    <form method="post" action="{{ route('twofa.devices') }}">@csrf<button class="btn btn-block">{{ __("Eslab qolingan qurilmalarni o'chirish") }}</button></form>
    <a class="btn btn-block" href="{{ route('twofa.setup', ['change' => 1]) }}">{{ __("Boshqa Telegram akkauntni ulash") }}</a>
    <a class="block text-center text-sm font-semibold text-teal-700 hover:underline" href="{{ $user->is_super_admin ? route('admin.dashboard') : route('app.dashboard') }}">{{ __('Orqaga') }}</a>
</div>
@endif
@endsection
