@extends('layouts.app')
@section('title', __('Moliya'))
@section('content')
@php
    $accLabels = \App\Models\FinanceTransaction::accountLabels();
    $typeLabels = \App\Models\FinanceTransaction::typeLabels();
    $tabs = ['overview' => __('Umumiy'), 'handovers' => __('Kassadan kelgan pul').($handovers->count() ? ' ('.$handovers->count().')' : ''), 'accounts' => __('Hisob raqamlari'), 'subsidy' => __('Subsidiya'), 'journal' => __('Jurnal')];
    $hStatus = ['confirmed' => [__('Qabul qilindi'), 'badge-leaf'], 'rejected' => [__('Rad etildi'), 'badge-red']];
@endphp
<div x-data="{ modal: null, rejectId: null, recvId: null, recvAmt: '' }" x-init="window.addEventListener('kd:activity', e => { if (['finance','handover'].includes(e.detail?.kind)) location.reload() })" @keydown.escape.window="modal = null">

@if($handovers->isNotEmpty() && $tab !== 'handovers')
    @can('finance.handover')
    <div class="banner banner-warn mb-5"><x-icon name="wallet" /><p class="flex-1">{{ __("Kassadan topshirilgan pul tasdiqlashingizni kutmoqda") }}: <b>{{ money($handovers->sum('amount')) }} {{ __("so'm") }}</b></p><a href="{{ route('app.finance.index', ['tab' => 'handovers']) }}" class="btn btn-sm btn-accent">{{ __("Ko'rish") }}</a></div>
    @endcan
@endif

<div class="tabs mb-6">@foreach($tabs as $k => $l)<a href="{{ route('app.finance.index', ['tab' => $k]) }}" class="tab {{ $tab===$k?'is-active':'' }}">{{ $l }}</a>@endforeach</div>

@if ($tab === 'overview')
<div class="grid sm:grid-cols-3 gap-4">
    @foreach (['cash' => ['leaf','wallet'], 'card' => ['sky','cash'], 'bank' => ['grape','receipt']] as $acc => [$c, $ic])
        <div class="tile"><span class="tile-icon"><x-icon :name="$ic" /></span>
            <p class="tile-label">{{ $accLabels[$acc] }}</p><p class="tile-value {{ $balances[$acc] < 0 ? 'text-danger' : '' }}">{{ money($balances[$acc]) }} <span class="text-sm">{{ __("so'm") }}</span></p></div>
    @endforeach
