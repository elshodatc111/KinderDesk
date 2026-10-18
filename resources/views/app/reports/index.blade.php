@extends('layouts.app')
@section('title', 'Hisobotlar')
@section('actions')
@if(auth()->user()->is_owner)@feature('exports')<div class="flex gap-2"><a class="btn btn-sm" href="{{ route('app.reports.export', 'kids') }}">Bolalar (CSV)</a>@feature('finance')<a class="btn btn-sm" href="{{ route('app.reports.export', 'debtors') }}">Qarzdorlar (CSV)</a>@endfeature</div>@endfeature @endif
@endsection
@section('content')
<form method="get" class="mb-6 flex items-end gap-3"><div><label class="label">Oy</label><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input !w-48" onchange="this.form.submit()"></div></form>
<div class="grid lg:grid-cols-2 gap-6">
    <section class="card card-pad"><h2 class="section-title mb-3">Guruhlar bo'yicha bolalar</h2>
        <ul class="divide-y divide-line">@foreach($kids_by_group as $g)<li class="flex justify-between py-2 font-medium"><span>{{ $g->name }}</span><span>{{ $g->active_kid_links_count }}@if($g->capacity)<span class="text-ink-mute"> / {{ $g->capacity }}</span>@endif</span></li>@endforeach</ul></section>
    @isset($attendance)
    <section class="card card-pad"><h2 class="section-title mb-3">Davomad ({{ $month->format('m.Y') }})</h2>
        <table class="table"><thead><tr><th>Guruh</th><th>Keldi</th><th>Kelmadi</th><th>Kasal</th><th class="text-right">%</th></tr></thead><tbody>
        @foreach($attendance as $a)<tr><td class="font-semibold">{{ $a['name'] }}</td><td>{{ $a['present'] }}</td><td>{{ $a['absent'] }}</td><td>{{ $a['sick'] }}</td><td class="text-right font-semibold">{{ $a['percent'] !== null ? $a['percent'].'%' : '—' }}</td></tr>@endforeach
        </tbody></table></section>
    @endisset
    @isset($income_by_cat)
    <section class="card card-pad"><h2 class="section-title mb-3">Kirim ({{ $month->format('m.Y') }})</h2>
        <ul class="divide-y divide-line">@forelse($income_by_cat as $r)<li class="flex justify-between py-2 font-medium"><span>{{ kd_desc($r->cat) }}</span><span class="text-leaf-700">{{ money($r->s) }}</span></li>@empty<li class="py-3 text-ink-mute font-medium">Ma'lumot yo'q</li>@endforelse</ul></section>
    <section class="card card-pad"><h2 class="section-title mb-3">Chiqim ({{ $month->format('m.Y') }})</h2>
        <ul class="divide-y divide-line">@forelse($expense_by_cat as $r)<li class="flex justify-between py-2 font-medium"><span>{{ kd_desc($r->cat) }}</span><span class="text-danger">{{ money($r->s) }}</span></li>@empty<li class="py-3 text-ink-mute font-medium">Ma'lumot yo'q</li>@endforelse</ul></section>
    <section class="card card-pad lg:col-span-2"><h2 class="section-title mb-3">Qarzdorlar</h2>
        <div class="table-scroll"><table class="table"><thead><tr><th>Bola</th><th>Guruh</th><th>Telefon</th><th class="text-right">Qarz</th></tr></thead><tbody>
        @forelse($debtors as $k)<tr><td><a class="font-semibold hover:underline" href="{{ route('app.kids.show', $k) }}">{{ $k->full_name }}</a></td><td>{{ $k->currentLink?->group?->name }}</td><td>{{ format_phone($k->parent_phone) }}</td><td class="text-right font-semibold text-danger">{{ money(abs($k->balance)) }}</td></tr>@empty<tr><td colspan="4" class="py-4 text-center text-ink-mute font-medium">Qarzdor yo'q</td></tr>@endforelse
        </tbody></table></div></section>
    @endisset
</div>
@endsection
