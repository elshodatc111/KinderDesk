<svg viewBox="0 0 420 300" class="w-full h-auto" role="img" aria-label="{{ __('Statistika diagrammalari va AI hisobot') }}">
    <rect width="420" height="300" rx="18" class="fill-teal-50"/>
    <g transform="translate(26 26)">
        <rect width="250" height="150" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <polyline points="20,112 62,92 104,100 146,62 188,70 230,34" fill="none" class="stroke-teal-500" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <polyline points="20,124 62,116 104,118 146,98 188,104 230,84" fill="none" class="stroke-marigold-500" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="2 9"/>
        @foreach ([[20, 112], [62, 92], [104, 100], [146, 62], [188, 70], [230, 34]] as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="fill-white stroke-teal-500" stroke-width="3"/>@endforeach
        <rect x="20" y="20" width="70" height="9" rx="4.5" class="fill-ink/20"/>
    </g>
    <g transform="translate(292 26)"><rect width="102" height="150" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <circle cx="51" cy="62" r="30" fill="none" class="stroke-leaf-500" stroke-width="12" stroke-dasharray="120 200" transform="rotate(-90 51 62)"/>
        <circle cx="51" cy="62" r="30" fill="none" class="stroke-sun-500" stroke-width="12" stroke-dasharray="40 200" stroke-dashoffset="-122" transform="rotate(-90 51 62)"/>
        <circle cx="51" cy="62" r="30" fill="none" class="stroke-berry-500" stroke-width="12" stroke-dasharray="22 200" stroke-dashoffset="-164" transform="rotate(-90 51 62)"/>
        <rect x="20" y="112" width="62" height="8" rx="4" class="fill-ink/15"/></g>
    <g transform="translate(26 194)"><rect width="368" height="80" rx="16" class="fill-white stroke-line" stroke-width="1.5"/>
        <path d="M44 28l5 11 11 5-11 5-5 11-5-11-11-5 11-5z" class="fill-grape-500"/>
        <rect x="80" y="22" width="220" height="9" rx="4.5" class="fill-ink/25"/><rect x="80" y="40" width="270" height="8" rx="4" class="fill-ink/12"/><rect x="80" y="55" width="180" height="8" rx="4" class="fill-ink/12"/></g>
</svg>
