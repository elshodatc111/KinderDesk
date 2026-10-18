@props(['sections'])
<div class="grid lg:grid-cols-[14rem_1fr] gap-8 max-w-6xl" x-data="{ active: '' }"
     x-init="const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) active = e.target.id }), { rootMargin: '-15% 0px -75% 0px' }); document.querySelectorAll('[data-guide]').forEach(el => io.observe(el))">
    <nav class="lg:sticky lg:top-24 self-start card p-2 text-sm" aria-label="Guide">
        <ul class="space-y-0.5">
            @foreach ($sections as $id => $title)
                <li><a href="#{{ $id }}" class="block px-2.5 py-1.5 rounded-md text-ink-soft hover:bg-cloud" :class="active === '{{ $id }}' && '!bg-teal-50 !text-teal-700 font-semibold'">{{ $title }}</a></li>
            @endforeach
        </ul>
    </nav>
    <article class="prose-kd min-w-0 pb-16">{{ $slot }}</article>
</div>
