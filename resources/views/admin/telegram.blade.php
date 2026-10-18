@extends('layouts.admin')
@section('title', 'Telegram')
@section('content')
<div class="grid xl:grid-cols-[1fr_24rem] gap-6 items-start">
<form method="post" action="{{ route('admin.telegram.update') }}" class="card card-pad space-y-5">@csrf @method('put')
    <h2 class="section-title">{{ __('Platforma boti (kirishda tasdiqlash kodi)') }}</h2>
    <p class="help">{{ __("Bu bot faqat direktor va super admin kirishidagi kodni yuboradi. Bog'chalarning o'z botlari alohida — ularni direktorlar o'zi ulaydi.") }}</p>
    <div><label class="label">{{ __('Bot tokeni') }}</label>
        <input name="token" class="input font-mono" autocomplete="off" placeholder="{{ $bot ? __('Saqlangan: bo\'sh qoldirsangiz o\'zgarmaydi') : '123456:ABC...' }}">
        @if($bot)<p class="help mt-1">✅ &#64;{{ $bot->username }} · {{ $bot->status === 'ok' ? __('ishlayapti') : __('xato') }}@if($bot->last_error) · {{ $bot->last_error }}@endif</p>@endif</div>
    <div><span class="label">{{ __('Yangilanishlarni olish rejimi') }}</span>
        <label class="flex items-start gap-2 text-sm font-medium mb-2"><input type="radio" class="check mt-0.5" name="mode" value="polling" @checked($mode==='polling')> <span><b>getUpdates (polling)</b> — {{ __("XAMPP / lokal kompyuter uchun. Worker ishlab turishi kerak (pastdagi ko'rsatma).") }}</span></label>
        <label class="flex items-start gap-2 text-sm font-medium"><input type="radio" class="check mt-0.5" name="mode" value="webhook" @checked($mode==='webhook')> <span><b>Webhook</b> — {{ __("ochiq HTTPS manzilli Linux server uchun. Telegram o'zi yuboradi.") }}</span></label>
        <p class="help mt-2">Webhook: <code>{{ $webhookUrl }}</code> ({{ __('APP_URL ochiq HTTPS bo\'lishi shart') }})</p></div>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="twofa" value="0"><input type="checkbox" class="check" name="twofa" value="1" @checked($twofa)> {{ __("Direktor va super admin uchun Telegram-kod majburiy (platforma boti sozlangandan keyin)") }}</label>
    <div class="flex flex-wrap gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button></div>
</form>
<div class="space-y-4">
    <div class="card card-pad space-y-2 text-sm font-medium">
        <h3 class="font-semibold">{{ __('Holat') }}</h3>
        <p>Worker: @if($workerAlive)<span class="badge badge-leaf">{{ __('ishlayapti') }}</span> ({{ $heartbeatAge }} {{ __('s oldin') }})@else<span class="badge badge-red">{{ __("to'xtagan") }}</span>@if($heartbeatAge !== null) ({{ intdiv($heartbeatAge, 60) }} {{ __('daqiqa oldin') }})@endif @endif</p>
        <p>{{ __("Bog'cha botlari") }}: <b>{{ $tenantBots }}</b>@if($tenantBotsErr) · <span class="text-danger">{{ $tenantBotsErr }} {{ __('xatoli') }}</span>@endif</p>
        <p>{{ __('Navbatda') }}: <b>{{ $pending }}</b> · {{ __('Oxirgi 24 soatda xato') }}: <b>{{ $failed }}</b></p>
        <form method="post" action="{{ route('admin.telegram.apply') }}">@csrf<button class="btn btn-sm">{{ __("Rejimni barcha botlarga qo'llash") }}</button></form>
        @if($bot)<form method="post" action="{{ route('admin.telegram.forget') }}" onsubmit="return confirm('{{ __("Platforma boti olib tashlansinmi? 2 bosqichli tasdiq o'chadi.") }}')">@csrf @method('delete')<button class="btn btn-sm">{{ __("Platforma botini olib tashlash") }}</button></form>@endif
    </div>
    <div class="card card-pad space-y-2 text-sm">
        <h3 class="font-semibold">{{ __('Workerni ishga tushirish') }}</h3>
        <p><b>Windows (XAMPP):</b> <code>telegram-worker.bat</code> {{ __('faylini ikki marta bosing va oynani yopmang.') }}</p>
        <p><b>Linux:</b> <code>php artisan tg:run</code> ({{ __('supervisor yoki systemd orqali doim yoqiq tursin') }}). {{ __('Webhook rejimida ham navbatni yuborish uchun kerak.') }}</p>
        <p class="help">{{ __("Zaxira: Windows Task Scheduler / cron har daqiqada «php artisan schedule:run» ni chaqirsa, worker o'chiq bo'lsa ham xabarlar ketadi.") }}</p>
    </div>
</div>
</div>
@endsection
