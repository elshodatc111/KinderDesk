<svg viewBox="0 0 420 300" class="w-full h-auto" role="img" aria-label="{{ __('Guruhlar kesimida davomad kartalari') }}">
    <rect x="0" y="0" width="420" height="300" rx="18" class="fill-leaf-50"/>
    @foreach ([[24, 'Kaptarlar', 18, 20, 'leaf'], [150, 'Quyoshlar', 15, 17, 'sky'], [276, 'Yulduzlar', 12, 16, 'sun']] as $i => [$x, $name, $a, $n, $c])
        <g transform="translate({{ $x }} 28)">
            <rect width="120" height="130" rx="14" class="fill-white stroke-line" stroke-width="1.5"/>
            <circle cx="60" cy="52" r="26" fill="none" class="stroke-ink/10" stroke-width="9"/>
            <circle cx="60" cy="52" r="26" fill="none" class="stroke-{{ $c }}-500" stroke-width="9" stroke-linecap="round" stroke-dasharray="{{ round(163.4 * $a / $n, 1) }} 200" transform="rotate(-90 60 52)"/>
            <text x="60" y="57" text-anchor="middle" class="fill-ink" style="font:700 15px Inter,system-ui">{{ $a }}/{{ $n }}</text>
            <rect x="22" y="96" width="76" height="8" rx="4" class="fill-ink/15"/>
            <rect x="34" y="112" width="52" height="6" rx="3" class="fill-ink/10"/>
        </g>
    @endforeach
    <g transform="translate(24 176)">
        <rect width="372" height="96" rx="14" class="fill-white stroke-line" stroke-width="1.5"/>
        @foreach ([[0, 'leaf', 'Keldi'], [1, 'berry', 'Kelmadi'], [2, 'sun', 'Kasal']] as [$k, $c, $l])
            <g transform="translate({{ 20 + $k * 118 }} 22)">
                <rect width="104" height="30" rx="15" class="fill-{{ $c }}-100"/>
                <circle cx="17" cy="15" r="6" class="fill-{{ $c }}-500"/>
                <rect x="30" y="11" width="56" height="8" rx="4" class="fill-{{ $c }}-700/40"/>
            </g>
        @endforeach
        @foreach (range(0, 11) as $d)
            <rect x="{{ 20 + $d * 29.5 }}" y="{{ 66 - [14, 18, 12, 20, 16, 22, 15, 19, 21, 17, 23, 20][$d] }}" width="18" height="{{ [14, 18, 12, 20, 16, 22, 15, 19, 21, 17, 23, 20][$d] }}" rx="4" class="fill-teal-400/{{ $d === 11 ? '100' : '50' }}"/>
        @endforeach
    </g>
</svg>
