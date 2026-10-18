@extends('layouts.admin')
@section('title', 'Platforma sozlamalari')
@section('content')
@php
    $tabs = ['conn' => 'Ulanishlar', 'pay' => "To'lov va aloqa", 'site' => 'Sayt: savollar va huquqiy matnlar', 'sms' => 'SMS shablonlar'];
    $faqRows = collect($faq)->map(fn ($r) => ['q_uz' => $r['q']['uz'] ?? '', 'q_ru' => $r['q']['ru'] ?? '', 'a_uz' => $r['a']['uz'] ?? '', 'a_ru' => $r['a']['ru'] ?? ''])->values()->all();
    $pageLabels = \App\Support\Site\Legal::PAGES;
@endphp
<div class="tabs mb-6 overflow-x-auto" role="tablist">
    @foreach($tabs as $k => $l)<a class="tab {{ $tab === $k ? 'is-active' : '' }}" href="{{ route('admin.settings.index', ['tab' => $k]) }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}">{{ __($l) }}</a>@endforeach
</div>

@if($tab === 'conn')
<div class="grid xl:grid-cols-[1fr_22rem] gap-6 items-start">
<form method="post" action="{{ route('admin.settings.connections') }}" class="card card-pad space-y-6">@csrf @method('put')
    <div class="space-y-4">
        <h2 class="section-title">Eskiz (SMS)</h2>
        <div><label class="label">SMS rejimi</label>
            <select name="sms_driver" class="select"><option value="log" @selected($conn['sms_driver']!=='eskiz')>Sinov (SMS yuborilmaydi, kod logga yoziladi)</option><option value="eskiz" @selected($conn['sms_driver']==='eskiz')>Eskiz (haqiqiy yuborish)</option></select></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="label">Eskiz email</label><input name="eskiz_email" class="input" value="{{ old('eskiz_email', $conn['eskiz_email']) }}" autocomplete="off"></div>
            <div><label class="label">Eskiz parol / maxfiy kalit</label><input type="password" name="eskiz_password" class="input" autocomplete="new-password" placeholder="{{ $conn['eskiz_password_set'] ? '•••••••• — o\'zgartirish uchun yangisini kiriting' : '' }}"></div>
            <div><label class="label">Yuboruvchi nomi (from)</label><input name="eskiz_from" class="input" value="{{ old('eskiz_from', $conn['eskiz_from']) }}"></div>
            <div><label class="label">Callback URL (ixtiyoriy)</label><input name="eskiz_callback_url" class="input" value="{{ old('eskiz_callback_url', $conn['eskiz_callback_url']) }}">@error('eskiz_callback_url')<p class="field-error">{{ $message }}</p>@enderror</div>
        </div>
        @if($conn['eskiz_password_set'])<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="forget_eskiz" value="1"> Saqlangan Eskiz parolini o'chirish</label>@endif
    </div>
    <div class="space-y-4 border-t border-line pt-6">
        <h2 class="section-title">Payme</h2>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="label">Merchant ID</label><input name="payme_merchant_id" class="input" value="{{ old('payme_merchant_id', $conn['payme_merchant_id']) }}" autocomplete="off"></div>
            <div><label class="label">Ruxsat etilgan IP'lar (vergul bilan)</label><input name="payme_allowed_ips" class="input" value="{{ old('payme_allowed_ips', $conn['payme_allowed_ips']) }}"></div>
            <div><label class="label">Asosiy kalit (KEY)</label><input type="password" name="payme_key" class="input" autocomplete="new-password" placeholder="{{ $conn['payme_key_set'] ? '••••••••' : '' }}"></div>
            <div><label class="label">Sinov kaliti (TEST KEY)</label><input type="password" name="payme_test_key" class="input" autocomplete="new-password" placeholder="{{ $conn['payme_test_key_set'] ? '••••••••' : '' }}"></div>
        </div>
        <label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="payme_test_mode" value="0"><input type="checkbox" name="payme_test_mode" value="1" @checked($conn['payme_test_mode'])> Sinov rejimi (haqiqiy pul yechilmaydi)</label>
        @if($conn['payme_key_set'] || $conn['payme_test_key_set'])<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="forget_payme" value="1"> Saqlangan Payme kalitlarini o'chirish</label>@endif
    </div>
    <div class="flex flex-wrap items-center gap-3"><button class="btn btn-primary">Saqlash</button><p class="help">Kalitlar bazada shifrlangan saqlanadi va qayta ko'rsatilmaydi. Bo'sh qoldirilgan maxfiy maydon eski qiymatni o'zgartirmaydi.</p></div>
