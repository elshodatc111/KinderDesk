@php
    $included = collect($features)->filter(fn ($f, $k) => in_array($k, $ed->features ?? []) || ! empty($f['core']));
    $missing = collect($features)->reject(fn ($f, $k) => in_array($k, $ed->features ?? []) || ! empty($f['core']));
    $head = $included->take(8);
    $rest = $included->slice(8);
@endphp
<div x-data="{ all: false }" class="mt-4 text-[13px] leading-snug">
    <ul class="space-y-1">
        @foreach ($head as $key => $f)
            <li class="flex items-start gap-2 text-ink"><x-icon name="check" class="size-3.5 mt-[3px] shrink-0 text-leaf-700" /><span>{{ __($f['label']) }}</span></li>
        @endforeach
        @foreach ($rest as $key => $f)
            <li x-show="all" x-cloak class="flex items-start gap-2 text-ink"><x-icon name="check" class="size-3.5 mt-[3px] shrink-0 text-leaf-700" /><span>{{ __($f['label']) }}</span></li>
        @endforeach
        @foreach ($missing as $key => $f)
            <li x-show="all" x-cloak class="flex items-start gap-2 text-ink-mute"><x-icon name="x" class="size-3.5 mt-[3px] shrink-0" /><span class="line-through decoration-1">{{ __($f['label']) }}</span></li>
        @endforeach
    </ul>
    @if ($rest->isNotEmpty() || $missing->isNotEmpty())
        <button type="button" class="mt-2 text-xs font-semibold text-teal-700 hover:underline" @click="all = !all" :aria-expanded="all">
            <span x-show="!all">{{ __('Barcha imkoniyatlar') }} (+{{ $rest->count() + $missing->count() }})</span><span x-show="all" x-cloak>{{ __('Yig\'ish') }}</span>
        </button>
    @endif
</div>
