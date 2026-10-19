@extends('layouts.public')
@section('title', $post->title())
@section('meta_title', \Illuminate\Support\Str::limit($post->title(), 55, '…').' | KinderDesk')
@section('description', $post->excerpt(160))
@section('og_type', 'article')
@if($post->image())@section('og_image', $post->image())@endif
@unless($post->isVisible())@section('noindex', '1')@endunless
@push('head')
@php
    $ldPost = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => \Illuminate\Support\Str::limit($post->title(), 110, ''),
        'description' => $post->excerpt(200),
        'image' => $post->image() ? [$post->image()] : null,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => ($post->edited_at ?? $post->published_at)?->toIso8601String(),
        'inLanguage' => app()->getLocale(),
        'mainEntityOfPage' => \App\Support\Site\Seo::urlFor(app()->getLocale()),
        'author' => ['@type' => 'Organization', 'name' => 'KinderDesk', 'url' => route('home')],
        'publisher' => ['@id' => route('home').'#organization'],
    ]);
    $ldCrumbs = \App\Support\Site\Seo::breadcrumbs([['KinderDesk', route('home')], [__('Yangiliklar'), route('news.index')], [$post->title(), $post->url()]]);
@endphp
<script type="application/ld+json">{!! \App\Support\Site\Seo::json($ldPost) !!}</script>
<script type="application/ld+json">{!! \App\Support\Site\Seo::json($ldCrumbs) !!}</script>
@endpush
@section('content')
@php $isAdmin = (bool) auth()->user()?->is_super_admin; @endphp
<article class="max-w-3xl mx-auto px-4 py-10 sm:py-14">
    <a href="{{ route('news.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> Yangiliklar</a>

    @if(! $post->isVisible())
        <p class="mt-4 rounded-xl bg-sun-50 text-sun-700 px-4 py-3 text-sm font-semibold">Bu post hali saytda ko'rinmaydi (qoralama yoki rejalashtirilgan). Siz uni super admin sifatida ko'ryapsiz. <a class="underline" href="{{ route('admin.posts.edit', $post) }}">Tahrirlash</a></p>
    @endif

    <div class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-ink-mute">
        @if($post->published_at)<time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('d.m.Y, H:i') }}</time>@endif
        @if($post->edited_at)<span>{{ __('Tahrirlangan') }}: {{ $post->edited_at->format('d.m.Y, H:i') }}</span>@endif
        <span>{{ $post->readMinutes() }} {{ __("daq. o'qish") }}</span>
    </div>
    <h1 class="mt-3 font-display text-3xl sm:text-4xl font-semibold leading-tight text-balance">{{ $post->title() }}</h1>

    @if($post->type === 'image' && $post->coverUrl())
        <img src="{{ $post->coverUrl() }}" alt="{{ $post->title() }}" class="mt-6 w-full rounded-2xl border border-line object-cover">
    @elseif($post->type === 'video' && $post->embedUrl())
        <div class="mt-6 aspect-video overflow-hidden rounded-2xl border border-line bg-ink">
            <iframe class="size-full" src="{{ str_replace('autoplay=1', 'autoplay=0', $post->embedUrl()) }}" title="{{ $post->title() }}" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    @endif

    <div class="prose-kd mt-6">{!! $post->body() !!}</div>
</article>

{{-- ============ Izohlar ============ --}}
<section id="izohlar" class="max-w-3xl mx-auto px-4 pb-14 scroll-mt-20">
    <div class="border-t border-line pt-8">
        <h2 class="font-display text-2xl font-semibold">Izohlar @if($comments->count())<span class="text-ink-mute">({{ $comments->count() }})</span>@endif</h2>

        @if($post->comments_enabled)
            <form method="post" action="{{ route('news.comment', $post->slug) }}" class="card card-pad mt-5 space-y-3" novalidate>
                @csrf
                <input type="hidden" name="t" value="{{ $formToken }}">
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                @guest
                    <div>
                        <label class="label" for="c-name">Ismingiz</label>
                        <input id="c-name" name="name" value="{{ old('name') }}" class="input @error('name') is-invalid @enderror" maxlength="60" required autocomplete="given-name">
                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @else
                    <p class="text-sm font-medium text-ink-soft">{{ __('Izoh') }}: <b>{{ auth()->user()->name }}</b></p>
                @endguest
                <div>
                    <label class="label" for="c-body">Izoh</label>
                    <textarea id="c-body" name="body" rows="4" maxlength="1000" class="input @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
                    @error('body')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="help">Izohingiz tekshiruvdan so'ng ko'rinadi. Havolalar va reklama o'chiriladi.</p>
                </div>
                <button class="btn btn-primary">Izoh yuborish</button>
            </form>
        @else
            <p class="mt-4 text-sm font-medium text-ink-mute">Bu postda izohlar o'chirilgan.</p>
        @endif

        <ul class="mt-6 space-y-4">
            @forelse($comments as $c)
                <li class="card card-pad" id="c{{ $c->id }}">
                    @include('public.news._comment', ['c' => $c, 'isAdmin' => $isAdmin])
                    @foreach($c->replies as $r)
                        <div class="mt-4 ml-2 border-l-2 border-teal-200 pl-4" id="c{{ $r->id }}">@include('public.news._comment', ['c' => $r, 'isAdmin' => $isAdmin])</div>
                    @endforeach
                </li>
            @empty
                @if($post->comments_enabled)<li class="text-sm font-medium text-ink-mute">Hali izoh yo'q. Birinchi bo'ling.</li>@endif
            @endforelse
        </ul>
    </div>
</section>

@if($related->count())
<section class="max-w-6xl mx-auto px-4 pb-6">
    <h2 class="font-display text-2xl font-semibold mb-5">Boshqa yangiliklar</h2>
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($related as $post)@include('public.news._card', ['post' => $post])@endforeach
    </div>
</section>
@endif
@endsection
