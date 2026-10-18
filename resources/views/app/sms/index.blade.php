@extends('layouts.app')
@section('title', 'SMS xabarlar')
@section('content')
@php
    $tabs = ['events' => 'Qachon yuboriladi', 'templates' => 'Matnlar (shablonlar)', 'account' => 'Eskiz ulanishi', 'send' => 'Xabar yuborish', 'logs' => 'Jurnal'];
    $badge = ['draft' => 'badge-gray', 'pending' => 'badge-orange', 'approved' => 'badge-leaf', 'rejected' => 'badge-red'];
    $connected = $account && $account->is_active;
    $logBadge = ['sent' => 'badge-leaf', 'delivered' => 'badge-leaf', 'queued' => 'badge-sky', 'failed' => 'badge-red', 'blocked' => 'badge-orange'];
    $logLabel = ['sent' => 'Yuborildi', 'delivered' => 'Yetkazildi', 'queued' => 'Navbatda', 'failed' => 'Xato', 'blocked' => "Yuborilmadi"];
@endphp
<div class="tabs mb-6 overflow-x-auto">@foreach($tabs as $k=>$l)<a href="{{ route('app.sms.index', ['tab'=>$k]) }}" class="tab {{ $tab===$k?'is-active':'' }}">{{ $l }}</a>@endforeach</div>

@unless($connected)
<div class="banner banner-warn mb-5"><x-icon name="alert" /><div class="flex-1"><p>Eskiz akkaunti ulanmagan — SMS yuborilmaydi.</p><p class="text-sm font-semibold text-ink-soft">Avval «Eskiz ulanishi» bo'limida o'z akkauntingizni ulang.</p></div><a class="btn btn-sm btn-accent" href="{{ route('app.sms.index', ['tab'=>'account']) }}">Ulash</a></div>
@endunless

@if ($tab === 'events')
<form method="post" action="{{ route('app.sms.rules') }}" class="space-y-4 w-full">@csrf
    <p class="help">Har bir holat uchun SMS yuborilishini yoqing yoki o'chiring. SMS faqat <b>Eskiz tasdiqlagan</b> matn bilan ketadi.</p>
    @foreach ($events as $key => $def)
        @php $rule = $rules[$key] ?? null; $tpl = $rule?->template_id ? $templates->firstWhere('id', $rule->template_id) : null; $opts = $rule?->options ?? [];
            $mine = $templates->where('event_key', $key); @endphp
        <div class="card card-pad" x-data="{ on: {{ $rule?->enabled ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between gap-4">
                <div><p class="font-semibold text-lg">{{ $def['label'] }}</p>
                    @if($rule?->enabled)
                        @if(! $connected)<p class="text-sm font-medium text-danger">Yuborilmaydi: Eskiz ulanmagan</p>
                        @elseif(! $tpl)<p class="text-sm font-medium text-danger">Yuborilmaydi: shablon tanlanmagan</p>
                        @elseif($tpl->status !== 'approved')<p class="text-sm font-medium text-danger">Yuborilmaydi: matn Eskiz tomonidan tasdiqlanmagan ({{ $tpl->statusLabel() }})</p>
                        @else<p class="text-sm font-medium text-leaf-700">Tayyor: SMS yuboriladi</p>@endif
                    @else<p class="text-sm font-medium text-ink-mute">O'chirilgan</p>@endif</div>
                <label class="switch"><input type="checkbox" name="rules[{{ $key }}][enabled]" value="1" x-model="on"><span></span></label>
            </div>
            <div x-show="on" x-cloak class="grid sm:grid-cols-3 gap-4 mt-4 pt-4 border-t border-line">
                <div class="sm:col-span-3"><label class="label">Qaysi matn ishlatilsin</label>
                    <select name="rules[{{ $key }}][template_id]" class="select">@foreach($mine as $t)<option value="{{ $t->id }}" @selected($rule?->template_id===$t->id)>{{ $t->name }} — {{ $t->statusLabel() }}</option>@endforeach</select></div>
                <div><label class="label">Tinch soat: dan</label><input type="time" name="rules[{{ $key }}][quiet_from]" value="{{ $opts['quiet_from'] ?? '' }}" class="input"></div>
                <div><label class="label">gacha</label><input type="time" name="rules[{{ $key }}][quiet_to]" value="{{ $opts['quiet_to'] ?? '' }}" class="input"></div>
                <p class="help sm:col-span-3 -mt-2">Tinch soatlarda SMS yuborilmaydi (masalan 21:00 – 07:00).</p>
            </div>
        </div>
    @endforeach
    <button class="btn btn-primary btn-lg">Saqlash</button>
