<svg viewBox="0 0 420 300" class="w-full h-auto" role="img" aria-label="{{ __('Arizalar voronkasi') }}">
    <rect width="420" height="300" rx="18" class="fill-sun-50"/>
    @foreach ([['berry', 340, 'Yangi'], ['sky', 280, 'Aloqa'], ['sun', 220, 'Tashrif'], ['leaf', 160, 'Qabul']] as $i => [$c, $w, $l])
        <g transform="translate({{ (420 - $w) / 2 }} {{ 32 + $i * 62 }})">
            <rect width="{{ $w }}" height="50" rx="14" class="fill-{{ $c }}-500" opacity="{{ $c === 'sun' ? '1' : '.92' }}"/>
            <rect x="18" y="19" width="{{ 40 + $i * 6 }}" height="9" rx="4.5" fill="#fff" opacity=".85"/>
            <circle cx="{{ $w - 28 }}" cy="25" r="13" fill="#fff" opacity=".9"/>
        </g>
    @endforeach
    <g transform="translate(332 30)"><rect width="66" height="34" rx="17" class="fill-white stroke-line" stroke-width="1.5"/><circle cx="18" cy="17" r="7" class="fill-berry-500"/><rect x="31" y="13" width="26" height="8" rx="4" class="fill-ink/20"/></g>
</svg>
