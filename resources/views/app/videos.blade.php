@extends('layouts.app')
@section('title', "Video yo'riqnoma")
@section('content')
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
@forelse($videos as $v)
    <div class="card overflow-hidden" x-data="{ on: false }">
        <div class="relative aspect-video bg-ink">
            <template x-if="on"><iframe class="absolute inset-0 size-full" src="https://www.youtube-nocookie.com/embed/{{ $v->youtube_id }}?autoplay=1&rel=0" title="{{ $v->title() }}" allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></template>
            <button type="button" x-show="!on" @click="on = true" class="absolute inset-0 grid place-items-center text-white group" aria-label="{{ __('Videoni ochish') }}">
                <span class="size-16 rounded-full bg-white/15 backdrop-blur grid place-items-center text-3xl group-hover:bg-white/25">▶</span>
            </button>
        </div>
        <div class="p-4"><h3 class="font-semibold">{{ $v->title() }}</h3></div>
    </div>
@empty<div class="card card-pad empty md:col-span-3">{{ __("Hozircha video yo'q") }}</div>@endforelse
</div>
@endsection
