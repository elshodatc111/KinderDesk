@extends('layouts.app')
@section('title', 'Bosh sahifa')
@section('content')
@if (feature('calendar') && auth()->user()->can('calendar.manage') && ! app(\App\Services\Calendar\WorkCalendar::class)->isConfirmed(now()))
<a href="{{ route('app.calendar.index') }}" class="banner banner-warn mb-4"><x-icon name="calendar" /><p>Bu oyning ish kalendari tasdiqlanmagan: bayram kunlarini belgilab, oyni tasdiqlang.</p></a>
@endif
@if (! empty($staleLeads))
<a href="{{ route('app.leads.index') }}" class="banner banner-danger mb-4"><x-icon name="inbox" /><p class="flex-1 font-semibold">{{ __('24 soatdan ortiq javob berilmagan arizalar') }}: {{ $staleLeads }}</p></a>
@endif
@if (! empty($aiReport))
<a href="{{ route('app.ai.reports.show', $aiReport) }}" class="banner banner-info mb-4"><x-icon name="sparkles" />
    <div class="flex-1"><p class="font-semibold">{{ __('AI hisobot') }} · {{ $aiReport->date->format('d.m.Y') }}</p>
        <p class="text-sm">{{ implode(' ', array_slice($aiReport->result['today'] ?? [], 0, 2)) }}</p></div>
    <span class="badge badge-sky">{{ count($aiReport->result['issues'] ?? []) }} {{ __('kamchilik') }}</span></a>
@endif
@include('app.partials.dashboard-widgets')
<div x-data="liveData('{{ route('app.dashboard.stats') }}', @js($stats))">
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <a href="{{ route('app.kids.index') }}" class="tile" style="--tile-bg:var(--color-berry-50);--tile-accent:var(--color-berry-500)">
            <span class="tile-icon"><x-icon name="kids" /></span><p class="tile-label">Faol bolalar</p><p class="tile-value" x-text="data.kids"></p></a>
        <a href="{{ route('app.groups.index') }}" class="tile" style="--tile-bg:var(--color-sky-50);--tile-accent:var(--color-sky-500)">
            <span class="tile-icon"><x-icon name="groups" /></span><p class="tile-label">Guruhlar</p><p class="tile-value" x-text="data.groups"></p></a>
        <a href="{{ route('app.employees.index') }}" class="tile" style="--tile-bg:var(--color-grape-50);--tile-accent:var(--color-grape-500)">
            <span class="tile-icon"><x-icon name="employees" /></span><p class="tile-label">Xodimlar</p><p class="tile-value" x-text="data.employees"></p></a>
        @if (isset($stats['attendance']))
        <a href="{{ route('app.attendance.index') }}" class="tile" style="--tile-bg:var(--color-leaf-50);--tile-accent:var(--color-leaf-500)">
            <span class="tile-icon"><x-icon name="attendance" /></span><p class="tile-label">Bugun kelganlar</p>
            <p class="tile-value"><span x-text="data.attendance?.present ?? 0"></span><span class="text-base text-ink-mute" x-show="data.attendance?.marked"> / <span x-text="data.attendance?.marked"></span></span></p></a>
        @endif
    </div>

    @if (isset($stats['income_month']))
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
        <div class="tile" style="--tile-bg:var(--color-marigold-50);--tile-accent:var(--color-marigold-500)"><span class="tile-icon"><x-icon name="wallet" /></span>
            <p class="tile-label">Shu oy kirim</p><p class="tile-value"><span x-text="new Intl.NumberFormat('ru-RU').format(data.income_month)"></span> <span class="text-sm">so'm</span></p>
            <p class="text-xs font-medium text-ink-soft mt-1">Bugun: <span x-text="new Intl.NumberFormat('ru-RU').format(data.income_today)"></span></p></div>
        <div class="tile" style="--tile-bg:var(--color-sky-50);--tile-accent:var(--color-sky-500)"><span class="tile-icon"><x-icon name="cash" /></span>
            <p class="tile-label">Shu oy chiqim</p><p class="tile-value"><span x-text="new Intl.NumberFormat('ru-RU').format(data.expense_month)"></span> <span class="text-sm">so'm</span></p></div>
        <a href="{{ route('app.kids.index', ['debt' => 1]) }}" class="tile" style="--tile-bg:var(--color-berry-50);--tile-accent:var(--color-danger)"><span class="tile-icon"><x-icon name="alert" /></span>
            <p class="tile-label">Jami qarz</p><p class="tile-value"><span x-text="new Intl.NumberFormat('ru-RU').format(data.debt)"></span> <span class="text-sm">so'm</span></p>
            <p class="text-xs font-medium text-ink-soft mt-1"><span x-text="data.debtors"></span> ta bola</p></a>
        <a href="{{ route('app.finance.index') }}" class="tile" style="--tile-bg:var(--color-sun-50);--tile-accent:var(--color-sun-500)"><span class="tile-icon"><x-icon name="clock" /></span>
            <p class="tile-label">Tasdiq kutayotgan to'lovlar</p><p class="tile-value" x-text="data.pending"></p></a>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mt-6">
        <div class="card card-pad lg:col-span-2">
            <h2 class="section-title mb-3">Kirim va chiqim (6 oy)</h2>
            <div class="h-64"><canvas x-init="
                kdChart($el, { type: 'bar', data: { labels: data.chart.labels, datasets: [
                    { label: 'Kirim', data: data.chart.income, backgroundColor: '#008878', borderRadius: 8 },
                    { label: 'Chiqim', data: data.chart.expense, backgroundColor: '#e88810', borderRadius: 8 } ] },
                  options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { ticks: { callback: v => new Intl.NumberFormat('ru-RU', {notation:'compact'}).format(v) } } } } })
                  .then(c => window.addEventListener('kd:activity', () => { c.data.labels = data.chart.labels; c.data.datasets[0].data = data.chart.income; c.data.datasets[1].data = data.chart.expense; c.update(); }))"></canvas></div>
        </div>
        <div class="card card-pad">
            <h2 class="section-title mb-3">Yaqin tug'ilgan kunlar</h2>
            <template x-if="!data.birthdays.length"><p class="text-sm font-medium text-ink-mute">Bir hafta ichida tug'ilgan kun yo'q</p></template>
            <ul class="space-y-2.5"><template x-for="b in data.birthdays" :key="b.id">
                <li class="flex items-center gap-3"><span class="grid place-items-center size-10 rounded-xl bg-berry-100 text-lg">🎂</span>
                    <span class="min-w-0"><a :href="'{{ url('app/kids') }}/'+b.id" class="block font-semibold truncate hover:underline" x-text="b.name"></a>
                    <span class="text-xs font-medium text-ink-soft"><span x-text="b.date"></span> · <span x-text="b.age"></span> yosh</span></span></li></template></ul>
        </div>
    </div>
    @else
    <div class="card card-pad mt-6">
        <h2 class="section-title mb-3">Yaqin tug'ilgan kunlar</h2>
        <template x-if="!data.birthdays.length"><p class="text-sm font-medium text-ink-mute">Bir hafta ichida tug'ilgan kun yo'q</p></template>
        <ul class="space-y-2"><template x-for="b in data.birthdays" :key="b.id"><li class="font-semibold"><span x-text="b.name"></span> <span class="text-ink-soft text-sm" x-text="b.date"></span></li></template></ul>
    </div>
    @endif
</div>
@endsection