</form>

<div class="card card-pad space-y-3">
    <h2 class="section-title">Ulanishlar holati</h2>
    <div class="flex items-center justify-between gap-2"><span class="font-medium">Payme</span>
        @if($paymeEnabled)<span class="badge badge-leaf">Ulangan {{ $paymeTest ? '(sinov rejimi)' : '' }}</span>@else<span class="badge badge-gray">Ulanmagan: faqat karta + chek</span>@endif</div>
    <div class="flex items-center justify-between gap-2"><span class="font-medium">Eskiz (platforma)</span>
        @if($smsDriver==='eskiz' && $eskizConfigured)<span class="badge badge-leaf">Yoqilgan</span>@else<span class="badge badge-orange">Sinov rejimi</span>@endif</div>
    <form method="post" action="{{ route('admin.settings.eskiz-check') }}">@csrf <button class="btn btn-sm">Eskiz ulanishini tekshirish</button></form>
</div>
</div>

@elseif($tab === 'pay')
<form method="post" action="{{ route('admin.settings.update') }}" class="card card-pad space-y-4 max-w-3xl">@csrf @method('put')
    <h2 class="section-title">To'lov: karta ma'lumotlari</h2>
    <p class="help">Payme ulanmagan bog'chalar shu kartaga o'tkazma qilib, chekni yuboradi.</p>
    <div><label class="label">Karta raqami</label><input name="payment_card_number" class="input" value="{{ $values['payment.card_number'] }}" inputmode="numeric"></div>
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Karta egasi</label><input name="payment_card_holder" class="input" value="{{ $values['payment.card_holder'] }}"></div>
        <div><label class="label">Bank</label><input name="payment_card_bank" class="input" value="{{ $values['payment.card_bank'] }}"></div></div>
    <div><label class="label">Eng kam to'lov (so'm)</label><input name="payment_min_topup" type="number" class="input" value="{{ $values['payment.min_topup'] }}"></div>
    <div><label class="label">Bog'chaga ko'rsatiladigan izoh</label><textarea name="payment_note" class="textarea" rows="2">{{ $values['payment.note'] }}</textarea></div>
    <h2 class="section-title pt-2">Qo'llab-quvvatlash va sayt pastki qismi (footer) aloqasi</h2>
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">Telefon</label><input name="support_phone" class="input" value="{{ $values['support.phone'] }}" inputmode="tel" placeholder="+998 90 123 45 67"></div>
        <div><label class="label">Telegram</label><input name="support_telegram" class="input" value="{{ $values['support.telegram'] }}" placeholder="@kinderdesk_support"></div></div>
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">{{ __('Ish vaqti: boshlanishi') }}</label><input type="time" name="support_from" class="input" value="{{ $values['support.from'] }}"></div>
        <div><label class="label">{{ __('Ish vaqti: tugashi') }}</label><input type="time" name="support_to" class="input" value="{{ $values['support.to'] }}"></div></div>
    <div><span class="label">{{ __('Ish kunlari') }}</span><div class="flex flex-wrap gap-3">
        @foreach(\App\Support\Site\SupportInfo::DAYS as $n => $d)<label class="flex items-center gap-1.5 text-sm font-medium"><input type="checkbox" class="check" name="support_days[]" value="{{ $n }}" @checked(in_array($n, \App\Support\Site\SupportInfo::days(), true))> {{ __($d) }}</label>@endforeach</div></div>
    <div><label class="label">{{ __('Javob muddati') }}</label><input name="support_response" class="input" value="{{ $values['support.response'] }}" placeholder="{{ __('Masalan: 30 daqiqa ichida') }}"></div>
    <p class="help">Bitta telefon va bitta Telegram. Ular sotuv sahifasi pastida va bog'chalarning «Qo'llab-quvvatlash» bo'limida ko'rinadi.</p>
    <button class="btn btn-primary">Saqlash</button>
