<svg viewBox="0 0 420 300" class="w-full h-auto" role="img" aria-label="{{ __('Kassa, to\'lovlar va moliya hisobi') }}">
    <rect width="420" height="300" rx="18" class="fill-marigold-50"/>
    <g transform="translate(26 28)">
        <rect width="230" height="244" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <rect x="20" y="20" width="90" height="10" rx="5" class="fill-ink/20"/>
        <text x="20" y="62" class="fill-ink" style="font:700 24px Inter,system-ui">12 480 000</text>
        <rect x="20" y="72" width="60" height="7" rx="3.5" class="fill-ink/10"/>
        @foreach ([48, 80, 62, 110, 92, 132, 118] as $i => $h)
            <rect x="{{ 22 + $i * 29 }}" y="{{ 220 - $h }}" width="20" height="{{ $h }}" rx="5" class="{{ $i === 5 ? 'fill-marigold-500' : 'fill-teal-400/60' }}"/>
        @endforeach
        <line x1="14" y1="221" x2="216" y2="221" class="stroke-line" stroke-width="1.5"/>
    </g>
    <g transform="translate(276 28)">
        <rect width="120" height="112" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <circle cx="34" cy="38" r="16" class="fill-leaf-100"/><path d="M27 38l5 5 9-10" fill="none" class="stroke-leaf-700" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="58" y="30" width="46" height="8" rx="4" class="fill-ink/20"/><rect x="58" y="44" width="30" height="6" rx="3" class="fill-ink/10"/>
        <rect x="16" y="72" width="88" height="24" rx="12" class="fill-leaf-500"/><text x="60" y="89" text-anchor="middle" fill="#fff" style="font:700 12px Inter,system-ui">+ 450 000</text>
    </g>
    <g transform="translate(276 160)">
        <rect width="120" height="112" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <ellipse cx="60" cy="78" rx="34" ry="9" class="fill-sun-500"/><rect x="26" y="62" width="68" height="16" class="fill-sun-500"/><ellipse cx="60" cy="62" rx="34" ry="9" class="fill-sun-100"/>
        <ellipse cx="60" cy="50" rx="34" ry="9" class="fill-sun-500"/><rect x="26" y="34" width="68" height="16" class="fill-sun-500"/><ellipse cx="60" cy="34" rx="34" ry="9" class="fill-sun-100"/>
        <rect x="30" y="94" width="60" height="7" rx="3.5" class="fill-ink/15"/>
    </g>
</svg>
