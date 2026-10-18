@extends('layouts.app')
@section('title', 'Guruhlar')
@section('actions')@can('groups.manage')<a href="{{ route('app.groups.create') }}" class="btn btn-sm btn-accent"><x-icon name="plus" /> {{ __("Guruh qo'shish") }}</a>@endcan @endsection
@section('content')
<div class="tabs mb-5"><a class="tab {{ $status==='active'?'is-active':'' }}" href="{{ route('app.groups.index') }}">Faol</a><a class="tab {{ $status==='archived'?'is-active':'' }}" href="{{ route('app.groups.index',['status'=>'archived']) }}">Arxiv</a></div>
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
@forelse ($groups as $g)
    @php $pct = $g->capacity ? min(100, round($g->active_kid_links_count / $g->capacity * 100)) : null; @endphp
    <a href="{{ route('app.groups.show', $g) }}" class="card-sticker p-5 block hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between gap-3"><h2 class="font-semibold text-lg">{{ $g->name }}</h2><span class="badge badge-sky">{{ $g->isDaily() ? money($g->daily_fee).' '.__("so'm").' / '.__('kun') : money($g->monthly_fee).' '.__("so'm") }}</span></div>
        <p class="text-sm font-medium text-ink-soft mt-1">@if($g->type!=='main')<span class="badge {{ $g->type==='evening' ? 'badge-grape' : 'badge-orange' }} mr-1">{{ __($g->typeLabel()) }}</span>@endif{{ $g->timeLabel() }} {{ $g->age_range }} @if($g->room) · {{ $g->room }} @endif</p>
        <p class="mt-4 font-semibold">{{ $g->active_kid_links_count }}@if($g->capacity) <span class="text-ink-mute">/ {{ $g->capacity }}</span>@endif bola</p>
        @if($pct !== null)<div class="progress mt-2"><span style="width:{{ $pct }}%;background:{{ $pct >= 100 ? 'var(--color-danger)' : 'var(--color-teal-500)' }}"></span></div>@endif
        <p class="text-sm font-semibold text-ink-soft mt-3">{{ $g->activeEmployeeLinks->map(fn($l) => $l->employee?->full_name)->filter()->implode(', ') ?: 'Tarbiyachi biriktirilmagan' }}</p>
    </a>
@empty
    <div class="empty col-span-full"><x-icon name="groups" class="size-10" /><p class="font-semibold mt-2">Hali guruh yo'q</p>@can('groups.manage')<a href="{{ route('app.groups.create') }}" class="btn btn-accent btn-sm mt-3">Birinchi guruhni yarating</a>@endcan</div>
@endforelse
</div>
@endsection
