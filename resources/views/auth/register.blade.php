@extends('layouts.auth', ['wide' => true])
@section('title', "Bog'chani ro'yxatdan o'tkazing")
@section('lead', config('kinderdesk.trial_days')." kun bepul. Karta kerak emas.")
@section('content')
<form method="post" action="{{ route('register.store') }}" class="space-y-6" @tg-otp-sent="sent = true; ok = true; msg = ''; wait = 0"
      x-data="{
        phone: @js(old('phone', '')), email: @js(old('email', '')), tax: @js(old('tax_id', '')),
        sent: {{ $errors->has('code') ? 'true' : 'false' }}, wait: 0, msg: '', ok: true, dev: '', busy: false,
        async send() {
            this.busy = true; this.msg = '';
            const r = await fetch('{{ route('register.otp') }}', { method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({phone: this.phone, email: this.email, tax_id: this.tax}) });
            const j = await r.json().catch(() => ({ok:false, message:'Xatolik. Qayta urinib ko\'ring'}));
            this.msg = j.message || (j.errors ? Object.values(j.errors)[0][0] : ''); this.ok = !!j.ok; this.dev = j.dev_code || ''; this.sent = this.sent || !!j.ok;
            if (j.ok) { this.wait = 60; const t = setInterval(() => { if (--this.wait <= 0) clearInterval(t); }, 1000); }
            this.busy = false;
        }
      }">@csrf

    <fieldset>
        <legend class="section-title mb-3">Nashrni tanlang</legend>
        <div class="grid sm:grid-cols-3 gap-3">
            @foreach ($editions as $ed)
                <label class="cursor-pointer">
                    <input type="radio" name="edition" value="{{ $ed->key }}" class="peer sr-only" @checked(old('edition', $selected) === $ed->key)>
                    <span class="block h-full rounded-2xl border border-line bg-white p-4 transition peer-checked:border-teal-500 peer-checked:bg-teal-50 peer-checked:ring-2 peer-checked:ring-teal-100 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-teal-500">
                        <span class="block font-semibold text-lg">{{ $ed->name }}</span>
                        <span class="block text-sm font-semibold text-ink-soft">{{ $ed->tagline }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('edition')<p class="field-error">{{ $message }}</p>@enderror
    </fieldset>

    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">Bog'cha nomi</label>
            <input name="name" value="{{ old('name') }}" class="input" required placeholder="Masalan: Quyoshli bog'cha">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Direktor ismi</label>
            <input name="owner_name" value="{{ old('owner_name') }}" class="input" required autocomplete="name">@error('owner_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Telefon raqam</label>
            <input name="phone" x-model="phone" class="input" inputmode="tel" placeholder="90 123 45 67" required autocomplete="tel">@error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Email <span class="font-semibold text-ink-mute">(ixtiyoriy)</span></label>
            <input type="email" name="email" x-model="email" class="input" autocomplete="email">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">STIR <span class="font-semibold text-ink-mute">(ixtiyoriy)</span></label>
            <input name="tax_id" x-model="tax" class="input" inputmode="numeric" maxlength="14"></div>
        <div><label class="label">Viloyat</label><input name="region" value="{{ old('region') }}" class="input"></div>
        <div><label class="label">Shahar / tuman</label><input name="city" value="{{ old('city') }}" class="input"></div>
        <div><label class="label">Parol</label>
            <input type="password" name="password" class="input" autocomplete="new-password" required>
            <p class="help">Kamida 8 belgi: harf va raqam</p>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Parolni takrorlang</label>
            <input type="password" name="password_confirmation" class="input" autocomplete="new-password" required></div>
    </div>

    <div class="card p-4 bg-teal-50 space-y-3">
        <p class="font-semibold">Telefon raqamni tasdiqlang</p>
        <div class="flex flex-wrap gap-3 items-start">
            <button type="button" class="btn" @click="send()" :disabled="busy || wait>0"><span x-text="wait>0 ? 'Qayta yuborish: '+wait+' s' : (sent ? 'Kodni qayta yuborish' : 'SMS kod yuborish')"></span></button>
            <input name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" class="input !w-40 text-center tracking-[.4em] text-lg font-semibold" :disabled="!sent">
        </div>
        <p class="text-sm font-medium" :class="ok ? 'text-ink-soft' : 'text-danger'" x-show="msg" x-text="msg"></p>
        <p class="banner banner-info text-sm" x-show="dev" x-cloak>Sinov rejimi. Kod: <b x-text="dev"></b></p>
        @include('auth.partials.tg-otp', ['purpose' => 'register'])
        @error('code')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-start gap-3 font-semibold">
        <input type="checkbox" name="agree" value="1" class="check mt-1" @checked(old('agree'))>
        <span><a target="_blank" href="{{ route('legal', 'oferta') }}" class="underline font-semibold">Ommaviy oferta</a> va <a target="_blank" href="{{ route('legal', 'privacy') }}" class="underline font-semibold">maxfiylik siyosati</a> bilan roziman</span>
    </label>
    @error('agree')<p class="field-error">{{ $message }}</p>@enderror

    <button class="btn btn-accent btn-lg btn-block">Sinovni boshlash</button>
</form>
@endsection
@section('below')<p class="text-center mt-5 font-medium text-ink-soft">Hisobingiz bormi? <a href="{{ route('login') }}" class="text-teal-700 font-semibold hover:underline">Kirish</a></p>@endsection
