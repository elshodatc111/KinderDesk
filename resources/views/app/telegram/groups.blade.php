@extends('layouts.app')
@section('title', 'Telegram')
@section('content')
@include('app.telegram._tabs')
@if(! $bot || ! $bot->usable())
    <div class="banner banner-info mb-4">{{ __("Telegram bot hali ulanmagan.") }} @can('tg.manage')<a class="font-semibold underline" href="{{ route('app.telegram.settings') }}">{{ __('Ulash') }}</a>@endcan</div>
@endif
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
@forelse($groups as $g)
    <a href="{{ route('app.telegram.group', $g) }}" class="card card-pad hover:shadow-md transition">
        <h3 class="font-semibold">{{ $g->name }}</h3>
        <p class="text-sm text-ink-soft mt-1">{{ __('Ulangan') }}: <b>{{ $stats[$g->id]['connected'] }}</b> / {{ $stats[$g->id]['kids'] }}</p>
        <div class="mt-3 h-2 rounded-full bg-cloud overflow-hidden"><div class="h-full bg-teal-600" style="width: {{ $stats[$g->id]['kids'] ? round($stats[$g->id]['connected'] / $stats[$g->id]['kids'] * 100) : 0 }}%"></div></div>
    </a>
@empty<div class="card card-pad empty md:col-span-3">{{ __("Sizga biriktirilgan guruh yo'q") }}</div>@endforelse
</div>
@endsection
