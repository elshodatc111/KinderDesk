@extends('layouts.auth')
@section('title', 'Tasdiqlash kodi')
@section('lead', "Telegramdagi 6 xonali kodni kiriting")
@section('content')
@if($error)<p class="banner banner-danger text-sm mb-4">{{ __($error) }}</p>@endif
<form method="post" action="{{ route('twofa.verify') }}" class="space-y-4" x-data="{ rec: false }">@csrf
    <div><label class="label" for="code" x-text="rec ? '{{ __('Zaxira kod') }}' : '{{ __('Kod') }}'"></label>
        <input id="code" name="code" class="input text-center text-2xl tracking-widest" :inputmode="rec ? 'text' : 'numeric'" autocomplete="one-time-code" maxlength="9" required autofocus>
        @error('code')<p class="field-error">{{ __($message) }}</p>@enderror</div>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="remember_device" value="1" class="check"> {{ __("Bu qurilmani 30 kun eslab qol") }}</label>
    <button class="btn btn-primary btn-lg btn-block">{{ __('Tasdiqlash') }}</button>
    <div class="flex items-center justify-between text-sm font-semibold text-teal-700">
        <button type="submit" formaction="{{ route('twofa.resend') }}" formnovalidate class="hover:underline">{{ __('Kodni qayta yuborish') }}</button>
        @if($left > 0)<button type="button" class="hover:underline" @click="rec = !rec; $nextTick(() => $refs.c?.focus())" x-text="rec ? '{{ __('Telegram kodi') }}' : '{{ __('Zaxira kod') }}'"></button>@endif
    </div>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm text-ink-mute hover:underline">{{ __('Chiqish') }}</button></form>
@endsection
