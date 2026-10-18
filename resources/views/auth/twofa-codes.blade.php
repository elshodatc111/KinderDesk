@extends('layouts.auth')
@section('title', 'Zaxira kodlar')
@section('lead', "Telegramga kira olmasangiz, shu kodlardan foydalaning")
@section('content')
<p class="text-sm font-medium mb-3">{{ __("Har bir kod faqat bir marta ishlaydi. Ularni xavfsiz joyda saqlang — keyin ko'rsatilmaydi.") }}</p>
<div class="grid grid-cols-2 gap-2 mb-5 font-mono text-base font-semibold">
    @foreach($codes as $c)<div class="rounded-lg border border-line bg-cloud px-3 py-2 text-center">{{ $c }}</div>@endforeach
</div>
<a href="{{ $next }}" class="btn btn-primary btn-lg btn-block">{{ __('Saqlab oldim, davom etish') }}</a>
@endsection
