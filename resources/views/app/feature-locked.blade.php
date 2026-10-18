@extends('layouts.app')
@section('title', 'Bu bo\'lim yopiq')
@section('content')
@php
    $def = config('kinderdesk.features.'.$feature, []);
    $name = $def['label'] ?? $feature;
    $needed = collect(config('kinderdesk.editions'))->first(fn($e) => in_array($feature, $e['features'] ?? []) || in_array('*', $e['features'] ?? []));
    $needed = $needed['name'] ?? 'yuqoriroq';
@endphp
<div class="empty max-w-xl mx-auto">
    <x-icon name="lock" class="size-12 text-marigold-600" />
    <h2 class="text-xl font-semibold mt-3">{{ $name }} sizning nashringizda yo'q</h2>
    <p class="text-ink-soft font-semibold mt-2">Bu imkoniyat {{ $needed }} nashrda mavjud. Nashrni o'zgartirib, darrov foydalanishni boshlashingiz mumkin.</p>
    @can('subscription.manage')<a href="{{ route('app.subscription.index') }}" class="btn btn-accent mt-4">Nashrlarni ko'rish</a>@endcan
</div>
@endsection
