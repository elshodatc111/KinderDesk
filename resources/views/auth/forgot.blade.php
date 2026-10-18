@extends('layouts.auth')
@section('title', 'Parolni tiklash')
@section('lead', "Telefon raqamingizga SMS kod yuboramiz")
@section('content')
<form method="post" action="{{ route('password.reset') }}" class="space-y-4" x-data="{
    phone: '{{ old('phone') }}', sent: false, wait: 0, msg: '', dev: '', busy: false,
    async send() {
        this.busy = true;
        const r = await fetch('{{ route('password.otp') }}', { method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({phone: this.phone}) });
        const j = await r.json().catch(() => ({ok:false, message:'Xatolik'}));
        this.msg = j.message; this.dev = j.dev_code || ''; this.sent = !!j.ok;
        if (j.ok) { this.wait = 60; const t = setInterval(() => { if (--this.wait <= 0) clearInterval(t); }, 1000); }
        this.busy = false;
    }}">@csrf
    <div><label class="label">Telefon raqam</label><input name="phone" x-model="phone" class="input" inputmode="tel" placeholder="90 123 45 67" required>
        @error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
    <button type="button" class="btn btn-block" @click="send()" :disabled="busy || wait>0"><span x-text="wait>0 ? 'Qayta yuborish: '+wait+' s' : 'Kod yuborish'"></span></button>
    <p class="help" x-show="msg" x-text="msg"></p>
    <p class="banner banner-info text-sm" x-show="dev" x-cloak>Sinov rejimi. Kod: <b x-text="dev"></b></p>
    <div x-show="sent" x-cloak class="space-y-4">
        <div><label class="label">SMS kod</label><input name="code" inputmode="numeric" maxlength="6" class="input text-center tracking-[.5em] text-xl font-semibold">
            @error('code')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Yangi parol</label><input type="password" name="password" class="input" autocomplete="new-password">
            <p class="help">Kamida 8 belgi: harf va raqam</p>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Parolni takrorlang</label><input type="password" name="password_confirmation" class="input" autocomplete="new-password"></div>
        <button class="btn btn-primary btn-lg btn-block">Parolni yangilash</button>
    </div>
</form>
@endsection
@section('below')<p class="text-center mt-5"><a href="{{ route('login') }}" class="font-semibold text-teal-700 hover:underline">Kirish sahifasiga qaytish</a></p>@endsection