</form>

@elseif ($tab === 'templates')
<div class="w-full space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="help max-w-xl">Eskiz har bir matnni oldindan tekshiradi. Matnni «Eskizga yuborish» bilan tasdiqlatasiz, so'ng holatini tekshirasiz. Tasdiqlanmagan matn bilan SMS ketmaydi.</p>
        <form method="post" action="{{ route('app.sms.templates.sync') }}">@csrf<button class="btn btn-sm" @disabled(!$account)><x-icon name="refresh" /> Holatni tekshirish</button></form>
    </div>
    @foreach ($templates as $t)
        <div class="card card-pad space-y-3">
            <div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2"><h3 class="font-semibold">{{ $t->name }}</h3><span class="badge badge-gray">{{ $events[$t->event_key]['label'] ?? $t->event_key }}</span></div>
                <span class="badge {{ $badge[$t->status] ?? 'badge-gray' }}">{{ $t->statusLabel() }}</span></div>
            <form method="post" action="{{ route('app.sms.templates.update', $t) }}" class="space-y-2">@csrf @method('put')
                <input name="name" value="{{ $t->name }}" class="input"><textarea name="body" rows="2" class="textarea">{{ $t->body }}</textarea>
                <p class="help">O'zgaruvchilar: {{ collect($events[$t->event_key]['vars'] ?? [])->map(fn($v)=>'{'.$v.'}')->implode(' ') }} · {{ mb_strlen($t->body) }} belgi</p>
                <button class="btn btn-sm">Matnni saqlash</button></form>
            @if($t->rejection_reason)<p class="text-sm font-medium text-danger">{{ $t->rejection_reason }}</p>@endif
            @if($t->checked_at)<p class="text-xs font-medium text-ink-mute">Oxirgi tekshiruv: {{ $t->checked_at->format('d.m.Y H:i') }}</p>@endif
            <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-line">
                <form method="post" action="{{ route('app.sms.templates.submit', $t) }}">@csrf<button class="btn btn-sm btn-primary" @disabled(!$account || in_array($t->status, ['pending','approved']))>Eskizga yuborish</button></form>
                <form method="post" action="{{ route('app.sms.templates.test', $t) }}" class="flex gap-2">@csrf<input name="phone" class="input !w-36 !py-1" placeholder="Sizning raqamingiz" required><button class="btn btn-sm" @disabled(!$account)>Sinov SMS</button></form>
                <form method="post" action="{{ route('app.sms.templates.destroy', $t) }}" class="ml-auto" onsubmit="return confirm(@js(__('Shablon o\'chiriladi')))">@csrf @method('delete')<button class="btn-quiet !text-danger">O'chirish</button></form>
            </div>
        </div>
    @endforeach
    <form method="post" action="{{ route('app.sms.templates.store') }}" class="card card-pad space-y-3">@csrf
        <h3 class="section-title">Yangi matn qo'shish</h3>
        <div class="grid sm:grid-cols-2 gap-3"><select name="event_key" class="select">@foreach($events as $k=>$d)<option value="{{ $k }}">{{ $d['label'] }}</option>@endforeach</select><input name="name" class="input" placeholder="Nomi" required></div>
        <textarea name="body" rows="2" class="textarea" placeholder="Matn. Masalan: {bogcha}: {bola} uchun to'lov qabul qilindi." required></textarea>
        <button class="btn btn-primary">Qo'shish</button>
    </form>
</div>

