@extends('layouts.app')
@section('title', $club->exists ? __("To'garakni tahrirlash") : __("Yangi to'garak"))
@section('content')
<form method="post" action="{{ $club->exists ? route('app.clubs.update', $club) : route('app.clubs.store') }}" class="card card-pad w-full space-y-4">@csrf @if($club->exists) @method('put') @endif
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">{{ __("To'garak nomi") }}</label><input name="name" value="{{ old('name', $club->name) }}" class="input" required placeholder="{{ __('Masalan: Ingliz tili') }}">@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">{{ __("To'lov turi") }}</label>
            <select name="billing" class="select"><option value="monthly" @selected(old('billing',$club->billing)==='monthly')>{{ __('Oylik (har oy bir marta)') }}</option><option value="per_session" @selected(old('billing',$club->billing)==='per_session')>{{ __("Har bir mashg'ulot uchun (kelgan kuni)") }}</option></select></div>
        <div><label class="label">{{ __("To'lov summasi (so'm)") }}</label><input name="fee" type="number" min="0" value="{{ old('fee', $club->fee) }}" class="input" required>@error('fee')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">{{ __("O'qituvchi") }}</label><select name="teacher_id" class="select"><option value="">—</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected(old('teacher_id',$club->teacher_id)==$t->id)>{{ $t->full_name }}</option>@endforeach</select></div>
        <div><label class="label">{{ __('Jadval') }}</label><input name="schedule" value="{{ old('schedule', $club->schedule) }}" class="input" placeholder="{{ __('Du, Chor, Juma 15:00') }}"></div>
        <div class="sm:col-span-2"><label class="label">{{ __('Izoh') }}</label><textarea name="description" rows="2" class="textarea">{{ old('description', $club->description) }}</textarea></div>
    </div>
    <p class="help">{{ __("To'garak to'lovi bolaning balansidan alohida qator bo'lib yechiladi. Oylik to'garak bolaga yozilgan zahoti joriy oy uchun hisoblanadi.") }}</p>
    <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><a href="{{ route('app.clubs.index') }}" class="btn">{{ __('Bekor qilish') }}</a></div>
</form>
@endsection
