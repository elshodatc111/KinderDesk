@extends('layouts.admin')
@section('title', $tenant->name)
@section('actions')
<form method="post" action="{{ route('admin.tenants.impersonate', $tenant) }}">@csrf<button class="btn btn-sm"><x-icon name="user" /> Kabinetga kirish</button></form>
@endsection
@section('content')
@php $state = $tenant->accessState(); @endphp
<div class="grid lg:grid-cols-3 gap-5">
    <div class="card card-pad lg:col-span-2 space-y-3">
        <h2 class="section-title">Ma'lumot</h2>
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-2 font-medium text-sm">
            <div><dt class="text-ink-mute">Direktor</dt><dd>{{ $tenant->owner?->name }} · {{ format_phone($tenant->owner?->phone) }}</dd></div>
            <div><dt class="text-ink-mute">Bog'cha telefoni</dt><dd>{{ format_phone($tenant->phone) }}</dd></div>
            <div><dt class="text-ink-mute">Email</dt><dd>{{ $tenant->email ?: '—' }}</dd></div>
            <div><dt class="text-ink-mute">STIR</dt><dd>{{ $tenant->tax_id ?: '—' }}</dd></div>
            <div><dt class="text-ink-mute">Hudud</dt><dd>{{ trim($tenant->region.' '.$tenant->city) ?: '—' }}</dd></div>
            <div><dt class="text-ink-mute">Ro'yxatdan o'tgan</dt><dd>{{ $tenant->created_at->format('d.m.Y H:i') }}</dd></div>
            <div><dt class="text-ink-mute">Oxirgi faollik</dt><dd>{{ $tenant->last_active_at?->diffForHumans() ?? '—' }}</dd></div>
            <div><dt class="text-ink-mute">Foydalanuvchilar / bolalar / xodimlar / guruhlar</dt><dd>{{ $counts['users'] }} / {{ $counts['kids'] }} / {{ $counts['employees'] }} / {{ $counts['groups'] }}</dd></div>
        </dl>
    </div>
    <div class="space-y-4">
        <div class="tile" style="--tile-bg:var(--color-teal-50)"><p class="tile-label">Holat · {{ $tenant->edition->name }}</p>
            <p class="tile-value">{{ ['trial'=>'Sinov','active'=>'Faol','lifetime'=>'Umrbod','read_only'=>"Faqat ko'rish",'suspended'=>'Bloklangan'][$state] }}{{ $tenant->isManualReadOnly() ? ' (qo\'lda)' : '' }}</p>
            <p class="text-sm font-medium text-ink-soft">{{ $tenant->lifetime ? 'Muddatsiz — to\'lov olinmaydi' : $tenant->access_expires_at?->format('d.m.Y H:i').' gacha' }}</p>
            @if ($tenant->isManualReadOnly())<p class="text-sm font-semibold text-danger mt-1">Sabab: {{ $tenant->manual_readonly_reason }}@if($tenant->manual_readonly_until) · {{ $tenant->manual_readonly_until->format('d.m.Y') }} gacha @endif</p>@endif
            @if ($state==='read_only' && ! $tenant->isManualReadOnly())<p class="text-sm font-semibold text-danger mt-1">{{ $tenant->daysSinceExpiry() }} kun o'tdi · o'chirish sanasi {{ $tenant->deletionDueOn()?->format('d.m.Y') }}</p>@endif</div>
        <div class="tile" style="--tile-bg:var(--color-marigold-50);--tile-accent:var(--color-marigold-500)"><p class="tile-label">Balans</p><p class="tile-value">{{ money($tenant->balance) }} <span class="text-sm">so'm</span></p></div>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-5 mt-6">
    <form method="post" action="{{ route('admin.tenants.grant', $tenant) }}" class="card card-pad space-y-3">@csrf
        <h2 class="section-title">Bepul muddat berish</h2>
        <div class="grid grid-cols-2 gap-3">
            <select name="edition_id" class="select">@foreach($editions as $e)<option value="{{ $e->id }}" @selected($e->id===$tenant->edition_id)>{{ $e->name }}</option>@endforeach</select>
            <input name="months" type="number" min="1" max="36" value="1" class="input" aria-label="Oy">
        </div><button class="btn btn-primary">Berish</button>
    </form>
    <form method="post" action="{{ route('admin.tenants.wallet', $tenant) }}" class="card card-pad space-y-3">@csrf
        <h2 class="section-title">Balansni tuzatish</h2>
        <div class="grid grid-cols-2 gap-3"><input name="amount" type="number" class="input" placeholder="+100000 yoki -50000" required><input name="note" class="input" placeholder="Sabab" required></div>
        <button class="btn">Saqlash</button>
    </form>
    <form method="post" action="{{ route('admin.tenants.edition', $tenant) }}" class="card card-pad space-y-3">@csrf @method('put')
        <h2 class="section-title">Nashrni almashtirish</h2>
        <select name="edition_id" class="select">@foreach($editions as $e)<option value="{{ $e->id }}" @selected($e->id===$tenant->edition_id)>{{ $e->name }}</option>@endforeach</select>
        <button class="btn">O'zgartirish</button>
    </form>
    <form method="post" action="{{ route('admin.tenants.message', $tenant) }}" class="card card-pad space-y-3">@csrf
        <h2 class="section-title">Direktorga xabar</h2>
        <input name="title" class="input" placeholder="Sarlavha" required><input name="body" class="input" placeholder="Matn (ixtiyoriy)">
        <button class="btn">Yuborish</button>
    </form>
    @if($tenant->owner)
    <form method="post" action="{{ route('admin.tenants.twofa-reset', $tenant) }}" class="card card-pad space-y-3" onsubmit="return confirm('{{ __("Direktorning Telegram ulanishi va zaxira kodlari o'chiriladi. Keyingi kirishda qayta ulaydi. Davom etasizmi?") }}')">@csrf
        <h2 class="section-title">{{ __("Telegram-kod (2 bosqichli tasdiq)") }}</h2>
        <p class="help">{{ $tenant->owner->tg_chat_id ? __('Ulangan') .($tenant->owner->tg_username ? ' — @'.$tenant->owner->tg_username : '') : __('Ulanmagan') }}</p>
        <button class="btn">{{ __('Direktor Telegramini tiklash') }}</button>
    </form>
    @endif