</div>
<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
    <div class="card card-pad"><p class="tile-label">{{ __('Jami qoldiq (direktor hisobi)') }}</p><p class="text-2xl font-medium">{{ money(array_sum($balances)) }} {{ __("so'm") }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Davr') }}: {{ __('kassadan kelgan') }} ({{ $from->format('d.m') }}–{{ $to->format('d.m') }})</p><p class="text-2xl font-medium text-leaf-700">{{ money($fromDesk) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Boshqa daromad') }}</p><p class="text-2xl font-medium text-leaf-700">{{ money($otherIncome) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Davr chiqimi') }}</p><p class="text-2xl font-medium text-danger">{{ money($expense) }}</p></div>
</div>
<div class="grid sm:grid-cols-3 gap-4 mt-4">
    <div class="card card-pad"><p class="tile-label">{{ __("Qo'shimcha mablag' (davr)") }}</p><p class="text-xl font-medium text-leaf-700">{{ money($deposits) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Subsidiya tushumi (davr)') }}</p><p class="text-xl font-medium text-leaf-700">{{ money($subsidyIn) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Kassaga berilgan naqd (davr)') }}</p><p class="text-xl font-medium">{{ money($toDesk) }}</p></div>
</div>
@feature('desk')
<div class="card card-pad mt-4 flex flex-wrap items-center justify-between gap-3"><div><p class="tile-label">{{ __("Kassada hali topshirilmagan pul") }}</p><p class="text-xl font-medium">{{ money(array_sum($deskBalances)) }} {{ __("so'm") }}</p></div>
    @can('desk.view')<a href="{{ route('app.desk.index') }}" class="btn btn-sm">{{ __('Kassaga o\'tish') }}</a>@endcan</div>
@endfeature
<div class="flex flex-wrap gap-2 mt-6">
    @can('finance.expenses')
        <button class="btn btn-accent" @click="modal='expense'">{{ __('Xarajat kiritish') }}</button>
        <button class="btn" @click="modal='income'">{{ __('Daromad kiritish') }}</button>
        <button class="btn" @click="modal='deposit'">{{ __("Qo'shimcha mablag' kiritish") }}</button>
        <button class="btn" @click="modal='transfer'">{{ __("Hisoblar o'rtasida o'tkazma") }}</button>
    @endcan
    @feature('desk')@can('finance.handover')<button class="btn" @click="modal='fund'">{{ __('Kassaga naqd pul berish') }}</button>@endcan @endfeature
</div>

@elseif ($tab === 'handovers')
<div class="space-y-3 w-full">
@forelse ($handovers as $h)
    <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
        <div><p class="font-medium">{{ $h->creator?->name }} <span class="badge badge-sky">{{ $accLabels[$h->account] }}</span></p>
            <p class="text-sm font-semibold text-ink-soft">{{ $h->created_at->format('d.m.Y H:i') }} @if($h->note)· {{ $h->note }}@endif</p></div>
        <div class="flex items-center gap-3"><span class="text-xl font-medium">{{ money($h->amount) }}</span>
            @can('finance.handover')
            <form method="post" action="{{ route('app.finance.handover.confirm', $h) }}">@csrf<button class="btn btn-primary btn-sm">{{ __('Pulni qabul qildim') }}</button></form>
            <button class="btn btn-sm !text-danger" @click="rejectId = {{ $h->id }}; modal = 'reject'">{{ __('Rad etish') }}</button>
            @endcan</div>
    </div>
@empty<div class="empty"><x-icon name="check" class="size-10" /><p class="font-medium mt-2">{{ __("Tasdiq kutayotgan topshirish yo'q") }}</p></div>@endforelse
</div>
@if($handoverHistory->isNotEmpty())
<h2 class="section-title mt-8 mb-3">{{ __('Oxirgi topshirishlar') }}</h2>
<div class="card table-wrap w-full"><table class="table"><tbody>
@foreach($handoverHistory as $h)
    <tr><td class="whitespace-nowrap">{{ $h->created_at->format('d.m.Y') }}</td><td>{{ $h->creator?->name }}</td><td>{{ $accLabels[$h->account] }}</td><td class="text-right font-medium">{{ money($h->amount) }}</td><td><span class="badge {{ $hStatus[$h->status][1] ?? 'badge-gray' }}">{{ $hStatus[$h->status][0] ?? $h->status }}</span></td></tr>
@endforeach
</tbody></table></div>
@endif

@elseif ($tab === 'accounts')
<div class="grid lg:grid-cols-3 gap-5 w-full">
    <div class="lg:col-span-2 space-y-3">
        @forelse($finAccounts as $a)
        <div class="card card-pad flex flex-wrap items-center justify-between gap-3 {{ $a->is_active ? '' : 'opacity-60' }}">
            <div><p class="font-bold">{{ $a->title }} <span class="badge badge-sky">{{ $a->kind === 'card' ? __('Karta') : __('Bank hisobi') }}</span> @unless($a->is_active)<span class="badge badge-gray">{{ __("o'chirilgan") }}</span>@endunless</p>
                <p class="text-lg font-semibold tracking-wide">{{ $a->number }}</p>
                <p class="text-sm font-semibold text-ink-soft">{{ $a->holder }}@if($a->bank_name) · {{ $a->bank_name }}@endif</p></div>
            @can('finance.expenses')
            <div class="flex gap-2">
                <form method="post" action="{{ route('app.finance.accounts.toggle', $a) }}">@csrf<button class="btn btn-sm">{{ $a->is_active ? __("O'chirib qo'yish") : __('Yoqish') }}</button></form>
                <form method="post" action="{{ route('app.finance.accounts.destroy', $a) }}" onsubmit="return confirm('{{ __("Hisob raqami o'chirilsinmi?") }}')">@csrf @method('DELETE')<button class="btn btn-sm !text-danger">{{ __("O'chirish") }}</button></form>
            </div>
            @endcan
        </div>
        @empty<div class="empty"><x-icon name="wallet" class="size-10" /><p class="font-medium mt-2">{{ __("Hisob raqami hali kiritilmagan") }}</p></div>@endforelse
        <p class="help">{{ __("Bu yerda direktorning o'z karta va bank raqamlari saqlanadi. Pul harakati baribir «Naqd / Karta / Bank» bo'yicha yuritiladi.") }}</p>
    </div>
    @can('finance.expenses')
    <form method="post" action="{{ route('app.finance.accounts.store') }}" class="card card-pad space-y-3 self-start">@csrf
        <h3 class="font-bold">{{ __("Hisob raqami qo'shish") }}</h3>
        <div><label class="label">{{ __('Turi') }}</label><select name="kind" class="select"><option value="card">{{ __('Karta') }}</option><option value="bank">{{ __('Bank hisobi') }}</option></select></div>
        <div><label class="label">{{ __('Nomi') }}</label><input name="title" class="input" required maxlength="80" placeholder="{{ __('Masalan: Asosiy karta') }}"></div>
        <div><label class="label">{{ __('Raqami') }}</label><input name="number" class="input" required maxlength="40" inputmode="numeric"></div>
        <div><label class="label">{{ __('Egasi') }}</label><input name="holder" class="input" maxlength="120"></div>
        <div><label class="label">{{ __('Bank nomi') }}</label><input name="bank_name" class="input" maxlength="80"></div>
        <button class="btn btn-primary w-full">{{ __("Qo'shish") }}</button>
    </form>
    @endcan
