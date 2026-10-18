@extends('layouts.app')
@section('title', $club->name)
@section('actions')@can('clubs.manage')<a href="{{ route('app.clubs.edit', $club) }}" class="btn btn-sm"><x-icon name="edit" /> {{ __('Tahrirlash') }}</a>
<form method="post" action="{{ route('app.clubs.archive', $club) }}">@csrf<button class="btn btn-sm">{{ $club->status==='active' ? __('Arxivlash') : __('Qayta faollashtirish') }}</button></form>@endcan @endsection
@section('content')
<div class="grid lg:grid-cols-[1fr_20rem] gap-6 items-start">
    <div class="space-y-6">
        <div>
            <form method="get" class="mb-4 flex items-end gap-3"><div><label class="label">{{ __('Sana') }}</label><input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="input !w-44" onchange="this.form.submit()"></div></form>
            @can('attendance.kids')
                <x-attendance-list :rows="$rows" :date="$date->toDateString()" :url="route('app.clubs.attendance', $club)" :empty-text="__('Hali bola yozilmagan')" />
            @endcan
        </div>
    </div>
    <div class="space-y-4">
        <div class="card card-pad"><p class="tile-label">{{ __("To'lov") }}</p><p class="text-2xl font-medium">{{ $club->feeLabel() }}</p>
            <p class="text-sm font-semibold text-ink-soft mt-1">{{ $club->schedule }} @if($club->teacher)· {{ $club->teacher->full_name }}@endif</p></div>
        <div class="card card-pad space-y-3">
            <h2 class="section-title">{{ __('Qatnashuvchilar') }} ({{ $links->count() }})</h2>
            @forelse($links as $l)
                <div class="flex items-center justify-between gap-2"><a href="{{ route('app.kids.show', $l->kid) }}" class="font-semibold hover:underline">{{ $l->kid->full_name }}</a>
                    @can('clubs.manage')<form method="post" action="{{ route('app.clubs.leave', [$club, $l]) }}">@csrf @method('delete')<button class="btn-quiet is-danger">{{ __('Chiqarish') }}</button></form>@endcan</div>
            @empty<p class="text-sm font-semibold text-ink-mute">{{ __('Hali bola yozilmagan') }}</p>@endforelse
            @can('clubs.manage')
            @if($available->isNotEmpty())
            <form method="post" action="{{ route('app.clubs.enroll', $club) }}" class="flex gap-2 pt-3 border-t border-line">@csrf
                <select name="kid_id" class="select">@foreach($available as $k)<option value="{{ $k->id }}">{{ $k->full_name }}</option>@endforeach</select><button class="btn btn-sm">{{ __('Yozish') }}</button></form>
            @endif
            @endcan
        </div>
    </div>
</div>
@endsection
