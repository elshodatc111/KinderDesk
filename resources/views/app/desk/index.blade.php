@extends('layouts.app')
@section('title', __('Kassa'))
@section('content')
@php
    $accLabels = \App\Models\FinanceTransaction::accountLabels();
    $typeLabels = \App\Models\FinanceTransaction::typeLabels();
    $tabs = ['today' => __('Bugun'), 'pending' => __('Tasdiq kutayotgan').' ('.$pending->count().')', 'handovers' => __('Topshirishlar'), 'journal' => __('Jurnal')];
    $hStatus = ['pending' => [__('Kutilmoqda'), 'badge-orange'], 'confirmed' => [__('Qabul qilindi'), 'badge-leaf'], 'rejected' => [__('Rad etildi'), 'badge-red']];
    $shown = collect($accounts)->merge(['cash'])->unique()->values();
@endphp
<div x-data="{ modal: null, picked: null, q: '', found: [], async search() { if (this.q.length < 2) { this.found = []; return; } const r = await fetch('{{ route('app.desk.kids') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.found = await r.json(); } }"
     x-init="window.addEventListener('kd:activity', e => { if (['finance','handover','kid'].includes(e.detail?.kind)) location.reload() })" @keydown.escape.window="modal = null">

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="tile tile-accent"><span class="tile-icon"><x-icon name="wallet" /></span><p class="tile-label">{{ __("Kassadagi pul") }}</p><p class="tile-value">{{ money(array_sum($balances)) }} <span class="text-sm">{{ __("so'm") }}</span></p>
        <p class="text-xs font-semibold text-ink-soft mt-1">@foreach($shown as $a){{ $accLabels[$a] }}: {{ money($balances[$a]) }}@if(!$loop->last) · @endif @endforeach</p></div>
    <div class="tile"><span class="tile-icon"><x-icon name="cash" /></span><p class="tile-label">{{ __("Bugungi tushum") }}</p><p class="tile-value text-leaf-700">{{ money($todayIn) }} <span class="text-sm">{{ __("so'm") }}</span></p></div>
    <div class="tile"><span class="tile-icon"><x-icon name="clock" /></span><p class="tile-label">{{ __("Topshirish kutilmoqda") }}</p><p class="tile-value">{{ money(array_sum($held->all())) }} <span class="text-sm">{{ __("so'm") }}</span></p><p class="text-xs font-semibold text-ink-soft mt-1">{{ __('Direktor tasdiqlashi kerak') }}</p></div>
    <div class="tile"><span class="tile-icon"><x-icon name="check" /></span><p class="tile-label">{{ __("Tasdiq kutayotgan to'lovlar") }}</p><p class="tile-value">{{ $pending->count() }}</p><p class="text-xs font-semibold text-ink-soft mt-1">{{ __('Karta / bank orqali') }}</p></div>
</div>

<div class="flex flex-wrap gap-2 mt-6">
    @can('finance.payments')<button class="btn btn-accent" @click="modal='pay'; picked=null; q=''; found=[]"><x-icon name="plus" /> {{ __("To'lov qabul qilish") }}</button>@endcan
    @can('desk.handover')<button class="btn" @click="modal='handover'">{{ __('Direktorga topshirish') }}</button>@endcan
    @can('finance.expenses')<button class="btn" @click="modal='expense'">{{ __('Kassadan xarajat') }}</button>@endcan
    @can('finance.payments')
        <form method="post" action="{{ route('app.desk.charge') }}" onsubmit="return confirm(@js(__("Joriy davr uchun barcha bolalarga oylik to'lov hisoblanadi (oldin hisoblangan bo'lsa takrorlanmaydi). Davom etasizmi?")))">@csrf
            <button class="btn"><x-icon name="refresh" /> {{ __("Oylik to'lovni hisoblash") }}</button></form>
    @endcan
</div>

<div class="tabs mt-7 mb-5">@foreach($tabs as $k => $l)<a href="{{ route('app.desk.index', ['tab' => $k]) }}" class="tab {{ $tab===$k?'is-active':'' }}">{{ $l }}</a>@endforeach</div>