</div>

<form method="post" action="{{ route('admin.tenants.features', $tenant) }}" class="card card-pad mt-6">@csrf @method('put')
    <h2 class="section-title mb-1">Imkoniyatlarni alohida sozlash</h2>
    <p class="help mb-4">«Nashr bo'yicha» — nashrdagi qiymat. Faqat shu bog'cha uchun yoqish yoki o'chirish mumkin.</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($features as $key => $f) @continue(!empty($f['core']))
            @php $cur = array_key_exists($key, $overrides) ? ($overrides[$key] ? 'on' : 'off') : 'default'; @endphp
            <label class="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2 bg-white"><span class="font-medium text-sm">{{ $f['label'] }}</span>
                <select name="f[{{ $key }}]" class="select !w-36 !py-1"><option value="default" @selected($cur==='default')>Nashr bo'yicha</option><option value="on" @selected($cur==='on')>Yoqilgan</option><option value="off" @selected($cur==='off')>O'chirilgan</option></select></label>
        @endforeach
    </div>
    <button class="btn btn-primary mt-4">Saqlash</button>
</form>

<div class="grid lg:grid-cols-2 gap-5 mt-6">
    <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Obuna davri</th><th>Nashr</th><th class="text-right">Narx</th></tr></thead><tbody>
        @forelse ($subscriptions as $s)<tr><td>{{ $s->starts_on->format('d.m.Y') }} — {{ $s->ends_on->format('d.m.Y') }}</td><td>{{ $s->edition?->name }}</td><td class="text-right">{{ $s->price ? money($s->price) : 'Sovg\'a' }}</td></tr>@empty<tr><td colspan="3" class="text-center py-5 text-ink-mute font-medium">Obuna yo'q</td></tr>@endforelse</tbody></table></div></div>
    <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Balans harakati</th><th class="text-right">Summa</th></tr></thead><tbody>
        @forelse ($transactions as $w)<tr><td>{{ $w->created_at->format('d.m.Y') }} · {{ $w->description }}</td><td class="text-right font-semibold {{ $w->amount<0?'text-danger':'text-leaf-700' }}">{{ money($w->amount) }}</td></tr>@empty<tr><td colspan="2" class="text-center py-5 text-ink-mute font-medium">Harakat yo'q</td></tr>@endforelse</tbody></table></div></div>