</form>

@elseif($tab === 'site')
<div class="space-y-8" x-data="{ page: 'oferta' }">
<section class="card card-pad space-y-4" x-data='{ rows: @json($faqRows) }'>
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="section-title">Ko'p beriladigan savollar (sotuv sahifasi)</h2>
        <button type="button" class="btn btn-sm" @click="rows.push({q_uz:'',q_ru:'',a_uz:'',a_ru:''})"><x-icon name="plus" /> Savol qo'shish</button></div>
    <form method="post" action="{{ route('admin.settings.faq') }}" class="space-y-4">@csrf @method('put')
        <template x-for="(r, i) in rows" :key="i">
            <div class="rounded-xl border border-line p-4 space-y-3">
                <div class="flex items-center justify-between"><span class="text-sm font-semibold text-ink-soft" x-text="'№ ' + (i + 1)"></span>
                    <div class="flex gap-1"><button type="button" class="btn-quiet" :disabled="i === 0" @click="rows.splice(i-1, 2, rows[i], rows[i-1])" aria-label="Yuqoriga">↑</button>
                        <button type="button" class="btn-quiet" :disabled="i === rows.length - 1" @click="rows.splice(i, 2, rows[i+1], rows[i])" aria-label="Pastga">↓</button>
                        <button type="button" class="btn-quiet !text-danger" @click="rows.splice(i, 1)">O'chirish</button></div></div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div><label class="label">Savol (UZ)</label><input class="input" :name="`faq[${i}][q_uz]`" x-model="r.q_uz" maxlength="250"></div>
                    <div><label class="label">Вопрос (RU)</label><input class="input" :name="`faq[${i}][q_ru]`" x-model="r.q_ru" maxlength="250"></div>
                    <div><label class="label">Javob (UZ)</label><textarea class="textarea" rows="3" :name="`faq[${i}][a_uz]`" x-model="r.a_uz" maxlength="1500"></textarea></div>
                    <div><label class="label">Ответ (RU)</label><textarea class="textarea" rows="3" :name="`faq[${i}][a_ru]`" x-model="r.a_ru" maxlength="1500"></textarea></div>
                </div>
            </div>
        </template>
        <p x-show="rows.length === 0" class="empty">Savollar yo'q. «Savol qo'shish» tugmasini bosing.</p>
        <button class="btn btn-primary">Savollarni saqlash</button>
    </form>
</section>

<section class="card card-pad space-y-4">
    <h2 class="section-title">Huquqiy matnlar</h2>
    <p class="help">Quyida namuna matnlar yozilgan. Kvadrat qavsdagi joylarni (<code>[KOMPANIYA NOMI]</code>, <code>[STIR]</code> va h.k.) o'z rekvizitlaringiz bilan almashtiring va yurist bilan kelishing. Matn Markdown (<code>## Sarlavha</code>, <code>- ro'yxat</code>) qabul qiladi; UZ va RU alohida.</p>
    <div class="tabs overflow-x-auto">@foreach($pageLabels as $pk => $pl)<button type="button" class="tab" :class="page === '{{ $pk }}' && 'is-active'" @click="page = '{{ $pk }}'">{{ __($pl) }}</button>@endforeach</div>
    @foreach($pageLabels as $pk => $pl)
    <div x-show="page === '{{ $pk }}'" @if(!$loop->first) x-cloak @endif class="space-y-3">
        <form method="post" action="{{ route('admin.settings.legal') }}" class="space-y-3">@csrf @method('put')<input type="hidden" name="page" value="{{ $pk }}">
            <div class="grid xl:grid-cols-2 gap-4">
                <div><label class="label">O'zbekcha @if($legalTexts[$pk]['sample_uz'])<span class="badge badge-orange ml-1">namuna</span>@endif</label><textarea name="text_uz" rows="18" class="textarea font-mono !text-sm">{{ $legalTexts[$pk]['uz'] }}</textarea></div>
                <div><label class="label">Русский @if($legalTexts[$pk]['sample_ru'])<span class="badge badge-orange ml-1">образец</span>@endif</label><textarea name="text_ru" rows="18" class="textarea font-mono !text-sm">{{ $legalTexts[$pk]['ru'] }}</textarea></div>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="reconsent" value="1" class="check"> {{ __("Qayta rozilik talab qilish (barcha foydalanuvchilar yangi matnga qayta rozilik beradi)") }}</label>
            <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Saqlash</button><a class="btn" target="_blank" rel="noopener" href="{{ route('legal', $pk) }}">Sahifani ko'rish</a></div>
        </form>
        <form method="post" action="{{ route('admin.settings.legal.reset') }}" onsubmit="return confirm('{{ __("Namuna matn qaytariladi, o'zgarishlaringiz o'chadi") }}')">@csrf @method('delete')<input type="hidden" name="page" value="{{ $pk }}"><button class="btn btn-sm">Namuna matnni qaytarish</button></form>
    </div>
    @endforeach
