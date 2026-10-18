@extends('layouts.app')
@section('title', 'Obuna va balans')
@section('content')
@php
    $state = $tenant->accessState();
    $statusMap = ['trial' => 'Bepul sinov', 'active' => 'Obuna faol', 'lifetime' => 'Umrbod', 'read_only' => "Faqat ko'rish", 'suspended' => 'Bloklangan'];
    $topupStatus = ['pending' => ['Kutilmoqda', 'badge-orange'], 'approved' => ['Tasdiqlandi', 'badge-leaf'], 'rejected' => ['Rad etildi', 'badge-red']];
@endphp
<div class="grid lg:grid-cols-3 gap-5">
    <div class="tile" style="--tile-bg:var(--color-teal-50);--tile-accent:var(--color-teal-500)">
        <span class="tile-icon"><x-icon name="sparkles" /></span>
        <p class="tile-label">Holat</p>
        <p class="tile-value">{{ $statusMap[$state] ?? $state }}</p>
        <p class="text-sm font-medium text-ink-soft mt-1">
            @if ($tenant->lifetime) Muddatsiz — tarif to'lovi olinmaydi
            @elseif ($state === 'read_only') {{ $tenant->daysSinceExpiry() }} kundan beri tugagan
            @else {{ $tenant->access_expires_at?->format('d.m.Y') }} gacha · {{ max(0, $tenant->daysLeft()) }} kun qoldi @endif
        </p>
    </div>
    <div class="tile" style="--tile-bg:var(--color-marigold-50);--tile-accent:var(--color-marigold-500)">
        <span class="tile-icon"><x-icon name="wallet" /></span>
        <p class="tile-label">Balans</p>
        <p class="tile-value" data-live="balance">{{ money($tenant->balance) }} <span class="text-base">so'm</span></p>
        <p class="text-sm font-medium text-ink-soft mt-1">Tarifni sotib olganingizda shu balansdan yechiladi</p>
    </div>
    <div class="tile" style="--tile-bg:var(--color-sun-50);--tile-accent:var(--color-sun-500)">
        <span class="tile-icon"><x-icon name="info" /></span>
        <p class="tile-label">Obuna qanday yangilanadi</p>
        <p class="text-sm font-medium text-ink-soft mt-2">Obuna o'zi yechilmaydi. Muddat tugasa tizim «faqat ko'rish» rejimiga o'tadi. Davom etish uchun balansni to'ldirib, quyidan tarifni o'zingiz tanlang.</p>
    </div>
</div>

