@extends('layouts.app')
@section('title', $employee->exists ? 'Xodimni tahrirlash' : 'Yangi xodim')
@section('content')
<form method="post" action="{{ $employee->exists ? route('app.employees.update', $employee) : route('app.employees.store') }}" class="card card-pad w-full space-y-5" x-data="{ login: {{ ($employee->user_id || old('has_login')) ? 'true' : 'false' }} }">
    @csrf @if($employee->exists) @method('put') @endif
    @include('partials.blacklist-warning')
    @if(request('lead') || old('lead_id'))<input type="hidden" name="lead_id" value="{{ (int) (request('lead') ?: old('lead_id')) }}">@endif
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">To'liq ism</label><input name="full_name" value="{{ old('full_name', $employee->full_name) }}" class="input" required>@error('full_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Lavozim</label><select name="role_id" class="select" required><option value="">{{ __('Tanlang') }}</option>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id',$employee->role_id)==$r->id)>{{ $r->name }}</option>@endforeach</select>@error('role_id')<p class="field-error">{{ $message }}</p>@enderror
            @can('roles.manage')<p class="help"><a class="underline" href="{{ route('app.settings.index', ['tab' => 'roles']) }}">{{ __('Lavozimlarni sozlash') }}</a></p>@endcan</div>
        <div><label class="label">{{ __('Lavozim izohi') }}</label><input name="position_note" value="{{ old('position_note', $employee->position_note) }}" class="input"></div>
        <div><label class="label">Telefon</label><input name="phone" value="{{ old('phone', $employee->phone ? format_phone($employee->phone) : '') }}" class="input" inputmode="tel">@error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Tug'ilgan sana</label><input type="date" name="birth_date" value="{{ old('birth_date', $employee->birth_date?->toDateString()) }}" class="input"></div>
        <div><label class="label">Pasport</label><input name="passport" value="{{ old('passport', $employee->passport) }}" class="input"></div>
        <div><label class="label">Ishga kirgan sana</label><input type="date" name="hired_on" value="{{ old('hired_on', $employee->hired_on?->toDateString()) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Manzil</label><input name="address" value="{{ old('address', $employee->address) }}" class="input"></div>
    </div>

    @if ($payroll)
    <fieldset class="rounded-2xl border border-line p-4 space-y-4"><legend class="px-2 font-semibold">Ish haqi</legend>
        <p class="help">Bog'chaning umumiy usuli: <b>{{ ['fixed'=>'Belgilangan oylik','attendance'=>'Davomadga qarab','hybrid'=>'Aralash'][$strategy] }}</b>. Kerak bo'lsa, shu xodim uchun boshqa usul tanlang.</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="label">Usul</label><select name="salary_type" class="select"><option value="">Bog'cha sozlamasi bo'yicha</option><option value="fixed" @selected(old('salary_type',$employee->salary_type)==='fixed')>Belgilangan oylik</option><option value="attendance" @selected(old('salary_type',$employee->salary_type)==='attendance')>Davomadga qarab</option><option value="hybrid" @selected(old('salary_type',$employee->salary_type)==='hybrid')>Aralash</option></select></div>
            @if(feature('pay_schemes'))
            <div><label class="label">{{ __('Ish haqi sxemasi') }}</label><select name="scheme_key" class="select">
                @foreach(['' => __('Avtomatik (lavozim bo\'yicha)'), 'group' => __('Guruh: tarbiyachi / yordamchi'), 'cook' => __('Oshpaz'), 'admin' => __('Administrator'), 'pedagog' => __('Pedagog (dars / oylik)'), 'fixed' => __('Qat\'iy oylik')] as $k => $l)<option value="{{ $k }}" @selected(old('scheme_key', $employee->scheme_key) === ($k ?: null) || (old('scheme_key', $employee->scheme_key) === null && $k === ''))>{{ $l }}</option>@endforeach</select></div>
            @endif
            <div><label class="label">Oylik (so'm)</label><input name="base_salary" type="number" min="0" value="{{ old('base_salary', $employee->base_salary) }}" class="input"></div>
            <div><label class="label">Bir kunlik stavka (so'm)</label><input name="daily_rate" type="number" min="0" value="{{ old('daily_rate', $employee->daily_rate) }}" class="input"><p class="help">Davomadga qarab hisoblashda. 0 bo'lsa oylik / ish kunlari.</p></div>
            <div><label class="label">Har bir bola uchun qo'shimcha (so'm)</label><input name="kpi_per_kid" type="number" min="0" value="{{ old('kpi_per_kid', $employee->kpi_per_kid) }}" class="input"><p class="help">Aralash usulda: guruhdagi bolalar soniga.</p></div>
        </div></fieldset>
    @endif

    <div class="rounded-2xl border border-line p-4 space-y-3">
        <label class="flex items-center justify-between gap-3 font-semibold">Tizimga kirish huquqi <span class="switch"><input type="checkbox" name="has_login" value="1" x-model="login"><span></span></span></label>
        <div x-show="login" x-cloak class="grid sm:grid-cols-2 gap-4">
            <p class="help sm:col-span-2">{{ __("Tizimdagi ruxsatlar xodimning lavozimi bo'yicha beriladi. Ularni Sozlamalar → Lavozimlar bo'limida belgilaysiz.") }}</p>
            @unless($employee->user_id)<div><label class="label">Parol</label><input type="password" name="password" class="input" autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror<p class="help">Telefon raqam bilan kiradi</p></div>@endunless
        </div>
    </div>
    <div><label class="label">Izoh</label><textarea name="note" rows="2" class="textarea">{{ old('note', $employee->note) }}</textarea></div>
    <div class="flex gap-3"><button class="btn btn-primary">Saqlash</button><a href="{{ route('app.employees.index') }}" class="btn">Bekor qilish</a></div>
</form>
@endsection
