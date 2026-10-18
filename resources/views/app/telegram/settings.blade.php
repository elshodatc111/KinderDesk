@extends('layouts.app')
@section('title', 'Telegram bot')
@section('content')
@include('app.telegram._tabs')
<div class="grid xl:grid-cols-[1fr_22rem] gap-6 items-start">
<form method="post" action="{{ route('app.telegram.save') }}" class="card card-pad space-y-5">@csrf @method('put')
    <div>
        <label class="label">{{ __('Bot tokeni') }}</label>
        <input name="token" class="input font-mono" autocomplete="off" placeholder="{{ $bot ? __("Saqlangan (shifrlangan). O'zgartirish uchun yangisini kiriting") : '123456789:AAH...' }}">
        <p class="help mt-1">{{ __("Telegramda @BotFather orqali bot yarating (/newbot) va tokenni shu yerga qo'ying. Token bazada shifrlangan holda saqlanadi.") }}</p>
    </div>
    @if($bot)
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">{{ __('Bot nomi') }}</label><input name="name" class="input" maxlength="64" value="{{ old('name', $bot->name) }}"></div>
        <div><label class="label">{{ __('Qisqa tavsif (profilda)') }}</label><input name="short_description" class="input" maxlength="120" value="{{ old('short_description', $bot->short_description) }}"></div>
    </div>
    <div><label class="label">{{ __("Tavsif (bot ochilganda ko'rinadi)") }}</label><textarea name="description" rows="3" maxlength="512" class="textarea">{{ old('description', $bot->description) }}</textarea>
        <p class="help mt-1">{{ __("Bot rasmi Telegram API orqali o'rnatilmaydi: @BotFather → /setuserpic.") }}</p></div>
    <label class="flex items-start gap-2 text-sm font-medium"><input type="checkbox" class="check mt-0.5" name="protect_content" value="1" @checked(old('protect_content', $bot->protect_content))> <span>{{ __("Yuborilgan xabarni ko'chirish va ulashishni taqiqlash") }}<span class="block text-xs text-ink-mute font-normal">{{ __("Ota-onalar rasm/videoni qayta yubora olmaydi va saqlay olmaydi (skrinshotni to'xtatib bo'lmaydi).") }}</span></span></label>

    <div class="border-t border-line pt-4 space-y-4">
        <h3 class="font-semibold">{{ __('Avtomatik xabarlar') }}</h3>
        @foreach($events as $key => $ev)
        <div class="rounded-xl border border-line p-3 space-y-2">
            <label class="flex items-center gap-2 font-semibold text-sm"><input type="checkbox" class="check" name="events[{{ $key }}]" value="1" @checked($bot->eventEnabled($key))> {{ __($ev['label']) }} <span class="text-xs font-medium text-ink-mute">· {{ __($ev['to']) }}</span></label>
            <textarea name="templates[{{ $key }}]" rows="2" class="textarea !text-sm" maxlength="600">{{ old("templates.$key", $bot->template($key)) }}</textarea>
        </div>
        @endforeach
        <p class="help">{{ __("O'zgaruvchilar") }}: {{ implode(' ', $vars) }}</p>
    </div>
    @endif
    <button class="btn btn-primary">{{ __('Saqlash') }}</button>
</form>

<div class="space-y-4">
    <div class="card card-pad text-sm font-medium space-y-2">
        <h3 class="font-semibold">{{ __('Holat') }}</h3>
        @if($bot)
            <p>&#64;{{ $bot->username }} · @if($bot->status === 'ok')<span class="badge badge-leaf">{{ __('ishlayapti') }}</span>@else<span class="badge badge-red">{{ __('xato') }}</span>@endif</p>
            @if($bot->last_error)<p class="text-danger text-xs">{{ $bot->last_error }}</p>@endif
            <p>{{ __('Ulangan ota-onalar (bolalar)') }}: <b>{{ $connected }}</b></p>
            <div class="flex flex-wrap gap-2 pt-1">
                <form method="post" action="{{ route('app.telegram.check') }}">@csrf<button class="btn btn-sm">{{ __('Tekshirish') }}</button></form>
                <form method="post" action="{{ route('app.telegram.disconnect') }}" onsubmit="return confirm('{{ __("Bot uzilsinmi? Ulangan ota-onalar ro'yxati o'chadi.") }}')">@csrf @method('delete')<button class="btn btn-sm">{{ __('Botni uzish') }}</button></form>
            </div>
        @else
            <p class="text-ink-soft">{{ __('Bot hali ulanmagan.') }}</p>
        @endif
    </div>
    <div class="card card-pad text-sm space-y-2">
        <h3 class="font-semibold">{{ __('Qanday ishlaydi') }}</h3>
        <p>{{ __("Ota-onaga shaxsiy havola yuboriladi (Telegram raqami so'ralmaydi). U «Start» va «Roziman» tugmalarini bosadi — shundan keyingina xabar boradi.") }}</p>
        <p>{{ __("Tarbiyachi botga rasm/video yuboradi, bot guruhni so'raydi va tasdiqlagach ota-onalarga uzatadi. Fayllar serverda saqlanmaydi.") }}</p>
    </div>
</div>
</div>
@endsection
