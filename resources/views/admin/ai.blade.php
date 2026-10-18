@extends('layouts.admin')
@section('title', 'AI')
@section('content')
@php
    $usd = fn ($micro) => '$'.number_format($micro / 1e6, 2);
    $tok = fn ($n) => number_format((int) $n, 0, '.', ' ');
    $max = max(1, $trend->max('cost'));
@endphp
<div class="space-y-6">
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="tile" style="--tile-bg:var(--color-teal-50)"><p class="tile-label">Bugun</p><p class="tile-value">{{ $usd($today->cost) }}</p><p class="text-sm font-medium text-ink-soft">{{ $tok($today->i + $today->o) }} token · {{ $today->n }} chaqiruv</p></div>
        <div class="tile" style="--tile-bg:var(--color-marigold-50)"><p class="tile-label">Shu oy</p><p class="tile-value">{{ $usd($month->cost) }}</p><p class="text-sm font-medium text-ink-soft">{{ $tok($month->i + $month->o) }} token · {{ $month->n }} chaqiruv</p></div>
        <div class="tile" style="--tile-bg:var(--color-sky-50)"><p class="tile-label">Holat</p><p class="tile-value">{{ $configured ? ($enabled ? 'Ulangan' : "O'chirilgan") : 'Kalit yo\'q' }}</p><p class="text-sm font-medium text-ink-soft">Oxirgi muvaffaqiyatli: {{ $lastOk ? \Illuminate\Support\Carbon::parse($lastOk)->format('d.m H:i') : '—' }}</p></div>
        <div class="tile" style="--tile-bg:var(--color-berry-50)"><p class="tile-label">Bloklangan so'rovlar</p><p class="tile-value">{{ $blocked }}</p><p class="text-sm font-medium text-ink-soft">Shaxsiy ma'lumot aniqlangani uchun yuborilmagan (shu oy)</p></div>
    </div>

    <div class="card card-pad">
        <h2 class="section-title mb-3">Oxirgi 30 kun xarajati</h2>
        <div class="flex items-end gap-1 h-28">@foreach($trend as $d)<div class="flex-1 rounded-t bg-teal-500/80" style="height: {{ max(2, $d['cost'] / $max * 100) }}%" title="{{ $d['d'] }}: {{ $usd($d['cost']) }}"></div>@endforeach</div>
        <p class="help mt-2">Hisob taxminiy: narxlar quyida qo'lda kiritiladi. Haqiqiy hisob-fakturani provayder panelidan solishtiring.</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>Eng ko'p sarflagan bog'chalar (shu oy)</th><th class="text-right">Token</th><th class="text-right">Limit</th><th class="text-right">Narx</th></tr></thead><tbody>
            @forelse($byTenant as $r)<tr><td>@if($r['tenant'])<a class="font-semibold hover:underline" href="{{ route('admin.tenants.show', $r['tenant']) }}">{{ $r['tenant']->name }}</a>@else —@endif</td>
                <td class="text-right">{{ $tok($r['tokens']) }}</td><td class="text-right">{{ $r['limit'] ? $tok($r['limit']).' ('.round($r['tokens'] / $r['limit'] * 100).'%)' : '—' }}</td><td class="text-right font-semibold">{{ $usd($r['cost']) }}</td></tr>
            @empty<tr><td colspan="4"><div class="empty">Hali chaqiruv yo'q</div></td></tr>@endforelse
        </tbody></table></div></div>
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>Tur bo'yicha (shu oy)</th><th class="text-right">Chaqiruv</th><th class="text-right">Narx</th></tr></thead><tbody>
            @forelse($byKind as $k)<tr><td>{{ ['chat' => 'Chat', 'report' => 'Hisobot', 'test' => 'Sinov'][$k->kind] ?? $k->kind }}</td><td class="text-right">{{ $k->n }}</td><td class="text-right font-semibold">{{ $usd($k->cost) }}</td></tr>
            @empty<tr><td colspan="3"><div class="empty">—</div></td></tr>@endforelse
        </tbody></table></div></div>
    </div>

    <form method="post" action="{{ route('admin.ai.update') }}" class="card card-pad space-y-4">@csrf @method('put')
        <h2 class="section-title">Sozlamalar</h2>
        <label class="flex items-center gap-2 text-sm font-medium"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($enabled)> AI xizmati yoqilgan (hamma bog'cha uchun)</label>
        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="label">API kalit {{ $configured ? '(saqlangan, ekranda ko\'rsatilmaydi)' : '' }}</label>
                <input type="password" name="api_key" class="input" autocomplete="new-password" placeholder="{{ $configured ? '•••••••• — o\'zgartirish uchun yangisini kiriting' : 'sk-ant-…' }}">@error('api_key')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Chat modeli (yengilroq)</label><input name="ai_model_chat" value="{{ old('ai_model_chat', $values['ai.model_chat']) }}" class="input" required></div>
            <div><label class="label">Hisobot modeli (kuchliroq)</label><input name="ai_model_report" value="{{ old('ai_model_report', $values['ai.model_report']) }}" class="input" required></div>
            <div><label class="label">Narx: 1 mln kirish token ($)</label><input name="ai_price_in" value="{{ old('ai_price_in', $values['ai.price_in']) }}" class="input" required></div>
            <div><label class="label">Narx: 1 mln chiqish token ($)</label><input name="ai_price_out" value="{{ old('ai_price_out', $values['ai.price_out']) }}" class="input" required></div>
            <div><label class="label">Narx: 1 mln kesh token ($)</label><input name="ai_price_cache" value="{{ old('ai_price_cache', $values['ai.price_cache']) }}" class="input" required></div>
            <div></div>
            <div><label class="label">Kunlik savol limiti / foydalanuvchi: Standard</label><input type="number" min="0" name="ai_questions_standard" value="{{ old('ai_questions_standard', $values['ai.questions.standard']) }}" class="input" required></div>
            <div><label class="label">Kunlik savol limiti / foydalanuvchi: Premium</label><input type="number" min="0" name="ai_questions_premium" value="{{ old('ai_questions_premium', $values['ai.questions.premium']) }}" class="input" required></div>
            <div><label class="label">Oylik token limiti / bog'cha: Standard</label><input type="number" min="0" name="ai_tokens_standard" value="{{ old('ai_tokens_standard', $values['ai.tokens.standard']) }}" class="input" required></div>
            <div><label class="label">Oylik token limiti / bog'cha: Premium (umrbod ham)</label><input type="number" min="0" name="ai_tokens_premium" value="{{ old('ai_tokens_premium', $values['ai.tokens.premium']) }}" class="input" required></div>
        </div>
        <p class="help">Limitning 80 % ida direktorga ogohlantirish, 100 % da AI to'xtaydi. Aniq bog'cha uchun limitni bog'cha sahifasida o'zgartirasiz.</p>
        <div class="flex flex-wrap gap-3"><button class="btn btn-primary">Saqlash</button></div>
    </form>
    <div class="flex flex-wrap gap-3">
        <form method="post" action="{{ route('admin.ai.test') }}">@csrf<button class="btn" @disabled(! $configured)>Ulanishni sinab ko'rish</button></form>
        @if($configured)<form method="post" action="{{ route('admin.ai.key.forget') }}" onsubmit="return confirm('API kalit o\'chiriladi')">@csrf @method('delete')<button class="btn btn-danger">Kalitni o'chirish</button></form>@endif
    </div>
</div>
@endsection