</section>
</div>

@else
<section>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h2 class="section-title">Platforma SMS shablonlari (login, kod, direktor ogohlantirishlari)</h2>
        <form method="post" action="{{ route('admin.settings.templates.sync') }}">@csrf <button class="btn btn-sm"><x-icon name="refresh" /> Eskiz holatini tekshirish</button></form>
    </div>
    <div class="grid lg:grid-cols-2 gap-4">
    @foreach ($templates as $t)
        @php $badge = ['draft'=>'badge-gray','pending'=>'badge-orange','approved'=>'badge-leaf','rejected'=>'badge-red'][$t->status] ?? 'badge-gray'; @endphp
        <div class="card card-pad space-y-3">
            <div class="flex items-center justify-between"><h3 class="font-semibold">{{ $t->name }}</h3><span class="badge {{ $badge }}">{{ $t->statusLabel() }}</span></div>
            <form method="post" action="{{ route('admin.settings.templates.update', $t) }}" class="space-y-2">@csrf @method('put')
                <textarea name="body" rows="3" class="textarea">{{ $t->body }}</textarea>
                <p class="help">O'zgaruvchilar: {{ collect($events[$t->event_key]['vars'] ?? [])->map(fn($v) => '{'.$v.'}')->implode(' ') }}</p>
                <button class="btn btn-sm">Matnni saqlash</button>
            </form>
            @if($t->rejection_reason)<p class="text-sm font-medium text-danger">{{ $t->rejection_reason }}</p>@endif
            <div class="flex flex-wrap gap-2 items-center border-t border-line pt-3">
                <form method="post" action="{{ route('admin.settings.templates.submit', $t) }}">@csrf <button class="btn btn-sm btn-primary" @disabled($t->status==='pending'||$t->status==='approved')>Eskizga tasdiqlashga yuborish</button></form>
                <form method="post" action="{{ route('admin.settings.templates.test', $t) }}" class="flex gap-2">@csrf <input name="phone" class="input !w-36 !py-1" placeholder="90 123 45 67" required><button class="btn btn-sm">Sinov SMS</button></form>
            </div>
        </div>
    @endforeach
    </div>
    @if ($logs->isNotEmpty())
    <div class="card table-wrap mt-5"><div class="table-scroll"><table class="table"><thead><tr><th>Vaqt</th><th>Kimga</th><th>Hodisa</th><th>Holat</th></tr></thead><tbody>
        @foreach ($logs as $l)<tr><td>{{ $l->created_at->format('d.m H:i') }}</td><td>{{ format_phone($l->to) }}</td><td>{{ $l->event_key }}</td><td><span class="badge {{ in_array($l->status,['sent','delivered'])?'badge-leaf':($l->status==='failed'?'badge-red':'badge-gray') }}">{{ $l->status }}</span> <span class="text-xs text-ink-soft">{{ $l->error }}</span></td></tr>@endforeach
    </tbody></table></div></div>@endif
</section>

@endif
@endsection
