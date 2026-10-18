@extends('layouts.admin')
@section('title', "Video yo'riqnoma")
@section('content')
<p class="text-sm text-ink-soft mb-4">{{ __("YouTube'ga yuklangan videolar bog'cha kabinetida platformaning o'zida ko'rsatiladi. Havolani yoki video ID'sini kiriting.") }}</p>
<form method="post" action="{{ route('admin.videos.store') }}" class="card card-pad grid md:grid-cols-[1fr_1fr_1.2fr_6rem_auto] gap-3 items-end mb-6">@csrf
    <div><label class="label">{{ __('Sarlavha (UZ)') }}</label><input name="title_uz" class="input" required></div>
    <div><label class="label">{{ __('Sarlavha (RU)') }}</label><input name="title_ru" class="input"></div>
    <div><label class="label">YouTube</label><input name="url" class="input" placeholder="https://youtu.be/..." required></div>
    <div><label class="label">{{ __('Tartib') }}</label><input name="sort" type="number" class="input" value="{{ ($videos->max('sort') ?? 0) + 1 }}"></div>
    <input type="hidden" name="is_active" value="1"><button class="btn btn-primary">{{ __("Qo'shish") }}</button>
    @error('url')<p class="field-error md:col-span-5">{{ $message }}</p>@enderror
</form>
<div class="space-y-3">
@forelse($videos as $v)
    <form method="post" action="{{ route('admin.videos.update', $v) }}" class="card card-pad grid md:grid-cols-[1fr_1fr_1.2fr_6rem_auto_auto] gap-3 items-end">@csrf @method('put')
        <div><label class="label">UZ</label><input name="title_uz" class="input" value="{{ $v->title_uz }}" required></div>
        <div><label class="label">RU</label><input name="title_ru" class="input" value="{{ $v->title_ru }}"></div>
        <div><label class="label">YouTube</label><input name="url" class="input" value="https://youtu.be/{{ $v->youtube_id }}" required></div>
        <div><label class="label">{{ __('Tartib') }}</label><input name="sort" type="number" class="input" value="{{ $v->sort }}"></div>
        <label class="flex items-center gap-1.5 text-sm font-medium pb-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" class="check" name="is_active" value="1" @checked($v->is_active)> {{ __('Faol') }}</label>
        <div class="flex gap-2"><button class="btn btn-sm btn-primary">{{ __('Saqlash') }}</button>
            <button class="btn btn-sm" form="del{{ $v->id }}" onclick="return confirm('{{ __("O'chirilsinmi?") }}')">{{ __("O'chirish") }}</button></div>
    </form>
    <form id="del{{ $v->id }}" method="post" action="{{ route('admin.videos.destroy', $v) }}">@csrf @method('delete')</form>
@empty<div class="card card-pad empty">{{ __("Hali video yo'q") }}</div>@endforelse
</div>
@endsection
