@extends('layouts.app')
@section('title', 'Ish kalendari')
@section('content')
@php
    $prev = $month->copy()->subMonthNoOverflow()->format('Y-m'); $next = $month->copy()->addMonthNoOverflow()->format('Y-m');
    $names = [1 => 'Du', 2 => 'Se', 3 => 'Chor', 4 => 'Pay', 5 => 'Ju', 6 => 'Sha', 7 => 'Yak'];
    $cls = ['working' => 'bg-white', 'off' => 'bg-cloud text-ink-mute', 'holiday' => 'bg-marigold-100'];
@endphp
<div class="flex flex-wrap items-end justify-between gap-4 mb-5">
    <div class="flex items-center gap-2">
        <a href="{{ route('app.calendar.index', ['month' => $prev]) }}" class="btn btn-sm" aria-label="{{ __('Oldingi oy') }}"><x-icon name="arrow-left" /></a>
        <h2 class="text-lg font-semibold min-w-40 text-center">{{ $month->locale(app()->getLocale() === 'ru' ? 'ru' : 'uz_Latn')->translatedFormat('F Y') }}</h2>
        <a href="{{ route('app.calendar.index', ['month' => $next]) }}" class="btn btn-sm" aria-label="{{ __('Keyingi oy') }}"><x-icon name="arrow-right" /></a>
    </div>
    <div class="flex items-center gap-3">
        <span class="badge badge-sky">Ishchi kunlar (W): {{ $workDays }}</span>
        @if ($confirmed)<span class="badge badge-leaf">Direktor tasdiqlagan</span>@else<span class="badge badge-marigold">Tasdiqlanmagan</span>@endif
        @can('calendar.manage')
        <form method="post" action="{{ route('app.calendar.confirm') }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            @if ($confirmed)<input type="hidden" name="undo" value="1"><button class="btn">Tasdiqni bekor qilish</button>@else<button class="btn btn-accent">Oyni tasdiqlash</button>@endif
        </form>
        @endcan
    </div>
</div>
@unless ($confirmed)
<div class="banner banner-warn mb-4"><x-icon name="alert" /><p>Oy hali tasdiqlanmagan: shanba va yakshanba avtomatik dam olish kuni hisoblanadi, bayramlar ishchi kunga kirmaydi. Bayram kunlarini belgilab, oyni tasdiqlang.</p></div>
@endunless
<div class="card p-4">
    <div class="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-ink-soft mb-1.5">@foreach ($names as $n)<span>{{ __($n) }}</span>@endforeach</div>
    <div class="grid grid-cols-7 gap-1.5">
        @for ($i = 1; $i < $days[0]['date']->dayOfWeekIso; $i++)<span></span>@endfor
        @foreach ($days as $d)
            @php $date = $d['date']; @endphp
            <div class="rounded-lg border border-line p-2 min-h-20 {{ $cls[$d['kind']] }}">
                <div class="flex items-center justify-between"><b>{{ $date->day }}</b>@if ($d['manual'])<span class="size-1.5 rounded-full bg-teal-500" title="{{ __("Qo'lda belgilangan") }}"></span>@endif</div>
                <p class="text-xs font-medium text-ink-soft">{{ __($kinds[$d['kind']]) }}@if($d['title']) · {{ $d['title'] }}@endif</p>
                @can('calendar.manage')
                <form method="post" action="{{ route('app.calendar.day') }}" class="mt-1 flex gap-1">@csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <select name="kind" class="input !py-0.5 !px-1 !text-xs" onchange="this.form.submit()" aria-label="{{ __('Kun turi') }}">
                        <option value="" @selected(! $d['manual'])>{{ __('Avtomatik') }}</option>
                        @foreach ($kinds as $k => $label)<option value="{{ $k }}" @selected($d['manual'] && $d['kind'] === $k)>{{ __($label) }}</option>@endforeach
                    </select>
                </form>
                @endcan
            </div>
        @endforeach
    </div>
</div>
<p class="help mt-3">W — shu oydagi ishchi kunlar soni. To'lov chegirmalari, xodimlar maoshi va hisobotlar shu songa tayanadi. Kun o'zgartirilsa, oy tasdig'i bekor bo'ladi.</p>
@endsection
