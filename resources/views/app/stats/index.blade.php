@extends('layouts.app')
@section('title', __('Statistika'))
@section('content')
@php
    $k = $data['kpi'];
    $periods = ['day' => __('Kunlar'), 'week' => __('Haftalar'), 'month' => __('Oylar'), 'year' => __('Yillar')];
    $span = ['day' => __('Oxirgi 30 kun'), 'week' => __('Oxirgi 12 hafta'), 'month' => __('Oxirgi 12 oy'), 'year' => __('Oxirgi 5 yil')][$period];
    $tr = [
        'present' => __('Keldi'), 'absent' => __('Kelmadi'), 'sick' => __('Sababli'), 'rate' => __('Davomad, %'),
        'income' => __('Kirim'), 'expense' => __('Chiqim'), 'net' => __('Sof natija'),
        'new' => __('Yangi'), 'left' => __('Ketgan'), 'kids' => __('Bolalar'), 'capacity' => __('Sig\'im'),
        'late' => __('Kechikdi'), 'leave' => __("Ta'til"), 'won' => __('Qabul qilingan'), 'leads' => __('Arizalar'), 'subsidy' => __('Subsidiya'),
    ];
    $fmt = fn ($v) => number_format((int) $v, 0, '.', ' ');
    $payload = ['d' => $data, 't' => $tr];
