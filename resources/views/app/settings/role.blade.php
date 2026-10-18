@extends('layouts.app')
@section('title', $role->exists ? $role->name : __('Yangi lavozim'))
@section('content')
<form method="post" action="{{ $role->exists ? route('app.settings.roles.update', $role) : route('app.settings.roles.store') }}" class="w-full space-y-5"
      x-data="{ all(g, on) { document.querySelectorAll('[data-g=\'' + g + '\']').forEach(c => c.checked = on) } }">
    @csrf @if($role->exists) @method('put') @endif
    <div class="card card-pad"><label class="label">{{ __('Lavozim nomi') }}</label>
        <input name="name" class="input" value="{{ old('name', $role->name) }}" placeholder="{{ __('Masalan: Metodist') }}" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
    <p class="help">{{ __("Quyida shu lavozim egasi nimalarni qila olishini belgilang. Belgilanmagan bo'lim xodimga ko'rinmaydi.") }}</p>
    @foreach ($groups as $title => $keys)
    <div class="card card-pad">
        <div class="flex items-center justify-between mb-3"><h2 class="section-title">{{ __($title) }}</h2>
            <span class="text-sm"><button type="button" class="btn-quiet" @click="all(@js($title), true)">{{ __('Hammasi') }}</button> <button type="button" class="btn-quiet" @click="all(@js($title), false)">{{ __('Hech biri') }}</button></span></div>
        <div class="grid sm:grid-cols-2 gap-2">
        @foreach ($keys as $k)
            <label class="choice"><input type="checkbox" class="check" name="permissions[]" value="{{ $k }}" data-g="{{ $title }}" @checked(in_array($k, old('permissions', $role->permissions ?? [])))><span>{{ __(config('kinderdesk.permissions')[$k] ?? $k) }}</span></label>
        @endforeach
        </div>
    </div>
    @endforeach
    <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><a class="btn" href="{{ route('app.settings.index', ['tab' => 'roles']) }}">{{ __('Bekor qilish') }}</a></div>
</form>
@endsection
