@extends('layouts.admin')
@section('title', 'Zaxira API')
@section('content')
<div class="grid xl:grid-cols-[1fr_26rem] gap-6 items-start">
<div class="space-y-6">
<form method="post" action="{{ route('admin.backup.update') }}" class="card card-pad space-y-4">@csrf @method('put')
    <p class="help">{{ __("Tashqi zaxira tizimingiz shu API orqali bazaning to'liq .sql nusxasini oladi. Kuniga bitta nusxa tayyorlanadi, 24 soatdan keyin avtomatik o'chiriladi.") }}</p>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" class="check" name="enabled" value="1" @checked($enabled)> {{ __('API yoqilgan') }}</label>
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">{{ __('Login') }}</label><input name="login" class="input" value="{{ old('login', $login) }}" required autocomplete="off"></div>
        <div><label class="label">{{ __('Parol') }}</label><input type="password" name="password" class="input" autocomplete="new-password" placeholder="{{ $passwordSet ? __('Saqlangan (o\'zgartirish uchun yangisini kiriting)') : __('Kamida 12 belgi') }}">
            @error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
    </div>
    <div><label class="label">{{ __("Ruxsat etilgan IP manzillar") }}</label><input name="ips" class="input" value="{{ old('ips', $ips) }}" placeholder="203.0.113.10, 198.51.100.0/24">
        <p class="help mt-1">{{ __("Vergul bilan ajrating. Bo'sh qoldirilsa — hech kimga ruxsat yo'q.") }} ({{ __('Sizning IP') }}: {{ $myIp }})</p></div>
    <div><label class="label">mysqldump {{ __("yo'li (ixtiyoriy)") }}</label><input name="mysqldump" class="input" value="{{ old('mysqldump', $dumpBin) }}" placeholder="C:\xampp\mysql\bin\mysqldump.exe">
        <p class="help mt-1">{{ __("Topilmasa, tizim o'zining PHP dumperidan foydalanadi.") }}</p></div>
    <button class="btn btn-primary">{{ __('Saqlash') }}</button>
</form>
<div class="card table-wrap"><div class="flex items-center justify-between p-4"><h3 class="font-semibold">{{ __('Oxirgi nusxalar') }}</h3>
    <form method="post" action="{{ route('admin.backup.run') }}">@csrf<button class="btn btn-sm">{{ __('Hozir yaratish (sinov)') }}</button></form></div>
<div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Holat') }}</th><th>{{ __('Hajmi') }}</th><th>{{ __('Yuklab olingan') }}</th><th>{{ __("O'chiriladi") }}</th></tr></thead><tbody>
@forelse($jobs as $j)<tr><td>{{ $j->for_date->format('d.m.Y') }}</td><td>{{ $j->status }}@if($j->error)<div class="text-xs text-danger">{{ $j->error }}</div>@endif</td><td>{{ $j->size ? number_format($j->size / 1048576, 1).' MB' : '—' }}</td><td>{{ $j->downloads }}</td><td>{{ $j->expires_at?->format('d.m.Y H:i') ?? '—' }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">{{ __("Hali nusxa yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
</div>
<div class="card card-pad text-sm space-y-3">
    <h3 class="font-semibold">{{ __('API ishlatish') }}</h3>
    <p>1) {{ __("Nusxa so'rash (kuniga bir marta yaratiladi, keyingi so'rovlar o'shani qaytaradi):") }}</p>
    <pre class="rounded-lg bg-cloud p-3 text-xs overflow-x-auto">POST {{ url('/api/backup/request') }}
{"login":"...","password":"..."}
→ 202 {"job_id":"...","status":"pending|running|ready"}</pre>
    <p>2) {{ __('Tayyor bo\'lguncha 202 qaytadi; keyin faylni oling:') }}</p>
    <pre class="rounded-lg bg-cloud p-3 text-xs overflow-x-auto">GET {{ url('/api/backup/{job_id}/download') }}
{"login":"...","password":"..."}  (JSON yoki Basic auth)
→ 200 .sql  (X-Backup-Sha256 sarlavhasi)</pre>
    <p class="help">{{ __("Fayl 24 soatdan keyin o'chadi. APP_KEY nusxaga KIRMAYDI: shifrlangan maydonlarni (ism, telefon, sog'liq) tiklash uchun .env dagi APP_KEY ni zaxira tizimida alohida va xavfsiz saqlang — usiz bu maydonlarni ochib bo'lmaydi.") }}</p>
</div>
</div>
@endsection
