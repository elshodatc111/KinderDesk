@extends('layouts.app')
@section('title', 'Sozlamalar')
@section('content')
@php
    $tabs = ['general' => 'Asosiy sozlamalar', 'modules' => "Bo'limlar", 'org' => "Bog'cha ma'lumoti", 'users' => 'Foydalanuvchilar', 'roles' => 'Lavozimlar'];
    $enabledMsg = ['finance' => 'Moliya'];
@endphp
<div class="tabs mb-6 overflow-x-auto">
    @foreach ($tabs as $k => $l)<a href="{{ route('app.settings.index', ['tab' => $k]) }}" class="tab {{ $tab === $k ? 'is-active' : '' }}">{{ $l }}</a>@endforeach
</div>

@if ($tab === 'general')
<form method="post" action="{{ route('app.settings.update') }}" class="space-y-6 w-full">@csrf @method('put')
    @foreach ($groups as $gKey => $group)
        <section class="card card-pad">
            <h2 class="section-title mb-4">{{ $group['title'] }}</h2>
            <div class="space-y-5">
            @foreach ($group['items'] as $key => $def)
                <div>
                @switch($def['type'])
                    @case('bool')
                        <input type="hidden" name="b[{{ $key }}]" value="1">
                        <label class="flex items-center justify-between gap-4"><span class="font-semibold">{{ $def['label'] }}</span>
                            <span class="switch"><input type="checkbox" name="s[{{ $key }}]" value="1" @checked($def['value'])><span></span></span></label>
                        @break
                    @case('select')
                        <label class="label">{{ $def['label'] }}</label>
                        <select name="s[{{ $key }}]" class="select">
                            @foreach ($def['options'] as $v => $l)
                                <option value="{{ $v }}" @selected($def['value'] === $v) @disabled(isset($def['locked'][$v]))>{{ $l }}{{ isset($def['locked'][$v]) ? ' — nashringizda yo\'q' : '' }}</option>
                            @endforeach
                        </select>
                        @break
                    @case('multiselect')
                        <p class="label">{{ $def['label'] }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($def['options'] as $v => $l)
                                <label class="cursor-pointer"><input type="checkbox" class="peer sr-only" name="s[{{ $key }}][]" value="{{ $v }}" @checked(in_array($v, (array) $def['value']))>
                                    <span class="badge badge-gray peer-checked:!bg-teal-600 peer-checked:!text-white !px-3 !py-1.5">{{ $l }}</span></label>
                            @endforeach
                        </div>
                        @break
                    @case('list')
                        <label class="label">{{ $def['label'] }}</label>
                        <textarea name="s[{{ $key }}]" rows="5" class="textarea">{{ implode("\n", (array) $def['value']) }}</textarea>
                        @break
                    @case('int')
                        <label class="label">{{ $def['label'] }}</label>
                        <input type="number" name="s[{{ $key }}]" value="{{ $def['value'] }}" min="{{ $def['min'] ?? 0 }}" @if(isset($def['max'])) max="{{ $def['max'] }}" @endif class="input !w-40">
                        @break
                    @case('time')
                        <label class="label">{{ $def['label'] }}</label>
                        <input type="time" name="s[{{ $key }}]" value="{{ $def['value'] }}" class="input !w-40">
                        @break
                    @default
                        <label class="label">{{ $def['label'] }}</label>
                        <input name="s[{{ $key }}]" value="{{ $def['value'] }}" class="input">
                @endswitch
                @if (!empty($def['help']))<p class="help mt-1">{{ $def['help'] }}</p>@endif
                </div>
            @endforeach
            </div>
        </section>
    @endforeach
    <button class="btn btn-primary btn-lg">Saqlash</button>
</form>

@elseif ($tab === 'modules')
<form method="post" action="{{ route('app.settings.modules') }}" class="w-full">@csrf
    <p class="help mb-4">Keraksiz bo'limni o'chirib qo'ying — menyudan yo'qoladi. Ma'lumotlar o'chmaydi, istalgan vaqt qayta yoqasiz. Kulrang bo'limlar nashringizda mavjud emas.</p>
    <div class="card divide-y divide-line">
    @foreach ($features as $key => $f)
        @php $core = !empty($f['core']); $ok = $allowed[$key]; @endphp
        <label class="flex items-center justify-between gap-4 p-4 {{ $ok ? '' : 'opacity-60' }}">
            <span><span class="font-semibold">{{ $f['label'] }}</span>
                @if ($core)<span class="badge badge-gray ml-2">Doim yoqiq</span>@elseif(!$ok)<span class="badge badge-orange ml-2">Yuqori nashrda</span>@endif
                <span class="block text-sm font-semibold text-ink-soft">{{ $f['desc'] }}</span></span>
            <span class="switch"><input type="checkbox" name="on[]" value="{{ $key }}" @checked($enabled[$key]) @disabled($core || !$ok)><span></span></span>
        </label>
    @endforeach
    </div>
    <button class="btn btn-primary btn-lg mt-5">Saqlash</button>
</form>

@elseif ($tab === 'org')
<form method="post" action="{{ route('app.settings.organization') }}" enctype="multipart/form-data" class="card card-pad w-full space-y-4">@csrf
    <div><label class="label">Bog'cha nomi</label><input name="name" value="{{ old('name', $tenant->name) }}" class="input" required></div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="label">Telefon</label><input name="phone" value="{{ old('phone', format_phone($tenant->phone)) }}" class="input"></div>
        <div><label class="label">Manzil</label><input name="address" value="{{ old('address', $tenant->address) }}" class="input"></div></div>
    <div><label class="label">Logotip</label><input type="file" name="logo" accept="image/*" class="input">@error('logo')<p class="field-error">{{ $message }}</p>@enderror</div>
    @if ($tenant->public_key && $tenant->hasFeature('leads'))
        <div x-data="{ copied: false }"><label class="label">Ochiq ariza formasi havolasi</label>
            <div class="flex gap-2"><input readonly tabindex="-1" aria-readonly="true" class="input !bg-cloud !text-ink-soft cursor-default" value="{{ url('/k/'.$tenant->public_key) }}" onclick="this.select()">
                <button type="button" class="btn shrink-0" @click="navigator.clipboard?.writeText('{{ url('/k/'.$tenant->public_key) }}'); copied = true; setTimeout(() => copied = false, 1800)"><span x-show="!copied">{{ __('Nusxalash') }}</span><span x-show="copied" x-cloak>{{ __('Nusxalandi') }}</span></button></div>
            <p class="help">{{ __("Havola bog'cha ochilganda avtomatik yaratiladi va o'zgartirib bo'lmaydi.") }}</p></div>@endif
    <button class="btn btn-primary">Saqlash</button>
</form>

@elseif ($tab === 'users')
<div class="grid lg:grid-cols-[1fr_22rem] gap-6 items-start">
    <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Foydalanuvchi</th><th>Rol</th><th>Holat</th><th></th></tr></thead><tbody>
    @foreach ($users as $u)
        <tr x-data="{ edit: false }">
            <td><span class="font-semibold">{{ $u->name }}</span>@if($u->is_owner)<span class="badge ml-1">Direktor</span>@endif<span class="block text-xs font-semibold text-ink-soft">{{ format_phone($u->phone) }}</span></td>
            @if ($u->is_owner)
                <td>{{ $u->roleName() }}</td><td><span class="badge badge-leaf">Faol</span></td><td></td>
            @else
            <td colspan="3">
                <form method="post" action="{{ route('app.settings.users.update', $u) }}" class="flex flex-wrap items-center gap-2">@csrf @method('put')
                    <select name="role_id" class="select !w-40 !py-1">@foreach($roles as $r)@continue($r->key==='director')<option value="{{ $r->id }}" @selected($u->role_id===$r->id)>{{ $r->name }}</option>@endforeach</select>
                    <select name="status" class="select !w-32 !py-1"><option value="active" @selected($u->status==='active')>Faol</option><option value="blocked" @selected($u->status!=='active')>Bloklangan</option></select>
                    <input name="password" type="password" placeholder="Yangi parol" class="input !w-36 !py-1" autocomplete="new-password">
                    <button class="btn btn-sm">Saqlash</button>
                </form>
                <form method="post" action="{{ route('app.settings.users.destroy', $u) }}" class="mt-1" onsubmit="return confirm(@js(__('Foydalanuvchi o\'chiriladi. Davom etasizmi?')))">@csrf @method('delete')<button class="btn-quiet !text-danger">O'chirish</button></form>
            </td>
            @endif
        </tr>
    @endforeach
    </tbody></table></div></div>
    <form method="post" action="{{ route('app.settings.users.store') }}" class="card card-pad space-y-3">@csrf
        <h2 class="section-title">Yangi foydalanuvchi</h2>
        <input name="name" class="input" placeholder="Ism" value="{{ old('name') }}" required>
        <input name="phone" class="input" placeholder="Telefon (kirish uchun)" value="{{ old('phone') }}" required>@error('phone')<p class="field-error">{{ $message }}</p>@enderror
        <select name="role_id" class="select">@foreach($roles as $r)@continue($r->key==='director')<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select>
        <input name="password" type="password" class="input" placeholder="Parol (8+ belgi, harf va raqam)" autocomplete="new-password" required>@error('password')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-block">Qo'shish</button>
    </form>
</div>

@elseif ($tab === 'roles')
<div class="w-full space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="help max-w-xl">{{ __("Lavozim — xodim kim ekanini va tizimda nimani ko'rishi hamda qila olishini belgilaydi. Xodimni qo'shayotganda lavozim tanlanadi; tizimga kirishi kerak bo'lsa, shu lavozimning ruxsatlari ishlaydi.") }}</p>
        @can('roles.manage')<a href="{{ route('app.settings.roles.create') }}" class="btn btn-accent"><x-icon name="plus" /> {{ __("Yangi lavozim") }}</a>@endcan
    </div>
    <div class="card table-wrap"><table class="table"><thead><tr><th>{{ __('Lavozim') }}</th><th>{{ __('Xodimlar') }}</th><th>{{ __('Ruxsatlar') }}</th><th></th></tr></thead><tbody>
    @foreach ($roles as $r)
        <tr><td class="font-medium">{{ $r->name }}@if($r->key==='director')<span class="badge badge-orange ml-1">{{ __('Asosiy') }}</span>@endif</td>
            <td>{{ $r->employees_count }}</td>
            <td class="text-sm text-ink-soft">{{ in_array('*', $r->permissions ?? []) ? __('Barcha ruxsatlar') : (count($r->permissions ?? []) ? count($r->permissions).' / '.count($permissions) : __("Tizimga kira olmaydi yoki faqat o'zini ko'radi")) }}</td>
            <td class="text-right whitespace-nowrap">@if($r->key!=='director')<a class="btn btn-sm" href="{{ route('app.settings.roles.edit', $r) }}">{{ __('Ruxsatlar') }}</a>
                <form class="inline" method="post" action="{{ route('app.settings.roles.destroy', $r) }}" onsubmit="return confirm(@js(__("Lavozim o'chiriladi. Davom etasizmi?")))">@csrf @method('delete')<button class="btn-quiet !text-danger">{{ __("O'chirish") }}</button></form>@endif</td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endif
@endsection
