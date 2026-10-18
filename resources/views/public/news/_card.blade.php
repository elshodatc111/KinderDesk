@php $img = $post->image(); @endphp
<article class="card group flex flex-col overflow-hidden transition-shadow hover:shadow-[var(--shadow-pop)] motion-reduce:transition-none">
    @if($img)
        <a href="{{ $post->url() }}" tabindex="-1" aria-hidden="true" class="relative block aspect-[16/9] bg-teal-50 overflow-hidden">
            <img src="{{ $img }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
            @if($post->type === 'video')
                <span class="absolute inset-0 grid place-items-center bg-ink/25"><span class="size-12 rounded-full bg-white/95 grid place-items-center text-berry-600 shadow"><x-icon name="play" class="size-5" /></span></span>
            @endif
        </a>
    @endif
    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-ink-mute">
            <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('d.m.Y, H:i') }}</time>
            @if($post->is_pinned)<span class="badge badge-sun">{{ __('Mahkamlangan') }}</span>@endif
        </div>
        <h2 class="mt-2 font-display text-xl font-semibold leading-snug text-balance"><a href="{{ $post->url() }}" class="hover:text-teal-700 focus-visible:outline-2 focus-visible:outline-teal-500">{{ $post->title() }}</a></h2>
        @if($post->excerpt())<p class="mt-2 text-sm font-medium text-ink-soft line-clamp-3">{{ $post->excerpt() }}</p>@endif
        <div class="mt-auto pt-4 flex items-center justify-between text-xs font-semibold text-ink-mute">
            <span>{{ $post->readMinutes() }} {{ __("daq. o'qish") }}</span>
            @isset($post->approved_count)<span class="inline-flex items-center gap-1"><x-icon name="sms" class="size-4" /> {{ $post->approved_count }}</span>@endisset
        </div>
    </div>
</article>
