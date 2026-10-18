@extends('layouts.auth')
@section('title', 'Shartlarga rozilik')
@section('lead', "Davom etish uchun hujjatlar bilan tanishib, rozilik bering")
@section('content')
<p class="text-sm text-ink-soft mb-4">{{ __("Matnlar yangilangan yoki siz hali rozilik bermagansiz. Rozilik bermasdan tizimdan foydalanib bo'lmaydi.") }}</p>
<ul class="space-y-2 mb-5">
    @foreach($pages as $p => $label)
        <li><a class="font-semibold text-teal-700 hover:underline" target="_blank" href="{{ route('legal', $p) }}">{{ __($label) }}</a></li>
    @endforeach
</ul>
<form method="post" action="{{ route('consent.accept') }}" class="space-y-4">@csrf
    <label class="flex items-start gap-2 text-sm font-medium"><input type="checkbox" name="agree" value="1" class="check mt-0.5" required> <span>{{ __("Oferta, Maxfiylik siyosati va Foydalanish shartlariga roziman") }}</span></label>
    @error('agree')<p class="field-error">{{ $message }}</p>@enderror
    <button class="btn btn-primary btn-lg btn-block">{{ __('Roziman, davom etish') }}</button>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button class="text-sm text-ink-mute hover:underline">{{ __('Chiqish') }}</button></form>
@endsection
