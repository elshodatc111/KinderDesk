@extends('layouts.admin')
@section('title', 'Yangiliklar')
@section('actions')
    <a href="{{ route('admin.comments.index') }}" class="btn btn-sm"><x-icon name="sms" /> Izohlar @if($pending)<span class="badge badge-orange">{{ $pending }}</span>@endif</a>
    <a href="{{ route('admin.posts.create') }}" class="btn btn-sm btn-primary"><x-icon name="plus" /> Yangi post</a>
@endsection
@section('content')
@php
    $filters = ['all' => 'Hammasi', 'published' => 'Nashr qilingan', 'scheduled' => 'Rejalashtirilgan', 'draft' => 'Qoralama'];
@endphp
<p class="text-sm text-ink-soft mb-4 max-w-3xl">Sotuv sahifasidagi «Yangiliklar» bo'limi: matn, rasm yoki YouTube video bilan postlar. Har bir postga mehmonlar izoh qoldira oladi; izohlar moderatsiyadan keyin ko'rinadi.</p>

<div class="tabs mb-4 overflow-x-auto" role="tablist">
    @foreach($filters as $k => $l)<a class="tab {{ $filter === $k ? 'is-active' : '' }}" href="{{ route('admin.posts.index', $k === 'all' ? [] : ['f' => $k]) }}" role="tab" aria-selected="{{ $filter === $k ? 'true' : 'false' }}">{{ __($l) }}</a>@endforeach
</div>

<div class="table-wrap"><div class="table-scroll">
<table class="table">
    <thead><tr><th>Sarlavha</th><th>Turi</th><th>Holati</th><th>Vaqti</th><th class="num">Izohlar</th><th class="num">Ko'rishlar</th><th></th></tr></thead>
    <tbody>
    @forelse($posts as $p)
        <tr>
            <td class="min-w-[16rem]">
                <a href="{{ route('admin.posts.edit', $p) }}" class="font-semibold hover:text-teal-700">{{ $p->title_uz }}</a>
                @if($p->is_pinned)<span class="badge badge-sun ml-1">Mahkamlangan</span>@endif
                @if($p->title_ru)<p class="text-xs text-ink-mute font-medium mt-0.5">{{ $p->title_ru }}</p>@endif
            </td>
            <td><span class="badge badge-gray">{{ __(\App\Models\Post::TYPES[$p->type] ?? $p->type) }}</span></td>
            <td>
                @if($p->status === 'draft')<span class="badge badge-gray">Qoralama</span>
                @elseif($p->isScheduled())<span class="badge badge-sky">Rejalashtirilgan</span>
                @else<span class="badge badge-leaf">Nashr qilingan</span>@endif
            </td>
            <td class="whitespace-nowrap text-ink-soft">{{ $p->published_at?->format('d.m.Y, H:i') ?? '—' }}</td>
            <td class="num">
                {{ $p->approved_count }}
                @if($p->pending_count)<a href="{{ route('admin.comments.index', ['post' => $p->id]) }}" class="badge badge-orange ml-1" title="Kutilmoqda">+{{ $p->pending_count }}</a>@endif
            </td>
            <td class="num">{{ $p->views }}</td>
            <td class="text-right whitespace-nowrap">
                <a href="{{ $p->url() }}" target="_blank" rel="noopener" class="btn btn-sm" title="Saytda ko'rish" aria-label="Saytda ko'rish"><x-icon name="eye" /></a>
                <a href="{{ route('admin.posts.edit', $p) }}" class="btn btn-sm" title="Tahrirlash" aria-label="Tahrirlash"><x-icon name="edit" /></a>
            </td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty">Hali post yo'q. «Yangi post» tugmasi bilan birinchisini yarating.</div></td></tr>
    @endforelse
    </tbody>
</table>
</div></div>
<div class="mt-4">{{ $posts->links() }}</div>
@endsection
