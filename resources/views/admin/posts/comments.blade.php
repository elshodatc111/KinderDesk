@extends('layouts.admin')
@section('title', 'Izohlar')
@section('actions')
    <a href="{{ route('admin.posts.index') }}" class="btn btn-sm"><x-icon name="arrow-left" /> Yangiliklar</a>
@endsection
@section('content')
@php
    $tabs = ['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlangan', 'hidden' => 'Yashirin', 'all' => 'Hammasi'];
    $q = fn ($s) => array_filter(['s' => $s, 'post' => $postId]);
    $badge = ['pending' => 'badge-orange', 'approved' => 'badge-leaf', 'hidden' => 'badge-gray'];
    $label = ['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlangan', 'hidden' => 'Yashirin'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="tabs overflow-x-auto" role="tablist">
        @foreach($tabs as $k => $l)
            <a class="tab {{ $status === $k ? 'is-active' : '' }}" role="tab" aria-selected="{{ $status === $k ? 'true' : 'false' }}" href="{{ route('admin.comments.index', $q($k)) }}">
                {{ __($l) }}@if($k !== 'all' && ($counts[$k] ?? 0)) <span class="text-ink-mute">({{ $counts[$k] }})</span>@endif
            </a>
        @endforeach
    </div>
    <form method="get" action="{{ route('admin.comments.index') }}" class="flex items-center gap-2">
        <input type="hidden" name="s" value="{{ $status }}">
        <label class="sr-only" for="post-filter">Post</label>
        <select id="post-filter" name="post" class="select !py-1.5 max-w-[16rem]" onchange="this.form.submit()">
            <option value="">Barcha postlar</option>
            @foreach($posts as $p)<option value="{{ $p->id }}" @selected($postId === $p->id)>{{ \Illuminate\Support\Str::limit($p->title_uz, 50) }}</option>@endforeach
        </select>
    </form>
</div>

@if($comments->isEmpty())
    <div class="card card-pad"><div class="empty">
        <x-icon name="sms" class="size-10" />
        <h3>Bu bo'limda izoh yo'q</h3>
        <p>Mehmonlar yozgan izohlar shu yerda paydo bo'ladi. Siz tasdiqlaganingizdan keyin saytda ko'rinadi.</p>
    </div></div>
@else
<form id="bulk" method="post" action="{{ route('admin.comments.bulk') }}" x-data="{ all: false, n: 0, count() { this.n = $root.querySelectorAll('input[name=\'ids[]\']:checked').length } }">
    @csrf
    <div class="flex flex-wrap items-center gap-3 mb-3 text-sm">
        <label class="check"><input type="checkbox" x-model="all" @change="$root.querySelectorAll('input[name=\'ids[]\']').forEach(i => i.checked = all); count()"> Hammasini belgilash</label>
        <span class="text-ink-mute font-medium" x-show="n" x-cloak><span x-text="n"></span> ta belgilandi</span>
        <div class="flex gap-2 ml-auto" x-show="n" x-cloak>
            <button name="action" value="approve" class="btn btn-sm btn-primary">Tasdiqlash</button>
            <button name="action" value="hide" class="btn btn-sm">Yashirish</button>
            <button name="action" value="delete" class="btn btn-sm !text-danger" onclick="return confirm('{{ __("Belgilangan izohlar o'chiriladi. Davom etasizmi?") }}')">O'chirish</button>
        </div>
    </div>

    <ul class="space-y-3" @change="count()">
        @foreach($comments as $c)
            <li class="card card-pad" x-data="{ reply: false }">
                <div class="flex gap-3">
                    <input type="checkbox" class="check mt-1 shrink-0" name="ids[]" value="{{ $c->id }}" aria-label="Belgilash">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-semibold">{{ $c->author_name }}</span>
                            @if($c->user_id)<span class="badge badge-sky">Foydalanuvchi</span>@endif
                            <span class="badge {{ $badge[$c->status] ?? 'badge-gray' }}">{{ __($label[$c->status] ?? $c->status) }}</span>
                            <time class="text-xs font-medium text-ink-mute" datetime="{{ $c->created_at->toIso8601String() }}">{{ $c->created_at->format('d.m.Y, H:i') }}</time>
                        </div>
                        <p class="mt-1 text-sm whitespace-pre-line break-words">{{ $c->body }}</p>
                        @if($c->post)<p class="mt-1.5 text-xs font-medium text-ink-mute">Post: <a class="underline hover:text-teal-700" href="{{ route('admin.posts.edit', $c->post) }}">{{ $c->post->title_uz }}</a></p>@endif

                        @foreach($c->replies as $r)
                            <div class="mt-3 ml-1 border-l-2 border-teal-200 pl-3">
                                <div class="flex items-center gap-2 text-xs font-medium text-ink-mute"><span class="font-semibold text-teal-700">{{ $r->author_name }}</span><time>{{ $r->created_at->format('d.m.Y, H:i') }}</time></div>
                                <p class="text-sm whitespace-pre-line break-words">{{ $r->body }}</p>
                                <button type="submit" form="del-{{ $r->id }}" class="text-xs font-semibold text-danger hover:underline" onclick="return confirm('{{ __("Javob o'chiriladi. Davom etasizmi?") }}')">O'chirish</button>
                            </div>
                        @endforeach

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if($c->status !== 'approved')<button type="submit" form="ap-{{ $c->id }}" class="btn btn-sm btn-primary">Tasdiqlash</button>@endif
                            @if($c->status !== 'hidden')<button type="submit" form="hd-{{ $c->id }}" class="btn btn-sm">Yashirish</button>@endif
                            <button type="button" class="btn btn-sm" @click="reply = !reply" :aria-expanded="reply">Javob yozish</button>
                            <button type="submit" form="del-{{ $c->id }}" class="btn btn-sm !text-danger" onclick="return confirm('{{ __("Izoh va uning javoblari o'chiriladi. Davom etasizmi?") }}')"><x-icon name="trash" /> O'chirish</button>
                        </div>
                        <div x-show="reply" x-cloak class="mt-3 flex gap-2 items-start">
                            <label class="sr-only" for="rb-{{ $c->id }}">Javob</label>
                            <textarea id="rb-{{ $c->id }}" form="rp-{{ $c->id }}" name="body" rows="2" maxlength="1000" class="input" placeholder="«KinderDesk» nomidan javob…"></textarea>
                            <button type="submit" form="rp-{{ $c->id }}" class="btn btn-sm btn-primary shrink-0">Yuborish</button>
                        </div>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</form>

{{-- Alohida amallar uchun yashirin formalar (ichma-ich forma bo'lmasligi uchun tashqarida) --}}
@foreach($comments as $c)
    <form id="ap-{{ $c->id }}" method="post" action="{{ route('admin.comments.approve', $c) }}">@csrf</form>
    <form id="hd-{{ $c->id }}" method="post" action="{{ route('admin.comments.hide', $c) }}">@csrf</form>
    <form id="rp-{{ $c->id }}" method="post" action="{{ route('admin.comments.reply', $c) }}">@csrf</form>
    <form id="del-{{ $c->id }}" method="post" action="{{ route('admin.comments.destroy', $c) }}">@csrf @method('delete')</form>
    @foreach($c->replies as $r)
        <form id="del-{{ $r->id }}" method="post" action="{{ route('admin.comments.destroy', $r) }}">@csrf @method('delete')</form>
    @endforeach
@endforeach

<div class="mt-4">{{ $comments->links() }}</div>
@endif
@endsection
