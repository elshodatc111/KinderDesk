@extends('layouts.app')
@section('title', __('Bolalar davomadi'))
@section('content')
@php
    $t = $day['totals'];
    $dq = fn ($extra = []) => route('app.attendance.index', array_filter($extra, fn ($v) => $v !== null));
@endphp
<div class="flex flex-wrap items-center gap-3 mb-5">
    <div class="seg" role="tablist">
        <a href="{{ $dq() }}" class="{{ $view === 'today' ? 'is-on is-leaf' : '' }}">{{ __('Bugun') }}</a>
        <a href="{{ $dq(['view' => 'calendar']) }}" class="{{ $view === 'calendar' ? 'is-on is-leaf' : '' }}">{{ __('Kalendar') }}</a>
    </div>
    @if($view === 'today')
    <form method="get" class="flex items-center gap-2 ml-auto">
        <label class="label !mb-0">{{ __('Sana') }}</label>
        <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="input !w-44" onchange="this.form.submit()">
    </form>
    @endif
</div>

@if($view === 'today')
    {{-- Umumiy natija --}}
    <div class="card card-pad mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="font-display text-lg font-bold">{{ __("Bog'cha bo'yicha umumiy natija") }} <span class="text-ink-soft font-semibold text-sm">· {{ $date->format('d.m.Y') }}</span></h2>
            @unless($isToday)<span class="badge badge-gray"><x-icon name="lock" class="size-3.5" /> {{ __("faqat ko'rish") }}</span>@endunless
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div><p class="text-xs font-semibold text-ink-soft">{{ __('Jami bolalar') }}</p><p class="text-2xl font-bold">{{ $t['total'] }}</p></div>
            <div><p class="text-xs font-semibold text-ink-soft">{{ __('Keldi') }}</p><p class="text-2xl font-bold text-leaf-700">{{ $t['present'] }}</p></div>
            <div><p class="text-xs font-semibold text-ink-soft">{{ __('Kelmadi') }}</p><p class="text-2xl font-bold text-berry-700">{{ $t['absent'] }}</p></div>
            <div><p class="text-xs font-semibold text-ink-soft">{{ __('Sababli (kasal)') }}</p><p class="text-2xl font-bold text-sun-700">{{ $t['sick'] }}</p></div>
            <div><p class="text-xs font-semibold text-ink-soft">{{ __('Belgilanmagan') }}</p><p class="text-2xl font-bold text-ink-soft">{{ $t['unmarked'] }}</p></div>
        </div>
        <div class="mt-4 h-2.5 rounded-full bg-sand-200 overflow-hidden" title="{{ $t['rate'] ?? 0 }}%"><div class="h-full bg-leaf-500" style="width: {{ $t['rate'] ?? 0 }}%"></div></div>
        <p class="help mt-1.5">{{ __('Davomad darajasi') }}: <b>{{ $t['rate'] === null ? '—' : $t['rate'].'%' }}</b> · {{ __("Hisobga faqat asosiy guruhlar kiradi.") }}</p>
    </div>

    {{-- Guruh kartalari --}}
    @if($day['groups']->isEmpty())
        <div class="empty"><x-icon name="attendance" class="size-10" /><p class="font-medium mt-2">{{ __('Guruh topilmadi') }}</p><a href="{{ route('app.groups.create') }}" class="btn btn-accent btn-sm mt-3">{{ __('Guruh yarating') }}</a></div>
    @else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($day['groups'] as $r)
            @php $g = $r['group']; $href = $dq(['group' => $g->id, 'date' => $isToday ? null : $date->toDateString()]); @endphp
            <a href="{{ $href }}" class="card card-pad block hover:border-teal-500 transition">
                <div class="flex items-start justify-between gap-2">
                    <div><p class="font-bold text-lg leading-tight">{{ $g->name }}</p>@if($g->type !== 'main')<p class="text-xs font-semibold text-ink-soft">{{ __($g->typeLabel()) }}</p>@endif</div>
                    <span class="badge {{ $r['rate'] === null ? 'badge-gray' : ($r['rate'] >= 80 ? 'badge-leaf' : ($r['rate'] >= 60 ? 'badge-sun' : 'badge-red')) }}">{{ $r['rate'] === null ? '—' : $r['rate'].'%' }}</span>
                </div>
                <div class="mt-3 h-2 rounded-full bg-sand-200 overflow-hidden"><div class="h-full bg-leaf-500" style="width: {{ $r['rate'] ?? 0 }}%"></div></div>
                <div class="mt-3 grid grid-cols-4 gap-1 text-center text-xs font-semibold">
                    <div><p class="text-lg font-bold text-leaf-700">{{ $r['present'] }}</p>{{ __('Keldi') }}</div>
                    <div><p class="text-lg font-bold text-berry-700">{{ $r['absent'] }}</p>{{ __('Kelmadi') }}</div>
                    <div><p class="text-lg font-bold text-sun-700">{{ $r['sick'] }}</p>{{ __('Sababli') }}</div>
                    <div><p class="text-lg font-bold text-ink-soft">{{ $r['unmarked'] }}</p>{{ __('Belgilanmagan') }}</div>
                </div>
                <p class="mt-3 text-sm font-semibold text-teal-700">{{ $isToday ? __('Davomad olish') : __("Ko'rish") }} →</p>
            </a>
        @endforeach
    </div>
    @endif
