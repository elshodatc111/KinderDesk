@extends('layouts.app')
@section('title', 'Yangi tadbir')
@section('content')
<form method="post" action="{{ route('app.events.store') }}" class="card card-pad w-full space-y-4">@csrf
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">{{ __('Tadbir nomi') }}</label><input name="title" value="{{ old('title') }}" class="input" required placeholder="{{ __('Masalan: Delfinariumga ekskursiya') }}">@error('title')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">{{ __('Tadbir sanasi') }}</label><input type="date" name="date" value="{{ old('date', $event->date) }}" min="{{ today()->toDateString() }}" class="input" required>@error('date')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">{{ __("Ro'yxatga olish muddati") }}</label><input type="date" name="deadline" value="{{ old('deadline') }}" class="input">@error('deadline')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="sm:col-span-2"><label class="label">{{ __('Izoh') }}</label><textarea name="note" rows="2" class="textarea">{{ old('note') }}</textarea></div>
    </div>
    <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><a href="{{ route('app.events.index') }}" class="btn">{{ __('Bekor qilish') }}</a></div>
</form>
@endsection