{{-- ====== Balansni to'ldirish ====== --}}
<section class="mt-8" x-data="{ amount: '', method: '{{ $paymeEnabled ? 'payme' : 'card' }}' }">
    <h2 class="section-title mb-3">Balansni to'ldirish</h2>
    <div class="card card-pad">
        <div class="tabs mb-5">
            @if ($paymeEnabled)<button type="button" class="tab" :class="method==='payme' && 'is-active'" @click="method='payme'">Payme</button>@endif
            <button type="button" class="tab" :class="method==='card' && 'is-active'" @click="method='card'">Karta orqali (chek bilan)</button>
        </div>

        @if ($paymeEnabled)
        <form method="post" action="{{ route('app.subscription.topup.payme') }}" x-show="method==='payme'" class="grid sm:grid-cols-[1fr_auto] gap-3 max-w-xl">@csrf
            <div><label class="label">Summa (so'm)</label><input name="amount" inputmode="numeric" class="input" placeholder="{{ money($minTopup) }}" required></div>
            <button class="btn btn-primary self-end">Payme orqali to'lash</button>
        </form>
        @endif

        <div x-show="method==='card'" @if($paymeEnabled) x-cloak @endif class="grid lg:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-line p-5 bg-teal-600 text-white">
                @if ($card['number'])
                    <p class="text-sm font-medium text-white/70">Karta raqami</p>
                    <p class="text-2xl font-semibold tracking-wider mt-1" x-data="{ok:false}">{{ trim(chunk_split(preg_replace('/\D/', '', $card['number']), 4, ' ')) }}</p>
                    <p class="mt-3 font-semibold">{{ $card['holder'] }}</p>
                    <p class="text-sm font-medium text-white/70">{{ $card['bank'] }}</p>
                    <button type="button" class="btn btn-sm mt-4 !text-ink" x-data @click="navigator.clipboard.writeText('{{ preg_replace('/\D/', '', $card['number']) }}'); $store.toasts.add({title:'Karta raqami nusxalandi', level:'success'})">Raqamni nusxalash</button>
                @else
                    <p class="font-semibold">Karta raqami hali kiritilmagan. Qo'llab-quvvatlashga murojaat qiling.</p>
                @endif
                <p class="text-sm font-semibold text-white/80 mt-4">{{ $card['note'] }}</p>
            </div>
            <form method="post" action="{{ route('app.subscription.topup.manual') }}" enctype="multipart/form-data" class="space-y-3">@csrf
                <div><label class="label">O'tkazilgan summa (so'm)</label><input name="amount" value="{{ old('amount') }}" inputmode="numeric" class="input" required>
                    <p class="help">Eng kami: {{ money($minTopup) }} so'm. Tasdiqlangach shu summaning o'zi balansga tushadi.</p>@error('amount')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label class="label">To'lov cheki (rasm yoki PDF)</label><input type="file" name="receipt" accept="image/*,.pdf" class="input" required>@error('receipt')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">To'lov sanasi</label><input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="input"></div>
                    <div><label class="label">Karta (oxirgi 4 raqam)</label><input name="sender_card" maxlength="30" class="input" value="{{ old('sender_card') }}"></div>
                </div>
                <div><label class="label">Izoh</label><input name="note" class="input" value="{{ old('note') }}"></div>
                <button class="btn btn-primary">Chekni yuborish</button>
            </form>
        </div>
    </div>

    @if ($topups->isNotEmpty())
    <div class="card mt-4 table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>Sana</th><th>Usul</th><th class="text-right">Summa</th><th>Holat</th><th></th></tr></thead>
        <tbody>
        @foreach ($topups as $t)
            <tr><td>{{ $t->created_at->format('d.m.Y H:i') }}</td><td>{{ $t->method === 'payme' ? 'Payme' : 'Karta + chek' }}</td>
                <td class="text-right font-semibold">{{ money($t->amount) }}</td>
                <td><span class="badge {{ $topupStatus[$t->status][1] ?? 'badge-gray' }}">{{ $topupStatus[$t->status][0] ?? $t->status }}</span>
                    @if ($t->status==='rejected' && $t->reject_reason)<span class="block text-xs font-semibold text-ink-soft">{{ $t->reject_reason }}</span>@endif</td>
                <td>@if ($t->receipt_path)<a class="btn-quiet" target="_blank" href="{{ route('app.subscription.receipt', $t) }}">Chekni ko'rish</a>@endif</td></tr>
        @endforeach
        </tbody></table></div></div>
    @endif
</section>

{{-- ====== Tariflar ====== --}}
<section class="mt-10" x-data="{ months: 3 }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="section-title">Obuna sotib olish</h2>
        <div class="tabs">
            @foreach ([1 => '1 oy', 3 => '3 oy', 12 => '1 yil'] as $m => $l)
                <button type="button" class="tab" :class="months === {{ $m }} && 'is-active'" @click="months = {{ $m }}">{{ $l }}</button>
            @endforeach
        </div>
    </div>
    <div class="grid lg:grid-cols-3 gap-5">
        @foreach ($editions as $ed)
            <div class="card-sticker p-5 flex flex-col {{ $ed->id === $tenant->edition_id ? 'ring-4 ring-teal-200' : '' }}">
                <div class="flex items-center justify-between"><h3 class="text-xl font-semibold">{{ $ed->name }}</h3>
                    @if ($ed->id === $tenant->edition_id)<span class="badge">Joriy nashr</span>@endif</div>
                <p class="text-sm font-semibold text-ink-soft">{{ $ed->tagline }}</p>
                @foreach ($ed->plans as $plan)
                    <form method="post" action="{{ route('app.subscription.buy') }}" x-show="months === {{ $plan->months }}" @if(!$loop->first) x-cloak @endif class="mt-4">@csrf
                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                        <p class="text-3xl font-semibold">{{ money($plan->price) }} <span class="text-sm text-ink-mute">so'm</span></p>
                        <p class="text-sm font-medium text-ink-soft">{{ $plan->label() }}@if($plan->months>1) · oyiga {{ money($plan->monthlyEquivalent()) }}@endif</p>
                        <button class="btn {{ $tenant->balance >= $plan->price ? 'btn-accent' : '' }} btn-block mt-3"
                                data-confirm="{{ __(':ed — :plan: :sum so\'m balansdan yechiladi. Davom etasizmi?', ['ed' => $ed->name, 'plan' => __($plan->label()), 'sum' => money($plan->price)]) }}">Balansdan sotib olish</button>
                        @if ($tenant->balance < $plan->price)<p class="help mt-1">{{ __("Balansda yetarli mablag' yo'q: yana :sum so'm kerak", ['sum' => money($plan->price - $tenant->balance)]) }}</p>@endif
                    </form>
                @endforeach
                @include('partials.plan-features')
            </div>
        @endforeach
    </div>
</section>

{{-- ====== Tarix ====== --}}
<section class="mt-10 grid lg:grid-cols-2 gap-5">
    <div><h2 class="section-title mb-3">Obunalar tarixi</h2>
        <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Davr</th><th>Nashr</th><th class="text-right">Narx</th></tr></thead><tbody>
            @forelse ($history as $s)<tr><td>{{ $s->starts_on->format('d.m.Y') }} — {{ $s->ends_on->format('d.m.Y') }}</td><td>{{ $s->edition?->name }}</td><td class="text-right font-semibold">{{ $s->price ? money($s->price) : 'Sovg\'a' }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-ink-mute font-medium py-6">Hali obuna sotib olinmagan</td></tr>@endforelse
        </tbody></table></div></div></div>
    <div><h2 class="section-title mb-3">Balans harakati</h2>
        <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Sana</th><th>Izoh</th><th class="text-right">Summa</th></tr></thead><tbody>
            @forelse ($transactions as $w)<tr><td class="whitespace-nowrap">{{ $w->created_at->format('d.m.Y') }}</td><td>{{ $w->description }}</td><td class="text-right font-semibold {{ $w->amount < 0 ? 'text-danger' : 'text-leaf-700' }}">{{ $w->amount > 0 ? '+' : '' }}{{ money($w->amount) }}</td></tr>
            @empty<tr><td colspan="3" class="text-center text-ink-mute font-medium py-6">Harakat yo'q</td></tr>@endforelse
        </tbody></table></div></div></div>
</section>
@endsection
@push('scripts')
<script>
    // data-confirm: tugma bosilganda tasdiq so'raydi
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-confirm]');
        if (b && !confirm(b.dataset.confirm)) { e.preventDefault(); }
    });
    window.addEventListener('kd:activity', (e) => { if (['subscription','topup'].includes(e.detail?.kind)) setTimeout(() => location.reload(), 600); });
</script>
@endpush