@else
    {{-- Oylik kalendar --}}
    @php
        $prev = $month->copy()->subMonth()->format('Y-m'); $next = $month->copy()->addMonth()->format('Y-m');
        $canNext = $month->copy()->addMonth()->startOfMonth()->lte(today());
        $lead = $month->copy()->startOfMonth()->dayOfWeekIso - 1;
    @endphp
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <a href="{{ $dq(['view' => 'calendar', 'month' => $prev]) }}" class="btn btn-sm" aria-label="{{ __('Oldingi oy') }}"><x-icon name="arrow-left" /></a>
        <h2 class="font-display text-lg font-bold min-w-40 text-center">{{ $month->translatedFormat('F Y') }}</h2>
        @if($canNext)<a href="{{ $dq(['view' => 'calendar', 'month' => $next]) }}" class="btn btn-sm" aria-label="{{ __('Keyingi oy') }}"><x-icon name="arrow-right" /></a>@endif
        <div class="ml-auto flex flex-wrap gap-2">
            <span class="badge badge-gray">{{ __('Belgilangan kunlar') }}: {{ $monthSummary['days'] }}</span>
            <span class="badge badge-leaf">{{ __("O'rtacha") }}: {{ $monthSummary['avg'] === null ? '—' : $monthSummary['avg'].'%' }}</span>
            <span class="badge badge-red">{{ __('Kelmagan') }}: {{ $monthSummary['absent'] }}</span>
            <span class="badge badge-orange">{{ __('Sababli') }}: {{ $monthSummary['sick'] }}</span>
        </div>
    </div>
    <div class="card card-pad">
        <div class="grid grid-cols-7 gap-1 sm:gap-2 text-center text-xs font-bold text-ink-soft mb-2">
            @foreach([__('Du'), __('Se'), __('Ch'), __('Pa'), __('Ju'), __('Sh'), __('Ya')] as $w)<div>{{ $w }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7 gap-1 sm:gap-2">
            @for($i = 0; $i < $lead; $i++)<div></div>@endfor
            @foreach($cal as $key => $c)
                @php $on = $selected && $selected->toDateString() === $key; $clickable = $c['working'] && ! $c['future'] && $c['marked']; @endphp
                @if($clickable)<a href="{{ $dq(['view' => 'calendar', 'month' => $month->format('Y-m'), 'date' => $key]) }}#kun" @else<div @endif
                    class="rounded-xl border p-1.5 sm:p-2 min-h-16 sm:min-h-24 text-left {{ $on ? 'border-teal-500 bg-teal-50' : 'border-line' }} {{ $clickable ? 'hover:border-teal-500 bg-white' : 'bg-sand-100 text-ink-mute' }}">
                    <p class="text-xs sm:text-sm font-bold {{ $c['date']->isToday() ? 'text-teal-700' : '' }}">{{ $c['date']->day }}</p>
                    @if($clickable)
                        <p class="text-[11px] sm:text-xs font-semibold text-ink-soft"><b class="text-leaf-700">{{ $c['present'] }}</b>/{{ $c['total'] }}</p>
                        <p class="hidden sm:flex gap-1.5 mt-1 text-[11px] font-bold"><span class="text-berry-700" title="{{ __('Kelmadi') }}">{{ $c['absent'] }}</span><span class="text-sun-700" title="{{ __('Sababli') }}">{{ $c['sick'] }}</span></p>
                    @elseif(! $c['working'] && ! $c['future'])
                        <p class="text-[10px] font-semibold">{{ __('Dam') }}</p>
                    @endif
                @if($clickable)</a>@else</div>@endif
            @endforeach
        </div>
        <p class="help mt-3"><b class="text-leaf-700">{{ __('keldi') }}</b>/{{ __('jami') }} · <b class="text-berry-700">{{ __('kelmadi') }}</b> · <b class="text-sun-700">{{ __('sababli') }}</b>. {{ __("Kunni bosing — guruhlar kesimida ko'rasiz.") }}</p>
    </div>

    @if($selectedDay)
    <div id="kun" class="card card-pad mt-5">
        <h3 class="font-display text-lg font-bold mb-3">{{ $selected->format('d.m.Y') }} — {{ __('guruhlar kesimida') }}</h3>
        <div class="overflow-x-auto"><table class="table w-full text-sm">
            <thead><tr class="text-left text-ink-soft"><th class="py-2">{{ __('Guruh') }}</th><th>{{ __('Jami') }}</th><th>{{ __('Keldi') }}</th><th>{{ __('Kelmadi') }}</th><th>{{ __('Sababli') }}</th><th>{{ __('Belgilanmagan') }}</th><th>%</th><th></th></tr></thead>
            <tbody>
            @foreach($selectedDay['groups'] as $r)
                <tr class="border-t border-line"><td class="py-2 font-semibold">{{ $r['group']->name }}</td><td>{{ $r['total'] }}</td><td class="text-leaf-700 font-bold">{{ $r['present'] }}</td><td class="text-berry-700 font-bold">{{ $r['absent'] }}</td><td class="text-sun-700 font-bold">{{ $r['sick'] }}</td><td>{{ $r['unmarked'] }}</td><td>{{ $r['rate'] === null ? '—' : $r['rate'].'%' }}</td>
                    <td class="text-right"><a class="text-teal-700 font-semibold" href="{{ $dq(['group' => $r['group']->id, 'date' => $selected->toDateString()]) }}">{{ __("Ko'rish") }}</a></td></tr>
            @endforeach
            <tr class="border-t-2 border-line font-bold"><td class="py-2">{{ __('Jami (asosiy guruhlar)') }}</td><td>{{ $selectedDay['totals']['total'] }}</td><td class="text-leaf-700">{{ $selectedDay['totals']['present'] }}</td><td class="text-berry-700">{{ $selectedDay['totals']['absent'] }}</td><td class="text-sun-700">{{ $selectedDay['totals']['sick'] }}</td><td>{{ $selectedDay['totals']['unmarked'] }}</td><td>{{ $selectedDay['totals']['rate'] === null ? '—' : $selectedDay['totals']['rate'].'%' }}</td><td></td></tr>
            </tbody>
        </table></div>
    </div>
    @endif
@endif
@endsection
