@extends('layouts.admin')
@section('title', "Yangi bog'cha")
@section('content')
<form method="post" action="{{ route('admin.tenants.store') }}" class="card card-pad w-full space-y-4" x-data="{ lifetime: {{ old('lifetime') ? 'true' : 'false' }} }">@csrf
    <div class="banner banner-info"><x-icon name="info" /><p>Demo ma'lumotsiz toza bog'cha yaratiladi. Telefon, email va STIR «sinov reestri»ga yozilmaydi.</p></div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Bog'cha nomi</label><input name="name" value="{{ old('name') }}" class="input" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Direktor ismi</label><input name="owner_name" value="{{ old('owner_name') }}" class="input" required>@error('owner_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Direktor telefoni (kirish uchun)</label><input name="phone" value="{{ old('phone') }}" class="input" required placeholder="998901234567">@error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Boshlang'ich parol</label><input name="password" value="{{ old('password', \Illuminate\Support\Str::random(10)) }}" class="input" required minlength="8">@error('password')<p class="field-error">{{ $message }}</p>@enderror<p class="help">Direktorga ayting; kirgach almashtirishi mumkin.</p></div>
        <div><label class="label">Email (ixtiyoriy)</label><input name="email" type="email" value="{{ old('email') }}" class="input">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">STIR (ixtiyoriy)</label><input name="tax_id" value="{{ old('tax_id') }}" class="input"></div>
        <div><label class="label">Tarif</label><select name="edition_id" class="select">@foreach($editions as $e)<option value="{{ $e->id }}" @selected(old('edition_id')==$e->id)>{{ $e->name }}</option>@endforeach</select></div>
        <div x-show="!lifetime"><label class="label">Boshlang'ich muddat (oy)</label><input name="months" type="number" min="0" max="36" value="{{ old('months', 1) }}" class="input">@error('months')<p class="field-error">{{ $message }}</p>@enderror</div>
    </div>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="lifetime" value="1" x-model="lifetime" @checked(old('lifetime'))> Umrbod (tarif to'lovi olinmaydi, barcha imkoniyatlar ochiq)</label>
    <div class="flex gap-3"><button class="btn btn-primary">Yaratish</button><a href="{{ route('admin.tenants.index') }}" class="btn">Bekor qilish</a></div>
</form>
@endsection
