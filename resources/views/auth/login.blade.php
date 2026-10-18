@extends('layouts.auth')
@section('title', 'Tizimga kirish')
@section('lead', "Telefon raqamingiz bilan kiring")
@section('content')
<div x-data="{
    mode: 'password', phone: '{{ old('phone') }}', sent: false, wait: 0, msg: '', dev: '', busy: false,
    async send() {
        this.busy = true; this.msg = '';
        const r = await fetch('{{ route('login.otp.send') }}', { method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': window.KD?.csrf || document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({phone: this.phone}) });
        const j = await r.json().catch(() => ({ok:false, message:'Xatolik. Qayta urinib ko\'ring'}));
        this.msg = j.message; this.dev = j.dev_code || ''; this.sent = !!j.ok;
        if (j.ok) { this.wait = 60; const t = setInterval(() => { if (--this.wait <= 0) clearInterval(t); }, 1000); }
        this.busy = false;
    }
}">
    <div class="tabs mb-5">
        <button type="button" class="tab" :class="mode==='password' && 'is-active'" @click="mode='password'">Parol bilan</button>
        <button type="button" class="tab" :class="mode==='otp' && 'is-active'" @click="mode='otp'">SMS kod bilan</button>
    </div>

    <form method="post" action="{{ route('login.attempt') }}" x-show="mode==='password'" class="space-y-4">@csrf
        <div><label class="label" for="p1">Telefon raqam</label>
            <input id="p1" name="phone" x-model="phone" inputmode="tel" autocomplete="username" placeholder="90 123 45 67" class="input" required autofocus>
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="pw">Parol</label>
            <input id="pw" type="password" name="password" autocomplete="current-password" class="input" required></div>
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="remember" value="1" class="check"> Eslab qol</label>
            <a href="{{ route('password.forgot') }}" class="text-sm font-semibold text-teal-700 hover:underline">Parolni unutdim</a>
        </div>
        <button class="btn btn-primary btn-lg btn-block">Kirish</button>
    </form>

    <form method="post" action="{{ route('login.otp') }}" x-show="mode==='otp'" x-cloak class="space-y-4">@csrf
        <div><label class="label" for="p2">Telefon raqam</label>
            <input id="p2" name="phone" x-model="phone" inputmode="tel" placeholder="90 123 45 67" class="input" required></div>
        <button type="button" class="btn btn-block" @click="send()" :disabled="busy || wait>0">
            <span x-text="wait>0 ? 'Qayta yuborish: '+wait+' s' : 'Kod yuborish'"></span></button>
        <p class="help" x-show="msg" x-text="msg"></p>
        <p class="banner banner-info text-sm" x-show="dev" x-cloak>Sinov rejimi. Kod: <b x-text="dev"></b></p>
        <div x-show="sent" x-cloak><label class="label" for="c1">SMS kod</label>
            <input id="c1" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" class="input text-center tracking-[.5em] text-xl font-semibold">
            @error('code')<p class="field-error">{{ $message }}</p>@enderror</div>
        <button class="btn btn-primary btn-lg btn-block" x-show="sent" x-cloak>Kirish</button>
    </form>
</div>
@endsection
@section('below')
<p class="text-center mt-5 font-medium text-ink-soft">Hisobingiz yo'qmi? <a href="{{ route('register') }}" class="text-teal-700 font-semibold hover:underline">{{ config('kinderdesk.trial_days') }} kun bepul boshlang</a></p>
@endsection