</div>

@elseif ($tab === 'subsidy')
<div class="grid sm:grid-cols-3 gap-4 mb-5">
    <div class="card card-pad"><p class="tile-label">{{ __('Kutilayotgan subsidiya') }}</p><p class="text-2xl font-medium text-sun-700">{{ money($subsidyTotals['expected']) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Jami olingan') }}</p><p class="text-2xl font-medium text-leaf-700">{{ money($subsidyTotals['received']) }}</p></div>
    <div class="card card-pad"><p class="tile-label">{{ __('Shu oy olingan') }}</p><p class="text-2xl font-medium">{{ money($subsidyTotals['received_month']) }}</p></div>
</div>
<div class="grid lg:grid-cols-3 gap-5 w-full">
    <div class="lg:col-span-2 card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Davr') }}</th><th>{{ __('Nomi') }}</th><th class="text-right">{{ __('Kutilgan') }}</th><th class="text-right">{{ __('Olingan') }}</th><th></th></tr></thead><tbody>
    @forelse($subsidies as $sb)
        <tr><td class="whitespace-nowrap">{{ $sb->period->format('m.Y') }}</td>
            <td><p class="font-semibold">{{ $sb->title }}</p><p class="text-xs text-ink-soft">{{ $sb->source }}@if($sb->kids_count) · {{ $sb->kids_count }} {{ __('bola') }} × {{ money($sb->rate) }}@endif</p></td>
            <td class="text-right">{{ money($sb->expected) }}</td>
            <td class="text-right font-medium {{ $sb->status==='received' ? 'text-leaf-700' : '' }}">{{ $sb->status==='received' ? money($sb->received) : '—' }}</td>
            <td class="text-right whitespace-nowrap">
                @if($sb->status === 'expected')
                    @can('finance.expenses')
                    <button class="btn btn-sm btn-primary" @click="recvId={{ $sb->id }}; recvAmt='{{ $sb->expected }}'; modal='recv'">{{ __('Qabul qilindi') }}</button>
                    <form method="post" action="{{ route('app.finance.subsidy.cancel', $sb) }}" class="inline">@csrf<button class="btn btn-sm !text-danger">{{ __('Bekor') }}</button></form>
                    @endcan
                @else<span class="badge {{ $sb->status==='received' ? 'badge-leaf' : 'badge-gray' }}">{{ $sb->status==='received' ? __('Olindi').' '.$sb->received_on?->format('d.m') : __('Bekor qilingan') }}</span>@endif
            </td></tr>
    @empty<tr><td colspan="5"><div class="empty">{{ __("Subsidiya hisoblanmagan") }}</div></td></tr>@endforelse
    </tbody></table></div></div>
    @can('finance.expenses')
    <form method="post" action="{{ route('app.finance.subsidy.store') }}" class="card card-pad space-y-3 self-start" x-data="{ kids: {{ $activeKids }}, rate: '', get calc() { const r = parseInt(String(this.rate).replace(/\D/g,'')) || 0; return (parseInt(this.kids) || 0) * r } }">@csrf
        <h3 class="font-bold">{{ __('Subsidiya hisoblash') }}</h3>
        <div><label class="label">{{ __('Nomi') }}</label><input name="title" class="input" required maxlength="120" placeholder="{{ __('Masalan: Oylik subsidiya') }}"></div>
        <div><label class="label">{{ __('Manba (kim beradi)') }}</label><input name="source" class="input" maxlength="120"></div>
        <div><label class="label">{{ __('Oy') }}</label><input type="month" name="period" class="input" value="{{ today()->format('Y-m') }}" required></div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="label">{{ __('Bolalar soni') }}</label><input type="number" name="kids_count" x-model="kids" class="input" min="0"></div>
            <div><label class="label">{{ __("Bola boshiga (so'm)") }}</label><input name="rate" x-model="rate" class="input" inputmode="numeric"></div>
        </div>
        <p class="help">{{ __('Hisoblangan') }}: <b x-text="new Intl.NumberFormat('ru-RU').format(calc)"></b> {{ __("so'm") }}</p>
        <div><label class="label">{{ __("Yoki umumiy kutilgan summa (so'm)") }}</label><input name="expected" class="input" inputmode="numeric"></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="note" class="input" maxlength="255"></div>
        <button class="btn btn-primary w-full">{{ __('Hisoblash') }}</button>
    </form>
    @endcan
</div>

@else
<form method="get" class="flex flex-wrap items-end gap-3 mb-4"><input type="hidden" name="tab" value="journal">
    <div><label class="label">{{ __('Dan') }}</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input !w-40"></div>
    <div><label class="label">{{ __('Gacha') }}</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="input !w-40"></div>
    <select name="type" class="select !w-44"><option value="">{{ __('Barcha turlar') }}</option>@foreach($typeLabels as $k=>$l)@continue(in_array($k, ['kid_payment','kid_refund']))<option value="{{ $k }}" @selected(request('type')===$k)>{{ $l }}</option>@endforeach</select>
    <select name="account" class="select !w-40"><option value="">{{ __('Barcha hisoblar') }}</option>@foreach($accLabels as $k=>$l)<option value="{{ $k }}" @selected(request('account')===$k)>{{ $l }}</option>@endforeach</select>
    <button class="btn">{{ __("Ko'rsatish") }}</button>
    @if(auth()->user()->is_owner)@feature('exports')<a class="btn" href="{{ route('app.reports.export', ['what' => 'finance', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Excel (CSV)</a>@endfeature @endif
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Turi') }}</th><th>{{ __('Izoh') }}</th><th>{{ __('Hisob') }}</th><th class="text-right">{{ __('Summa') }}</th></tr></thead><tbody>
@forelse ($items as $t)
    <tr><td class="whitespace-nowrap">{{ $t->occurred_on->format('d.m.Y') }}</td>
        <td>{{ $t->typeLabel() }}</td>
        <td class="text-ink-soft">{{ $t->category ? kd_desc($t->category).' · ' : '' }}{{ kd_desc($t->description) }}</td>
        <td>{{ $accLabels[$t->account] }}</td><td class="text-right font-medium {{ $t->amount<0?'text-danger':'text-leaf-700' }}">{{ $t->amount>0?'+':'−' }}{{ money(abs($t->amount)) }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">{{ __('Yozuv topilmadi') }}</div></td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $items instanceof \Illuminate\Pagination\LengthAwarePaginator ? $items->links() : '' }}</div>
@endif

@can('finance.expenses')
@foreach (['expense' => __('Xarajat kiritish'), 'income' => __('Daromad kiritish'), 'deposit' => __("Qo'shimcha mablag' kiritish")] as $type => $title)
<div x-show="modal==='{{ $type }}'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" action="{{ route('app.finance.store') }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf<input type="hidden" name="type" value="{{ $type }}">
        <h3 class="text-xl font-medium">{{ $title }}</h3>
        <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)" required></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label">{{ __('Hisob') }}</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Sana') }}</label><input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input"></div></div>
        <div><label class="label">{{ __('Toifa') }}</label><input name="category" list="cats-{{ $type }}" class="input" placeholder="{{ __('Masalan: Oziq-ovqat') }}">
            <datalist id="cats-{{ $type }}">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="description" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __('Saqlash') }}</button></div>
    </form>
