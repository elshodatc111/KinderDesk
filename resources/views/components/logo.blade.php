@props(['size' => 'md', 'mark' => true, 'class' => ''])
@php
    $text = ['sm' => 'text-lg', 'md' => 'text-2xl', 'lg' => 'text-4xl'][$size] ?? 'text-2xl';
    $icon = ['sm' => 28, 'md' => 38, 'lg' => 56][$size] ?? 38;
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 $class"]) }}>
    @if ($mark)<x-logo-mark :size="$icon" />@endif
    <span class="font-brand font-semibold tracking-tight leading-none {{ $text }}"><span class="text-teal-500">Kinder</span><span class="text-marigold-500">Desk</span></span>
</span>
