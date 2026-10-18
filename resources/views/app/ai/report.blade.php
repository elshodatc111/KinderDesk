@extends('layouts.app')
@section('title', 'AI hisobot')
@section('content')
@php
    $ct = ['grounded' => 'Asoslangan', 'hypothesis' => 'Gipoteza', 'insufficient_data' => "Ma'lumot yetarli emas"];
    $cf = ['high' => 'Yuqori', 'medium' => "O'rta", 'low' => 'Past'];
@endphp
<div class="w-full space-y-5">
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge badge-sky">{{ $report->period === 'weekly' ? __('Haftalik') : __('Kunlik') }} · {{ $report->date->format('d.m.Y') }}</span>
        @if($report->status === 'unreliable')<span class="badge badge-orange">{{ __('Ishonchsiz: ba\'zi raqamlar paket bilan mos kelmadi') }}</span>@endif
    </div>
    @if($report->status === 'unreliable' && ! empty($r['problems']))
        <div class="banner banner-warn"><x-icon name="info" /><p>{{ __("Tekshiruv topgan nomuvofiqliklar") }}: {{ implode('; ', array_slice($r['problems'], 0, 5)) }}</p></div>
    @endif

    <div class="card card-pad"><h3 class="font-semibold mb-2">{{ __('Bugungi holat') }}</h3>
        <ul class="list-disc pl-5 space-y-1 text-sm">@foreach($r['today'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></div>

    <div class="space-y-3"><h3 class="font-semibold">{{ __('Kamchiliklar') }}</h3>
        @forelse($r['issues'] ?? [] as $i)
            <div class="card card-pad space-y-2">
                <p class="font-medium">{{ $i['title'] ?? '' }}</p>
                @if(! empty($i['evidence']))<div class="flex flex-wrap gap-2">@foreach($i['evidence'] as $ev)<span class="badge badge-gray">{{ $ev['key'] }} = {{ is_array($ev['value'] ?? null) ? '' : ($ev['value'] ?? '') }}</span>@endforeach</div>@endif
                <p class="text-sm"><b>{{ __('Ehtimoliy sabab') }}:</b> {{ $i['cause'] ?? '' }} <span class="badge {{ ($i['cause_type'] ?? '') === 'grounded' ? 'badge-leaf' : 'badge-orange' }}">{{ __($ct[$i['cause_type'] ?? ''] ?? '') }}</span> <span class="text-ink-mute">· {{ __('Ishonch') }}: {{ __($cf[$i['confidence'] ?? ''] ?? '') }}</span></p>
                <p class="text-sm"><b>{{ __('Javobgar') }}:</b> {{ $i['role'] ?? '' }} · <b>{{ __('Nima qilish') }}:</b> {{ $i['action'] ?? '' }}</p>
            </div>
        @empty
            <div class="card card-pad"><div class="empty">{{ __("Kamchilik topilmadi") }}</div></div>
        @endforelse
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="card card-pad"><h3 class="font-semibold mb-2">{{ __('Yaxshi tomonlar') }}</h3><ul class="list-disc pl-5 space-y-1 text-sm">@foreach($r['good'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
        <div class="card card-pad"><h3 class="font-semibold mb-2">{{ __("Ertaga e'tibor") }}</h3><ul class="list-disc pl-5 space-y-1 text-sm">@foreach($r['tomorrow'] ?? [] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
    </div>
    <p class="text-xs text-ink-mute">{{ __("Raqamlarni tizim hisoblaydi, AI faqat izohlaydi. Sabab «gipoteza» deb belgilangan bo'lsa, tekshirib ko'ring.") }}</p>
</div>
@endsection
