@extends('layouts.app')
@section('title', $group->exists ? __('Guruhni tahrirlash') : __('Yangi guruh'))
@section('content')
@php $type = old('type', $group->type ?: 'main'); $extra = feature('extra_groups'); @endphp
<form method="post" action="{{ $group->exists ? route('app.groups.update', $group) : route('app.groups.store') }}" class="card card-pad w-full space-y-5" x-data="{ type: '{{ $type }}' }">@csrf @if($group->exists) @method('put') @endif
    @if($extra)
    <div>
        <label class="label">{{ __('Guruh turi') }}</label>
        <div class="grid sm:grid-cols-3 gap-3">
            @foreach(['main' => [__('Asosiy guruh'), '09:00–17:00 · '.__('oylik to\'lov')], 'evening' => [__('Kechki guruh'), '17:00–20:00 · '.__('qolgan kun uchun')], 'saturday' => [__('Shanba guruhi'), __('shanba') . ' · ' . __('kelgan kun uchun')]] as $k => [$t, $d])
                <label class="choice" :class="type==='{{ $k }}' ? 'is-on' : ''"><input type="radio" name="type" value="{{ $k }}" x-model="type" class="sr-only" @if($group->exists) @disabled(($group->type ?: 'main') !== $k) @endif><span class="font-medium block">{{ $t }}</span><span class="text-xs font-semibold text-ink-soft">{{ $d }}</span></label>
            @endforeach
        </div>
        @if($group->exists)<input type="hidden" name="type" value="{{ $group->type }}">@endif
    </div>
    @else<input type="hidden" name="type" value="main">@endif
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">{{ __('Guruh nomi') }}</label><input name="name" value="{{ old('name', $group->name) }}" class="input" required placeholder="{{ __('Masalan: Quyoshchalar') }}">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div x-show="type==='main'"><label class="label">{{ __('Guruh o\'lchami') }}</label>
            <select name="size" class="input">@foreach(['large' => __('Katta guruh').' — '.money((int) tenant()->setting('groups.price_large')), 'small' => __('Kichik guruh').' — '.money((int) tenant()->setting('groups.price_small'))] as $k => $l)<option value="{{ $k }}" @selected(old('size', $group->size ?: 'large') === $k)>{{ $l }}</option>@endforeach</select>
            <p class="help">{{ __("Ish haqi sxemasi guruh o'lchamiga qarab tanlanadi. Narx bo'sh qoldirilsa, Sozlamalardagi katta/kichik guruh narxi qo'yiladi.") }}</p></div>
        <div x-show="type==='main'"><label class="label">{{ __("Oylik to'lov (so'm)") }}</label><input name="monthly_fee" type="number" min="0" value="{{ old('monthly_fee', $group->monthly_fee) }}" class="input">@error('monthly_fee')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div x-show="type!=='main'" x-cloak><label class="label">{{ __("Bir kunlik to'lov (so'm)") }}</label><input name="daily_fee" type="number" min="0" value="{{ old('daily_fee', $group->daily_fee) }}" class="input">
            <p class="help">{{ __("Bola faqat kelgan kuni uchun to'laydi. Summa avtomatik bolaning balansidan yechiladi.") }}</p>@error('daily_fee')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">{{ __("Sig'im (bola soni)") }}</label><input name="capacity" type="number" min="1" value="{{ old('capacity', $group->capacity) }}" class="input"></div>
        <div class="grid grid-cols-2 gap-3 sm:col-span-2">
            <div><label class="label">{{ __('Boshlanish vaqti') }}</label><input type="time" name="start_time" value="{{ old('start_time', $group->start_time ? substr($group->start_time,0,5) : '') }}" class="input"></div>
            <div><label class="label">{{ __('Tugash vaqti') }}</label><input type="time" name="end_time" value="{{ old('end_time', $group->end_time ? substr($group->end_time,0,5) : '') }}" class="input">@error('end_time')<p class="field-error">{{ $message }}</p>@enderror</div>
        </div>
        <div><label class="label">{{ __("Yosh oralig'i") }}</label><input name="age_range" value="{{ old('age_range', $group->age_range) }}" class="input" placeholder="3-4 {{ __('yosh') }}"></div>
        <div><label class="label">{{ __('Xona') }}</label><input name="room" value="{{ old('room', $group->room) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">{{ __('Izoh') }}</label><textarea name="description" rows="2" class="textarea">{{ old('description', $group->description) }}</textarea></div>
    </div>
    <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><a href="{{ route('app.groups.index') }}" class="btn">{{ __('Bekor qilish') }}</a></div>
</form>
@endsection
