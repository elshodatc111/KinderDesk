@extends('layouts.app')
@section('title', __("To'garaklar"))
@section('actions')@can('clubs.manage')<a href="{{ route('app.clubs.create') }}" class="btn btn-sm btn-accent"><x-icon name="plus" /> {{ __("To'garak qo'shish") }}</a>@endcan @endsection
@section('content')
<div class="tabs mb-5"><a class="tab {{ $status==='active'?'is-active':'' }}" href="{{ route('app.clubs.index') }}">{{ __('Faol') }}</a><a class="tab {{ $status==='archived'?'is-active':'' }}" href="{{ route('app.clubs.index',['status'=>'archived']) }}">{{ __('Arxiv') }}</a></div>
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
@forelse ($clubs as $c)
    <a href="{{ route('app.clubs.show', $c) }}" class="card card-pad block hover:border-teal-300 transition">
        <div class="flex items-start justify-between gap-3"><h2 class="font-medium text-lg">{{ $c->name }}</h2><span class="badge badge-sky">{{ $c->feeLabel() }}</span></div>
        <p class="text-sm font-semibold text-ink-soft mt-1">{{ $c->schedule ?: '—' }}</p>
        <p class="mt-4 font-medium">{{ $c->active_links_count }} <span class="text-ink-mute font-semibold">{{ __('bola') }}</span></p>
        <p class="text-sm font-semibold text-ink-soft mt-2">{{ $c->teacher?->full_name ?: __("O'qituvchi biriktirilmagan") }}</p>
    </a>
@empty
    <div class="empty col-span-full"><x-icon name="groups" class="size-10" /><p class="font-medium mt-2">{{ __("Hali to'garak yo'q") }}</p>@can('clubs.manage')<a href="{{ route('app.clubs.create') }}" class="btn btn-accent btn-sm mt-3">{{ __("Birinchi to'garakni yarating") }}</a>@endcan</div>
@endforelse
</div>
@endsection
