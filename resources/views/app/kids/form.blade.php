@extends('layouts.app')
@section('title', $kid->exists ? 'Tahrirlash: '.$kid->full_name : "Yangi bola")
@section('content')
<form method="post" action="{{ $kid->exists ? route('app.kids.update', $kid) : route('app.kids.store') }}" class="card card-pad w-full space-y-5">
    @csrf @if($kid->exists) @method('put') @endif
    @include('partials.blacklist-warning')
    @if(request('lead'))<input type="hidden" name="lead_id" value="{{ (int) request('lead') }}">@endif
    @if(old('lead_id'))<input type="hidden" name="lead_id" value="{{ (int) old('lead_id') }}">@endif
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">To'liq ism (F.I.Sh.)</label><input name="full_name" value="{{ old('full_name', $kid->full_name) }}" class="input" required>@error('full_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Jinsi</label><select name="gender" class="select"><option value="male" @selected(old('gender',$kid->gender)==='male')>O'g'il bola</option><option value="female" @selected(old('gender',$kid->gender)==='female')>Qiz bola</option></select></div>
        <div><label class="label">Tug'ilgan sana</label><input type="date" name="birth_date" value="{{ old('birth_date', $kid->birth_date?->toDateString()) }}" max="{{ today()->toDateString() }}" class="input" required>@error('birth_date')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Tug'ilganlik guvohnomasi</label><input name="certificate_no" value="{{ old('certificate_no', $kid->certificate_no) }}" class="input">@error('certificate_no')<p class="field-error">{{ $message }}</p>@enderror</div>
        @unless($kid->exists)
        <div><label class="label">Guruh</label><select name="group_id" class="select" required><option value="">Tanlang</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(old('group_id')==$g->id)>{{ $g->name }} — {{ money($g->monthly_fee) }} so'm</option>@endforeach</select>@error('group_id')<p class="field-error">{{ $message }}</p>@enderror
            @if($groups->isEmpty())<p class="help">Avval <a class="underline font-medium" href="{{ route('app.groups.create') }}">guruh yarating</a></p>@endif</div>
        <div><label class="label">Qabul sanasi</label><input type="date" name="enrolled_on" value="{{ old('enrolled_on', $kid->enrolled_on?->toDateString()) }}" class="input"></div>
        @endunless
        @php $g1 = $kid->exists ? $kid->guardians->firstWhere('position', 1) : null; $g2 = $kid->exists ? $kid->guardians->firstWhere('position', 2) : null; $rels = \App\Models\KidGuardian::relations(); @endphp
        <div class="sm:col-span-2"><h3 class="font-semibold">{{ __('Vasiy') }} 1</h3></div>
        <div><label class="label">Ota-ona ismi</label><input name="parent_name" value="{{ old('parent_name', $kid->parent_name) }}" class="input"></div>
        <div><label class="label">Munosabat</label><select name="g1_relation" class="select">@foreach($rels as $k => $v)<option value="{{ $k }}" @selected(old('g1_relation', $g1?->relation ?? 'other') === $k)>{{ __($v) }}</option>@endforeach</select></div>
        <div><label class="label">Ota-ona telefoni</label><input name="parent_phone" value="{{ old('parent_phone', $kid->parent_phone ? format_phone($kid->parent_phone) : '') }}" class="input" inputmode="tel" placeholder="90 123 45 67" required>@error('parent_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Qo'shimcha telefon</label><input name="parent_phone2" value="{{ old('parent_phone2', $kid->parent_phone2 ? format_phone($kid->parent_phone2) : '') }}" class="input" inputmode="tel"></div>
        @if(feature('guardians2'))
        <div class="sm:col-span-2 flex flex-wrap gap-x-5 gap-y-1 text-sm"><input type="hidden" name="g1_sms" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="g1_sms" value="1" @checked(old('g1_sms', $g1?->sms ?? true))> {{ __('SMS oladi') }}</label>
            <input type="hidden" name="g1_pickup" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="g1_pickup" value="1" @checked(old('g1_pickup', $g1?->pickup ?? true))> {{ __('Olib ketishga ruxsat') }}</label></div>
        <div class="sm:col-span-2 border-t border-line pt-3"><h3 class="font-semibold">{{ __('Vasiy') }} 2 <span class="text-ink-mute font-normal text-sm">({{ __('ixtiyoriy') }})</span></h3></div>
        <div><label class="label">Ism</label><input name="g2_name" value="{{ old('g2_name', $g2?->name) }}" class="input"></div>
        <div><label class="label">Munosabat</label><select name="g2_relation" class="select">@foreach($rels as $k => $v)<option value="{{ $k }}" @selected(old('g2_relation', $g2?->relation ?? 'other') === $k)>{{ __($v) }}</option>@endforeach</select></div>
        <div><label class="label">Telefon</label><input name="g2_phone" value="{{ old('g2_phone', $g2?->phone ? format_phone($g2->phone) : '') }}" class="input" inputmode="tel">@error('g2_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Qo'shimcha telefon</label><input name="g2_phone2" value="{{ old('g2_phone2', $g2?->phone2 ? format_phone($g2->phone2) : '') }}" class="input" inputmode="tel"></div>
        <div class="sm:col-span-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="g2_is_payer" value="1" @checked(old('g2_is_payer', $g2?->is_payer ?? false))> {{ __("Asosiy to'lovchi") }}</label>
            <input type="hidden" name="g2_sms" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="g2_sms" value="1" @checked(old('g2_sms', $g2?->sms ?? true))> {{ __('SMS oladi') }}</label>
            <input type="hidden" name="g2_pickup" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="g2_pickup" value="1" @checked(old('g2_pickup', $g2?->pickup ?? true))> {{ __('Olib ketishga ruxsat') }}</label></div>
        @endif
        <div><label class="label">Shaxsiy oylik narx <span class="font-semibold text-ink-mute">(ixtiyoriy)</span></label><input name="custom_fee" type="number" min="0" value="{{ old('custom_fee', $kid->custom_fee) }}" class="input"><p class="help">Bo'sh qoldirsangiz guruh narxi olinadi</p></div>
        <div class="sm:col-span-2"><label class="label">Manzil</label><input name="address" value="{{ old('address', $kid->address) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Izoh</label><textarea name="note" rows="2" class="textarea">{{ old('note', $kid->note) }}</textarea></div>
    </div>
    <div class="flex gap-3"><button class="btn btn-primary">Saqlash</button><a href="{{ $kid->exists ? route('app.kids.show', $kid) : route('app.kids.index') }}" class="btn">Bekor qilish</a></div>
</form>
@endsection
