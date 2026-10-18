<svg viewBox="0 0 420 300" class="w-full h-auto" role="img" aria-label="{{ __('Ish haqi hisob varaqasi') }}">
    <rect width="420" height="300" rx="18" class="fill-sky-50"/>
    <g transform="translate(70 24) rotate(-3 140 130)">
        <rect width="280" height="252" rx="14" class="fill-white stroke-line" stroke-width="1.5"/>
        <circle cx="38" cy="38" r="16" class="fill-sky-500"/><rect x="64" y="26" width="90" height="9" rx="4.5" class="fill-ink/25"/><rect x="64" y="42" width="56" height="7" rx="3.5" class="fill-ink/12"/>
        <rect x="206" y="26" width="58" height="22" rx="11" class="fill-leaf-100"/><rect x="218" y="34" width="34" height="6" rx="3" class="fill-leaf-700/50"/>
        @foreach ([['Oklad', 3200000, 'ink'], ['Davomad bonusi', 480000, 'leaf'], ['Tadbir bonusi', 150000, 'leaf'], ['Jarima', -60000, 'berry']] as $i => [$l, $v, $c])
            <g transform="translate(24 {{ 84 + $i * 30 }})">
                <rect width="{{ 70 + strlen($l) * 3 }}" height="8" rx="4" class="fill-ink/18"/>
                <rect x="{{ 232 - 6 * strlen(number_format(abs($v), 0, '', ' ')) }}" width="{{ 6 * strlen(number_format(abs($v), 0, '', ' ')) }}" height="8" rx="4" class="fill-{{ $c === 'ink' ? 'ink/30' : $c.'-500/70' }}"/>
            </g>
        @endforeach
        <line x1="24" y1="214" x2="256" y2="214" class="stroke-line" stroke-width="1.5" stroke-dasharray="4 4"/>
        <rect x="24" y="226" width="64" height="9" rx="4.5" class="fill-ink/25"/>
        <rect x="150" y="222" width="106" height="18" rx="9" class="fill-sky-500"/>
    </g>
</svg>
