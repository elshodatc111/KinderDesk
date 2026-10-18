<div class="seg !p-0.5 text-xs" role="group" aria-label="Language">
    @foreach (['ru' => 'RU', 'uz' => 'UZ'] as $code => $label)
        <a href="{{ route('locale', $code) }}" class="px-2.5 py-1 rounded-md font-semibold {{ app()->getLocale() === $code ? 'bg-white text-teal-700 shadow-sm' : 'text-ink-soft hover:text-ink' }}" hreflang="{{ $code }}">{{ $label }}</a>
    @endforeach
</div>