@endphp
<div class="flex flex-wrap items-center gap-3 mb-5">
    <div class="seg" role="tablist">
        @foreach($periods as $key => $label)<a href="{{ route('app.stats.index', ['period' => $key]) }}" class="{{ $period === $key ? 'is-on' : '' }}" @if($period === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </div>
    <p class="text-sm font-semibold text-ink-soft">{{ $span }} · {{ \Illuminate\Support\Carbon::parse($data['from'])->format('d.m.Y') }} – {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d.m.Y') }}</p>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    <div class="tile"><span class="tile-icon"><x-icon name="attendance" /></span><p class="tile-label">{{ __("O'rtacha davomad") }}</p><p class="tile-value">{{ $k['attendance'] === null ? '—' : $k['attendance'].'%' }}</p></div>
    <div class="tile"><span class="tile-icon"><x-icon name="kids" /></span><p class="tile-label">{{ __('Yangi / ketgan bolalar') }}</p><p class="tile-value"><span class="text-leaf-700">+{{ $k['new_kids'] }}</span> / <span class="text-berry-700">−{{ $k['left_kids'] }}</span></p></div>
    @isset($data['finance'])
    <div class="tile"><span class="tile-icon"><x-icon name="cash" /></span><p class="tile-label">{{ __('Kirim') }}</p><p class="tile-value text-leaf-700">{{ $fmt($k['income']) }}</p></div>
    <div class="tile"><span class="tile-icon"><x-icon name="wallet" /></span><p class="tile-label">{{ __('Chiqim') }} · {{ __('Sof natija') }}</p><p class="tile-value text-danger text-xl">{{ $fmt($k['expense']) }} <span class="{{ $k['net'] >= 0 ? 'text-leaf-700' : 'text-danger' }}">· {{ $fmt($k['net']) }}</span></p></div>
    @endisset
</div>

<div x-data="statsPage(@js($payload))" class="grid gap-5 lg:grid-cols-2">
    <section class="card card-pad lg:col-span-2"><h2 class="font-display font-bold mb-3">{{ __('Bolalar davomadi') }}</h2>
        <div class="grid lg:grid-cols-2 gap-4"><div class="h-64"><canvas x-ref="attRate" aria-label="{{ __('Davomad foizi') }}" role="img"></canvas></div><div class="h-64"><canvas x-ref="attCount" aria-label="{{ __('Davomad soni') }}" role="img"></canvas></div></div></section>

    @isset($data['finance'])
    <section class="card card-pad lg:col-span-2"><h2 class="font-display font-bold mb-3">{{ __('Kirim va chiqim') }}</h2><div class="h-72"><canvas x-ref="fin" role="img" aria-label="{{ __('Kirim va chiqim') }}"></canvas></div></section>
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __("To'lov usullari") }}</h2>
        @if(array_sum($data['finance']['methods']['values']))<div class="h-64"><canvas x-ref="methods" role="img" aria-label="{{ __("To'lov usullari") }}"></canvas></div>@else<p class="empty">{{ __("Ma'lumot yo'q") }}</p>@endif</section>
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __('Xarajat toifalari') }}</h2>
        @if(array_sum($data['finance']['categories']['values']))<div class="h-64"><canvas x-ref="cats" role="img" aria-label="{{ __('Xarajat toifalari') }}"></canvas></div>@else<p class="empty">{{ __("Ma'lumot yo'q") }}</p>@endif</section>
    @endisset

    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __('Yangi va ketgan bolalar') }}</h2><div class="h-64"><canvas x-ref="kids" role="img" aria-label="{{ __('Yangi va ketgan bolalar') }}"></canvas></div></section>
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __("Guruhlar to'lishi") }}</h2>
        @if(count($data['groups']['labels']))<div class="h-64"><canvas x-ref="groups" role="img" aria-label="{{ __("Guruhlar to'lishi") }}"></canvas></div>@else<p class="empty">{{ __("Ma'lumot yo'q") }}</p>@endif</section>

    @isset($data['staff'])
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __('Xodimlar davomadi') }}</h2><div class="h-64"><canvas x-ref="staff" role="img" aria-label="{{ __('Xodimlar davomadi') }}"></canvas></div></section>
    @endisset
    @isset($data['leads'])
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __('Arizalar') }}</h2><div class="h-64"><canvas x-ref="leads" role="img" aria-label="{{ __('Arizalar') }}"></canvas></div></section>
    @endisset
    @isset($data['subsidy'])
    <section class="card card-pad"><h2 class="font-display font-bold mb-3">{{ __('Subsidiya tushumi') }}</h2><div class="h-64"><canvas x-ref="subsidy" role="img" aria-label="{{ __('Subsidiya tushumi') }}"></canvas></div>
        <p class="help mt-2">{{ __('Kutilayotgan') }}: <b>{{ $fmt($data['subsidy']['expected']) }}</b> {{ __("so'm") }}</p></section>
    @endisset
    @isset($data['debt'])
    <section class="card card-pad flex flex-col justify-center"><h2 class="font-display font-bold mb-3">{{ __('Qarzdorlik (hozir)') }}</h2>
        <p class="text-3xl font-bold text-danger">{{ $fmt($data['debt']['amount']) }} <span class="text-base">{{ __("so'm") }}</span></p>
        <p class="help mt-1">{{ $data['debt']['kids'] }} {{ __('ta bola') }}</p></section>
    @endisset
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('statsPage', ({ d, t }) => ({
        init() {
            const C = { teal: '#008878', leaf: '#3aa655', berry: '#d6456a', sun: '#e8b410', orange: '#e88810', sky: '#2f8fd0', grape: '#8a5cc7', grey: '#9fb3ae' };
            const n = (v) => new Intl.NumberFormat('ru-RU').format(v);
            const many = d.labels.length > 14;
            const base = { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } } };
            const axes = (stack = false, money = false) => ({ x: { stacked: stack, grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: many ? 10 : 14 } }, y: { stacked: stack, beginAtZero: true, ticks: { precision: 0, callback: (v) => money ? (v >= 1e6 ? (v / 1e6) + ' mln' : n(v)) : v } } });
            const mk = (ref, cfg) => this.$refs[ref] && window.kdChart(this.$refs[ref], cfg);
            const ds = (label, data, color, extra = {}) => ({ label, data, backgroundColor: color, borderColor: color, borderRadius: 6, ...extra });

            mk('attRate', { type: 'line', data: { labels: d.labels, datasets: [ds(t.rate, d.attendance.rate, C.teal, { tension: .3, fill: true, backgroundColor: 'rgba(0,136,120,.12)', pointRadius: many ? 0 : 3, spanGaps: true })] }, options: { ...base, scales: { ...axes(), y: { min: 0, max: 100, ticks: { callback: (v) => v + '%' } } } } });
            mk('attCount', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.present, d.attendance.present, C.leaf), ds(t.absent, d.attendance.absent, C.berry), ds(t.sick, d.attendance.sick, C.sun)] }, options: { ...base, scales: axes(true) } });
            if (d.finance) {
                mk('fin', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.income, d.finance.income, C.teal), ds(t.expense, d.finance.expense, C.orange), ds(t.net, d.finance.net, C.sky, { type: 'line', tension: .3, pointRadius: many ? 0 : 3, borderRadius: 0 })] }, options: { ...base, scales: axes(false, true), plugins: { ...base.plugins, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + n(c.parsed.y) } } } } });
                if (d.finance.methods.values.length) mk('methods', { type: 'doughnut', data: { labels: d.finance.methods.labels, datasets: [{ data: d.finance.methods.values, backgroundColor: [C.leaf, C.sky, C.grape, C.sun], borderWidth: 0 }] }, options: { ...base, cutout: '62%', plugins: { ...base.plugins, tooltip: { callbacks: { label: (c) => c.label + ': ' + n(c.parsed) } } } } });
                if (d.finance.categories.values.length) mk('cats', { type: 'bar', data: { labels: d.finance.categories.labels, datasets: [ds(t.expense, d.finance.categories.values, C.orange)] }, options: { ...base, indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => n(c.parsed.x) } } }, scales: { x: { beginAtZero: true, ticks: { callback: (v) => v >= 1e6 ? (v / 1e6) + ' mln' : n(v) } }, y: { grid: { display: false } } } } });
            }
            mk('kids', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.new, d.kids.new, C.leaf), ds(t.left, d.kids.left, C.berry)] }, options: { ...base, scales: axes() } });
            if (d.groups.labels.length) mk('groups', { type: 'bar', data: { labels: d.groups.labels, datasets: [ds(t.kids, d.groups.kids, C.teal), ds(t.capacity, d.groups.capacity, C.grey)] }, options: { ...base, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } } } });
            if (d.staff) mk('staff', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.present, d.staff.present, C.leaf), ds(t.late, d.staff.late, C.sun), ds(t.absent, d.staff.absent, C.berry), ds(t.leave, d.staff.leave, C.sky)] }, options: { ...base, scales: axes(true) } });
            if (d.leads) mk('leads', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.leads, d.leads.new, C.sun), ds(t.won, d.leads.won, C.leaf)] }, options: { ...base, scales: axes() } });
            if (d.subsidy) mk('subsidy', { type: 'bar', data: { labels: d.labels, datasets: [ds(t.subsidy, d.subsidy.received, C.grape)] }, options: { ...base, plugins: { legend: { display: false } }, scales: axes(false, true) } });
        },
    }));
});
</script>
@endpush
@endsection
