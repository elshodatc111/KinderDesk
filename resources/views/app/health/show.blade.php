@extends('layouts.app')
@section('title', $kid->full_name)
@section('content')
@php $edit = auth()->user()->can('kids.manage'); @endphp
<div class="flex items-center gap-3 mb-4"><a class="btn btn-sm" href="{{ route('app.health.index') }}">← {{ __("Ro'yxat") }}</a><span class="text-sm text-ink-soft">{{ $kid->currentLink?->group?->name }}</span></div>
<div class="grid xl:grid-cols-2 gap-6 items-start">
<div class="card card-pad">
    <h2 class="section-title mb-3">{{ __("Sog'liq") }}</h2>
    @if($edit)
    <form method="post" action="{{ route('app.health.update', $kid) }}" class="space-y-3">@csrf @method('put')
        <div><label class="label">{{ __('Allergiya') }}</label><textarea name="allergies" rows="2" class="textarea">{{ old('allergies', $h?->allergies) }}</textarea></div>
        <div><label class="label">{{ __('Kasallik / holat') }}</label><textarea name="conditions" rows="2" class="textarea">{{ old('conditions', $h?->conditions) }}</textarea></div>
        <div><label class="label">{{ __('Dori-darmon') }}</label><textarea name="medications" rows="2" class="textarea">{{ old('medications', $h?->medications) }}</textarea></div>
        <div><label class="label">{{ __('Izoh') }}</label><textarea name="notes" rows="2" class="textarea">{{ old('notes', $h?->notes) }}</textarea></div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div><label class="label">{{ __('Shifokor') }}</label><input name="doctor_name" class="input" value="{{ old('doctor_name', $h?->doctor_name) }}"></div>
            <div><label class="label">{{ __('Shifokor telefoni') }}</label><input name="doctor_phone" class="input" value="{{ old('doctor_phone', $h?->doctor_phone) }}" inputmode="tel"></div></div>
        <button class="btn btn-primary">{{ __('Saqlash') }}</button>
        <p class="help">{{ __("Bu ma'lumotlar bazada shifrlangan. AI yordamchiga hech qachon yuborilmaydi.") }}</p>
    </form>
    @else
    <dl class="space-y-3 text-sm">
        @foreach(['allergies' => 'Allergiya', 'conditions' => 'Kasallik / holat', 'medications' => 'Dori-darmon', 'notes' => 'Izoh', 'doctor_name' => 'Shifokor', 'doctor_phone' => 'Shifokor telefoni'] as $f => $l)
        <div><dt class="font-semibold text-ink-soft">{{ __($l) }}</dt><dd class="font-medium">{{ $h?->$f ?: '—' }}</dd></div>@endforeach
    </dl>
    @endif
</div>
<div class="card card-pad">
    <h2 class="section-title mb-3">{{ __('Bolani olib ketishga ruxsat etilganlar') }}</h2>
    <ul class="divide-y divide-line mb-4">
    @forelse($kid->pickups as $p)
        <li class="py-2 flex items-center justify-between gap-3 text-sm"><span><b>{{ $p->name }}</b> <span class="text-ink-mute">{{ $p->relation }}</span> @if($p->phone)· {{ $p->phone }}@endif</span>
            @if($edit)<form method="post" action="{{ route('app.health.pickup.destroy', [$kid, $p]) }}" onsubmit="return confirm('{{ __("O'chirilsinmi?") }}')">@csrf @method('delete')<button class="text-xs font-semibold text-danger hover:underline">{{ __("O'chirish") }}</button></form>@endif</li>
    @empty<li class="py-2 text-sm text-ink-mute">{{ __("Hali kiritilmagan") }}</li>@endforelse
    </ul>
    @if($edit)
    <form method="post" action="{{ route('app.health.pickup.store', $kid) }}" class="grid sm:grid-cols-[1.2fr_1fr_1fr_auto] gap-2 items-end">@csrf
        <div><label class="label">{{ __('Ism') }}</label><input name="name" class="input" required></div>
        <div><label class="label">{{ __('Kim bo\'ladi') }}</label><input name="relation" class="input" placeholder="{{ __('Buvisi, amaki...') }}"></div>
        <div><label class="label">{{ __('Telefon') }}</label><input name="phone" class="input" inputmode="tel"></div>
        <button class="btn btn-primary">{{ __("Qo'shish") }}</button>
    </form>
    @endif
</div>
</div>
@endsection
