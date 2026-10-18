@php $lv = ['danger' => 'banner-danger', 'warn' => 'banner-warn', 'info' => 'banner-info']; @endphp
@if(! empty($w['alerts']))
<div class="grid lg:grid-cols-2 gap-2 mb-5">
    @foreach($w['alerts'] as $a)<a href="{{ $a['url'] }}" class="banner {{ $lv[$a['level']] ?? 'banner-info' }}"><p class="flex-1 font-semibold text-sm">{{ $a['text'] }}</p></a>@endforeach
</div>
@endif
@if(! empty($w['groups']) || $w['tg'])
<div class="grid lg:grid-cols-3 gap-4 mb-5">
    @if(! empty($w['groups']))
    <div class="card card-pad lg:col-span-2">
        <h2 class="section-title mb-3">{{ __('Bugungi davomad: guruhlar') }}</h2>
        <div class="grid sm:grid-cols-2 gap-3">
        @foreach($w['groups'] as $g)
            <a href="{{ route('app.attendance.index', ['group' => $g['id']]) }}" class="rounded-xl border border-line p-3 hover:bg-cloud transition">
                <div class="flex items-center justify-between"><span class="font-semibold">{{ $g['name'] }}</span>
                    @if($g['done'])<span class="badge badge-leaf">✓ {{ __('belgilangan') }}</span>@elseif($g['marked'] === 0)<span class="badge badge-orange">{{ __('belgilanmagan') }}</span>@else<span class="badge badge-sky">{{ $g['marked'] }}/{{ $g['total'] }}</span>@endif</div>
                <p class="text-sm text-ink-soft mt-1">{{ __('Keldi') }}: <b>{{ $g['present'] }}</b> / {{ $g['total'] }}</p>
            </a>
        @endforeach
        </div>
    </div>
    @endif
    @if($w['tg'])
    <div class="card card-pad">
        <h2 class="section-title mb-3">Telegram</h2>
        <p class="tile-value">{{ $w['tg']['percent'] }}%</p>
        <p class="text-sm text-ink-soft">{{ __("Ota-onalar ulangan") }}: {{ $w['tg']['connected'] }} / {{ $w['tg']['kids'] }}</p>
        <div class="mt-2 h-2 rounded-full bg-cloud overflow-hidden"><div class="h-full bg-teal-600" style="width: {{ $w['tg']['percent'] }}%"></div></div>
    </div>
    @endif
</div>
@endif
@if($w['cash'] || ! empty($w['debtors']))
<div class="grid lg:grid-cols-3 gap-4 mb-5">
    @if($w['cash'])
    <div class="card card-pad">
        <h2 class="section-title mb-3">{{ __('Pul qoldig\'i') }}</h2>
        <dl class="space-y-1.5 text-sm font-semibold">
            <div class="flex justify-between"><dt>{{ __('Naqd') }}</dt><dd>{{ money($w['cash']['cash']) }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('Karta') }}</dt><dd>{{ money($w['cash']['card']) }}</dd></div>
            <div class="flex justify-between"><dt>{{ __('Bank') }}</dt><dd>{{ money($w['cash']['bank']) }}</dd></div>
        </dl>
    </div>
    @endif
    @if(! empty($w['debtors']))
    <div class="card card-pad lg:col-span-2">
        <h2 class="section-title mb-3">{{ __('Eng katta qarzdorlar') }}</h2>
        <ul class="divide-y divide-line">@foreach($w['debtors'] as $d)<li class="py-1.5 flex justify-between text-sm"><a class="font-semibold hover:underline" href="{{ route('app.kids.show', $d['id']) }}">{{ $d['name'] }}</a><span class="text-danger font-bold">{{ money($d['debt']) }}</span></li>@endforeach</ul>
    </div>
    @endif
</div>
@endif