@elseif ($tab === 'account')
<div class="grid lg:grid-cols-2 gap-6 w-full">
    <form method="post" action="{{ route('app.sms.account') }}" class="card card-pad space-y-4">@csrf
        <h2 class="section-title">Eskiz.uz akkaunti</h2>
        <p class="help">Eskiz'da ro'yxatdan o'tgan o'z akkauntingiz ma'lumotlari. SMS xarajati sizning Eskiz balansingizdan yechiladi.</p>
        <div><label class="label">Eskiz email</label><input type="email" name="email" value="{{ old('email', $account?->email) }}" class="input" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Eskiz parol</label><input type="password" name="password" class="input" placeholder="{{ $account ? 'O\'zgartirmaslik uchun bo\'sh qoldiring' : '' }}" autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Jo'natuvchi nomi (from)</label><input name="sender" value="{{ old('sender', $account?->sender ?? '4546') }}" class="input"><p class="help">Eskiz sizga bergan nom yoki 4546</p></div>
        <label class="flex items-center justify-between font-semibold">Yoqilgan<span class="switch"><input type="checkbox" name="is_active" value="1" @checked($account?->is_active ?? true)><span></span></span></label>
        <button class="btn btn-primary">Saqlash va tekshirish</button>
    </form>
    <div class="card card-pad space-y-3 self-start">
        <h2 class="section-title">Holat</h2>
        @if($account)
            <p class="flex justify-between font-medium"><span>Ulanish</span>@if($account->last_error)<span class="badge badge-red">Xato</span>@elseif($account->last_checked_at)<span class="badge badge-leaf">Ulangan</span>@else<span class="badge badge-gray">Tekshirilmagan</span>@endif</p>
            <p class="flex justify-between font-medium"><span>Eskiz balansi</span><span>{{ $account->balance !== null ? number_format($account->balance, 0, '', ' ').' SMS' : '—' }}</span></p>
            @if($account->last_error)<p class="text-sm font-medium text-danger">{{ $account->last_error }}</p>@endif
            @if($account->last_checked_at)<p class="text-xs font-medium text-ink-mute">Tekshirilgan: {{ $account->last_checked_at->format('d.m.Y H:i') }}</p>@endif
            <div class="flex gap-2 pt-2"><form method="post" action="{{ route('app.sms.account.check') }}">@csrf<button class="btn btn-sm">Ulanishni tekshirish</button></form>
                <form method="post" action="{{ route('app.sms.account.remove') }}" onsubmit="return confirm(@js(__('Eskiz akkaunti uziladi. SMS yuborilmay qoladi.')))">@csrf @method('delete')<button class="btn btn-sm !text-danger">Uzish</button></form></div>
        @else<p class="font-medium text-ink-soft">Hali ulanmagan.</p>@endif
    </div>
</div>

@elseif ($tab === 'send')
<div class="grid lg:grid-cols-2 gap-6 w-full">
    <form method="post" action="{{ route('app.sms.bulk') }}" class="card card-pad space-y-4">@csrf
        <h2 class="section-title">Ota-onalarga xabar yuborish</h2>
        @if(!$bulkTemplate || $bulkTemplate->status!=='approved')<p class="banner banner-warn text-sm">«Ommaviy xabar» matni Eskiz tomonidan tasdiqlanmagan. Avval «Matnlar» bo'limida tasdiqlating.</p>@endif
        <div><label class="label">Xabar matni</label><textarea name="text" rows="3" maxlength="300" class="textarea" required></textarea></div>
        <div><label class="label">Kimga</label><select name="group_id" class="select"><option value="">Barcha guruhlar</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
        <label class="check"><input type="checkbox" name="only_debtors" value="1"> Faqat qarzdorlarga</label>
        <button class="btn btn-primary" onclick="return confirm(@js(__('Xabar barcha tanlangan ota-onalarga yuboriladi. Davom etasizmi?')))">Yuborish</button>
    </form>
    <div class="card card-pad space-y-3 self-start"><h2 class="section-title">Qarz eslatmasi</h2>
        <p class="font-semibold text-ink-soft">Qarzi bor barcha bolalarning ota-onasiga «Qarz eslatmasi» matni yuboriladi.</p>
        <form method="post" action="{{ route('app.sms.debts') }}" onsubmit="return confirm(@js(__('Barcha qarzdorlarga SMS yuboriladi. Davom etasizmi?')))">@csrf<button class="btn">Qarzdorlarga yuborish</button></form></div>
</div>

@else
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Vaqt</th><th>Kimga</th><th>Hodisa</th><th>Holat</th><th>Matn</th></tr></thead><tbody>
@forelse($logs as $l)<tr><td class="whitespace-nowrap">{{ $l->created_at->format('d.m H:i') }}</td><td>{{ format_phone($l->to) }}</td><td>{{ $events[$l->event_key]['label'] ?? $l->event_key }}</td>
    <td><span class="badge {{ $logBadge[$l->status] ?? 'badge-gray' }}">{{ $logLabel[$l->status] ?? $l->status }}</span>@if($l->error)<span class="block text-xs font-semibold text-ink-soft">{{ $l->error }}</span>@endif</td><td class="text-sm text-ink-soft max-w-xs truncate">{{ $l->message }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">Hali SMS yuborilmagan</div></td></tr>@endforelse
</tbody></table></div></div><div class="mt-4">{{ $logs instanceof \Illuminate\Pagination\AbstractPaginator ? $logs->links() : '' }}</div>
@endif
@endsection