@if ($tab === 'today')
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Vaqt') }}</th><th>{{ __('Kim') }}</th><th>{{ __('Hisob') }}</th><th class="text-right">{{ __('Summa') }}</th></tr></thead><tbody>
@forelse ($todayList as $t)
    <tr><td class="whitespace-nowrap">{{ $t->created_at->format('H:i') }}</td>
        <td>@if($t->kid)<a class="font-semibold hover:underline" href="{{ route('app.kids.show', $t->kid) }}">{{ $t->kid->full_name }}</a>@else{{ $t->typeLabel() }}@endif
            <span class="block text-xs text-ink-soft">{{ $t->creator?->name }} {{ $t->description ? '· '.$t->description : '' }} @if($t->status==='pending')<span class="badge badge-orange">{{ __('Kutilmoqda') }}</span>@endif</span></td>
        <td>{{ $accLabels[$t->account] }}</td><td class="text-right font-medium {{ $t->amount<0?'text-danger':'text-leaf-700' }}">{{ $t->amount>0?'+':'−' }}{{ money(abs($t->amount)) }}</td></tr>
@empty<tr><td colspan="4"><div class="empty">{{ __("Bugun hali pul harakati yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>

@elseif ($tab === 'pending')
<div class="space-y-3 w-full">
@forelse ($pending as $p)
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
        <div><p class="font-medium">{{ $p->kid?->full_name }} <span class="badge badge-sky">{{ $accLabels[$p->account] }}</span></p>
            <p class="text-sm font-semibold text-ink-soft">{{ $p->created_at->format('d.m.Y H:i') }} · {{ $p->creator?->name }} @if($p->description)· {{ $p->description }}@endif</p></div>
        <div class="flex items-center gap-3"><span class="text-xl font-medium">{{ money($p->amount) }}</span>
            @can('finance.confirm')
            <form method="post" action="{{ route('app.desk.confirm', $p) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('Tasdiqlash') }}</button></form>
            <form method="post" action="{{ route('app.desk.cancel', $p) }}" onsubmit="return confirm(@js(__("To'lov bekor qilinadi. Davom etasizmi?")))">@csrf<button class="btn btn-sm !text-danger">{{ __('Bekor qilish') }}</button></form>
            @endcan</div>
    </div>
@empty<div class="empty"><x-icon name="check" class="size-10" /><p class="font-medium mt-2">{{ __("Tasdiq kutayotgan to'lov yo'q") }}</p></div>@endforelse
</div>

@elseif ($tab === 'handovers')
<div class="card table-wrap w-full"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Kim topshirdi') }}</th><th>{{ __('Hisob') }}</th><th class="text-right">{{ __('Summa') }}</th><th>{{ __('Holat') }}</th></tr></thead><tbody>
@forelse ($handovers as $h)
    <tr><td class="whitespace-nowrap">{{ $h->created_at->format('d.m.Y H:i') }}</td><td>{{ $h->creator?->name }}@if($h->note)<span class="block text-xs text-ink-soft">{{ $h->note }}</span>@endif</td>
        <td>{{ $accLabels[$h->account] }}</td><td class="text-right font-medium">{{ money($h->amount) }}</td>
        <td><span class="badge {{ $hStatus[$h->status][1] }}">{{ $hStatus[$h->status][0] }}</span>@if($h->status==='rejected' && $h->reject_reason)<span class="block text-xs text-danger mt-1">{{ $h->reject_reason }}</span>@endif</td></tr>
@empty<tr><td colspan="5"><div class="empty">{{ __("Hali topshirish bo'lmagan") }}</div></td></tr>@endforelse
</tbody></table></div></div>

@else
<form method="get" class="flex flex-wrap items-end gap-3 mb-4"><input type="hidden" name="tab" value="journal">
    <div><label class="label">{{ __('Dan') }}</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input !w-40"></div>
    <div><label class="label">{{ __('Gacha') }}</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input !w-40"></div>
    <button class="btn">{{ __("Ko'rsatish") }}</button>
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Turi') }}</th><th>{{ __('Izoh') }}</th><th>{{ __('Hisob') }}</th><th class="text-right">{{ __('Summa') }}</th></tr></thead><tbody>
@forelse ($journal as $t)
    <tr><td class="whitespace-nowrap">{{ $t->occurred_on->format('d.m.Y') }}</td><td>{{ $t->typeLabel() }} @if($t->status==='pending')<span class="badge badge-orange">{{ __('Kutilmoqda') }}</span>@endif</td>
        <td class="text-ink-soft">@if($t->kid)<a class="underline" href="{{ route('app.kids.show', $t->kid) }}">{{ $t->kid->full_name }}</a> @endif{{ $t->description }}</td>
        <td>{{ $accLabels[$t->account] }}</td><td class="text-right font-medium {{ $t->amount<0?'text-danger':'text-leaf-700' }}">{{ $t->amount>0?'+':'−' }}{{ money(abs($t->amount)) }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">{{ __('Yozuv topilmadi') }}</div></td></tr>@endforelse
</tbody></table></div></div>
@if($journal instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="mt-4">{{ $journal->links() }}</div>@endif
@endif

{{-- To'lov qabul qilish --}}
@can('finance.payments')
<div x-show="modal==='pay'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" :action="picked ? picked.url : '#'" class="modal-panel space-y-4" x-data="moneyInput()" @submit="if(!picked) $event.preventDefault()">@csrf
        <h3 class="text-xl font-medium">{{ __("To'lov qabul qilish") }}</h3>
        <div x-show="!picked">
            <label class="label">{{ __('Bolani toping (ism yoki ota-ona telefoni)') }}</label>
            <input class="input" x-model="q" @input.debounce.250ms="search()" placeholder="{{ __('Kamida 2 ta belgi') }}" autocomplete="off">
            <div class="mt-2 divide-y divide-line rounded-xl border border-line overflow-hidden" x-show="found.length">
                <template x-for="k in found" :key="k.id"><button type="button" class="w-full text-left px-3 py-2 hover:bg-cloud" @click="picked = k; raw = k.balance < 0 ? String(Math.abs(k.balance)) : ''">
                    <span class="font-semibold" x-text="k.name"></span><span class="block text-xs text-ink-soft"><span x-text="k.group || ''"></span> · <span x-text="k.phone"></span> · <span :class="k.balance < 0 ? 'text-danger font-medium' : ''" x-text="(k.balance < 0 ? '−' : '') + Math.abs(k.balance).toLocaleString('ru-RU')"></span></span></button></template>
            </div>
        </div>
        <template x-if="picked"><div class="flex items-center justify-between rounded-xl bg-teal-50 px-3 py-2"><div><p class="font-medium" x-text="picked.name"></p><p class="text-xs text-ink-soft" x-text="picked.group"></p></div><button type="button" class="btn-quiet" @click="picked=null">{{ __("O'zgartirish") }}</button></div></template>
        <div x-show="picked" class="space-y-4">
            <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)"></div>
            <div><label class="label">{{ __("To'lov usuli") }}</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Izoh') }}</label><input name="description" class="input"></div>
        </div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary" :disabled="!picked">{{ __('Qabul qilish') }}</button></div>
    </form>
</div>
@endcan

@can('desk.handover')
<div x-show="modal==='handover'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" action="{{ route('app.desk.handover') }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
        <h3 class="text-xl font-medium">{{ __('Direktorga topshirish') }}</h3>
        <p class="help">{{ __("Pul direktor qabul qilganini tasdiqlagandan keyin kassadan chiqib, moliyaga o'tadi. Shu vaqtgacha u kassada hisoblanadi.") }}</p>
        <div><label class="label">{{ __('Hisob') }}</label><select name="account" class="select">@foreach(['cash','card','bank'] as $a)@continue(($available[$a] ?? 0) <= 0 && $a !== 'cash')<option value="{{ $a }}">{{ $accLabels[$a] }} ({{ __('mavjud') }}: {{ money(max(0,$available[$a])) }})</option>@endforeach</select></div>
        <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)" required></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="note" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __('Topshirish') }}</button></div>
    </form>
</div>
@endcan

@can('finance.expenses')
<div x-show="modal==='expense'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" action="{{ route('app.desk.expense') }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
        <h3 class="text-xl font-medium">{{ __('Kassadan xarajat') }}</h3>
        <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)" required></div>
        <div class="banner banner-info"><x-icon name="wallet" /><p>{{ __("Kassadan xarajat faqat naqd pul bilan qilinadi. Naqd qoldiq") }}: <b>{{ money($balances['cash']) }} {{ __("so'm") }}</b>. {{ __("Karta va bank tushumlarini direktorga topshiring.") }}</p></div>
        <div><label class="label">{{ __('Toifa') }}</label><input name="category" class="input" placeholder="{{ __('Masalan: Kantselyariya') }}"></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="description" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __('Saqlash') }}</button></div>
    </form>
</div>
@endcan
</div>
@endsection
