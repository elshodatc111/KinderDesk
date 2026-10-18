@extends(auth()->user()->is_super_admin ? 'layouts.admin' : 'layouts.app')
@section('title', 'Profil')
@section('content')
<div class="grid lg:grid-cols-2 gap-6 w-full">
    <form method="post" action="{{ route('app.profile.update') }}" class="card card-pad space-y-4">@csrf @method('put')
        <h2 class="section-title">Shaxsiy ma'lumot</h2>
        <div><label class="label">Ism</label><input name="name" value="{{ old('name', $user->name) }}" class="input" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Telefon</label><input value="{{ format_phone($user->phone) }}" class="input" disabled><p class="help">Telefon raqam kirish uchun ishlatiladi, uni direktor yoki qo'llab-quvvatlash o'zgartiradi.</p></div>
        <div><label class="label">Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="input">@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Til</label><select name="locale" class="select"><option value="uz" @selected($user->locale!=='ru')>O'zbekcha</option><option value="ru" @selected($user->locale==='ru')>Русский</option></select></div>
        <button class="btn btn-primary">Saqlash</button>
    </form>
    <form method="post" action="{{ route('app.profile.password') }}" class="card card-pad space-y-4 self-start">@csrf @method('put')
        <h2 class="section-title">Parolni almashtirish</h2>
        <div><label class="label">Joriy parol</label><input type="password" name="current_password" class="input" autocomplete="current-password">@error('current_password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Yangi parol</label><input type="password" name="password" class="input" autocomplete="new-password">@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div><label class="label">Takrorlang</label><input type="password" name="password_confirmation" class="input" autocomplete="new-password"></div>
        <button class="btn btn-primary">Parolni yangilash</button>
    </form>
</div>
@endsection