</div>

<div class="grid lg:grid-cols-2 gap-5 mt-6">
    <div class="card card-pad space-y-3">
        <h2 class="section-title">Qo'lda «faqat ko'rish»</h2>
        @if ($tenant->isManualReadOnly())
            <p class="banner banner-danger text-sm">Yoqilgan: {{ $tenant->manual_readonly_reason }}. To'lov kelsa ham olib tashlamaguncha ochilmaydi.</p>
            <form method="post" action="{{ route('admin.tenants.readonly.off', $tenant) }}" class="space-y-3">@csrf @method('delete')
                <input name="reason" class="input" placeholder="Olib tashlash sababi (jurnalga)"><button class="btn btn-primary">Olib tashlash</button></form>
        @else
            <p class="help">Yozish yopiladi, ko'rish ochiq qoladi. Sabab majburiy — direktor uni ko'radi.</p>
            <form method="post" action="{{ route('admin.tenants.readonly', $tenant) }}" class="space-y-3">@csrf
                <input name="reason" class="input" placeholder="Sabab (majburiy)" required minlength="3">
                <div><label class="label">Muddat (ixtiyoriy)</label><input type="date" name="until" class="input" min="{{ now()->addDay()->toDateString() }}"></div>
                <button class="btn">Yoqish</button></form>
        @endif
    </div>
    <div class="card card-pad space-y-3">
        <h2 class="section-title">Umrbod rejim</h2>
        <p class="help">Tarif to'lovi olinmaydi, obuna eslatmalari va 90 kunlik o'chirish xabari yuborilmaydi, barcha imkoniyatlar ochiq. AI uchun oylik limit saqlanadi.</p>
        <form method="post" action="{{ route('admin.tenants.lifetime', $tenant) }}" class="space-y-3">@csrf
            <input name="reason" class="input" placeholder="Sabab (jurnalga)" required minlength="3">
            <button class="btn {{ $tenant->lifetime ? '' : 'btn-primary' }}">{{ $tenant->lifetime ? "Umrbodni o'chirish" : 'Umrbod qilish' }}</button></form>
    </div>
</div>

<form method="post" action="{{ route('admin.ai.tenant', $tenant) }}" class="card card-pad mt-6 space-y-3">@csrf @method('put')
    <h2 class="section-title">AI</h2>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="ai_disabled" value="1" @checked($tenant->ai_disabled)> Shu bog'cha uchun AI'ni majburan o'chirish</label>
    <div class="max-w-xs"><label class="label">Oylik token limiti (bo'sh — standart)</label><input type="number" min="0" name="ai_token_limit" value="{{ $tenant->ai_token_limit }}" class="input"></div>
    <button class="btn">Saqlash</button>
</form>

<div class="card card-pad mt-8 border-danger" style="border-color:var(--color-danger)" x-data="{ open: false }">
    <h2 class="section-title text-danger">Xavfli amallar</h2>
    <div class="flex flex-wrap gap-3 mt-3">
        <form method="post" action="{{ route('admin.tenants.suspend', $tenant) }}">@csrf <button class="btn">{{ $tenant->status === 'suspended' ? 'Blokdan chiqarish' : 'Bloklash' }}</button></form>
        <button type="button" class="btn btn-danger" @click="open = !open">Butunlay o'chirish</button>
    </div>
    <form x-show="open" x-cloak method="post" action="{{ route('admin.tenants.destroy', $tenant) }}" class="mt-4 space-y-3 max-w-lg">@csrf @method('delete')
        <p class="banner banner-danger text-sm">Bu amalni qaytarib bo'lmaydi. Barcha bolalar, xodimlar, to'lovlar va fayllar butunlay o'chadi.</p>
        <div><label class="label">Tasdiqlash uchun bog'cha nomini yozing: <b>{{ $tenant->name }}</b></label><input name="confirm_name" class="input" autocomplete="off" required>@error('confirm_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Sizning parolingiz</label><input type="password" name="password" class="input" required>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Sabab (ixtiyoriy)</label><input name="reason" class="input"></div>
        <button class="btn btn-danger">Qaytarib bo'lmas tarzda o'chirish</button>
    </form>
</div>
@endsection