</div>
@endforeach
<div x-show="modal==='fund'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" action="{{ route('app.finance.fund-desk') }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
        <h3 class="text-xl font-medium">{{ __('Kassaga naqd pul berish') }}</h3>
        <p class="help">{{ __("Naqd hisobdan kassaga o'tadi. Kassa xarajatlari faqat shu naqd pul hisobidan qilinadi.") }} {{ __('Moliyada naqd') }}: <b>{{ money($balances['cash']) }}</b></p>
        <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)" required></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="note" class="input" maxlength="150"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __('Berish') }}</button></div>
    </form>
</div>
<div x-show="modal==='recv'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" :action="'{{ url('/app/moliya/subsidiya') }}/' + recvId + '/qabul'" class="modal-panel space-y-4">@csrf
        <h3 class="text-xl font-medium">{{ __('Subsidiya qabul qilindi') }}</h3>
        <div><label class="label">{{ __("Olingan summa (so'm)") }}</label><input name="amount" x-model="recvAmt" inputmode="numeric" class="input text-xl font-medium" required></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label">{{ __('Qaysi hisobga') }}</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}" @selected($a==='bank')>{{ $accLabels[$a] }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Sana') }}</label><input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input"></div></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __('Kirim qilish') }}</button></div>
    </form>
</div>
<div x-show="modal==='transfer'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" action="{{ route('app.finance.transfer') }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
        <h3 class="text-xl font-medium">{{ __("Hisoblar o'rtasida o'tkazma") }}</h3>
        <div class="grid grid-cols-2 gap-3"><div><label class="label">{{ __('Qaysi hisobdan') }}</label><select name="from" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }} ({{ money($balances[$a]) }})</option>@endforeach</select></div>
            <div><label class="label">{{ __('Qaysi hisobga') }}</label><select name="to" class="select">@foreach($accounts as $a)<option value="{{ $a }}" @selected($loop->index===1)>{{ $accLabels[$a] }}</option>@endforeach</select></div></div>
        <div><label class="label">{{ __("Summa (so'm)") }}</label><input name="amount" inputmode="numeric" class="input text-xl font-medium" :value="shown" @input="onInput($event)" required></div>
        <div><label class="label">{{ __('Izoh') }}</label><input name="description" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-primary">{{ __("O'tkazish") }}</button></div>
    </form>
</div>
@endcan
@can('finance.handover')
<div x-show="modal==='reject'" x-cloak class="modal-backdrop" @click.self="modal=null">
    <form method="post" :action="'{{ url('/app/moliya/topshirish') }}/' + rejectId + '/rad'" class="modal-panel space-y-4">@csrf
        <h3 class="text-xl font-medium">{{ __('Topshirishni rad etish') }}</h3>
        <div><label class="label">{{ __('Sabab') }}</label><input name="reason" class="input" required></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=null">{{ __('Bekor qilish') }}</button><button class="btn btn-danger">{{ __('Rad etish') }}</button></div>
    </form>
</div>
@endcan
</div>
@endsection
