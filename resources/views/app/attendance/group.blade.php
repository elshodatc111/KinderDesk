@extends('layouts.app')
@section('title', __('Bolalar davomadi'))
@section('content')
<div class="flex flex-wrap items-center gap-3 mb-5">
    <a href="{{ route('app.attendance.index', ['date' => $date->isToday() ? null : $date->toDateString()]) }}" class="btn btn-sm"><x-icon name="arrow-left" /> {{ __('Guruhlar') }}</a>
    <h2 class="font-display text-xl font-bold">{{ $group->name }}@if($group->type!=='main') <span class="text-ink-soft text-base font-semibold">— {{ __($group->typeLabel()) }}</span>@endif</h2>
    <span class="badge {{ $locked ? 'badge-gray' : 'badge-leaf' }}">{{ $date->format('d.m.Y') }}@if($locked) · {{ __("faqat ko'rish") }}@endif</span>
</div>
@if($day)
<div class="flex flex-wrap gap-2 mb-4">
    <span class="badge badge-leaf">{{ __('Keldi') }}: {{ $day['present'] }}</span><span class="badge badge-red">{{ __('Kelmadi') }}: {{ $day['absent'] }}</span>
    <span class="badge badge-orange">{{ __('Sababli') }}: {{ $day['sick'] }}</span><span class="badge badge-gray">{{ __('Jami') }}: {{ $day['total'] }}</span>
</div>
@endif
{{-- guruh --}}
    @if($group->isDaily())
        <div class="banner banner-info mb-4 w-full"><x-icon name="info" /><p class="flex-1">{{ __($group->typeLabel()) }}@if($group->timeLabel()) ({{ $group->timeLabel() }})@endif. {{ __('Faqat «Keldi» belgilangan kun uchun bolaning hisobidan :fee yechiladi.', ['fee' => money($group->daily_fee).' '.__("so'm")]) }}</p></div>
    @endif
    @if($wrongDay)
        <div class="banner banner-warn mb-4 w-full"><x-icon name="clock" /><p>{{ __('Shanba guruhi davomadi faqat shanba kuni belgilanadi. Shanba kuni qaytib keling.') }}</p></div>
    @else
        <x-attendance-list :rows="$rows" :payload="['group_id' => $group->id]" :date="$date->toDateString()" :locked="$locked" :url="route('app.attendance.store')" />
    @endif
    @if(! $locked && $group->isDaily() && $available->isNotEmpty() && ! $wrongDay)
        <form method="post" action="{{ route('app.attendance.attach') }}" class="w-full mt-5 card card-pad flex flex-wrap items-end gap-3">@csrf
            <input type="hidden" name="group_id" value="{{ $group->id }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <div class="flex-1 min-w-56"><label class="label">{{ __("Ro'yxatda yo'q bolani qo'shish") }}</label>
                <select name="kid_id" class="select">@foreach($available as $k)<option value="{{ $k->id }}">{{ $k->full_name }}</option>@endforeach</select></div>
            <button class="btn btn-sm">{{ __("Qo'shish") }}</button>
        </form>
    @endif
@endsection
