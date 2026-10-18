@extends('layouts.admin')
@section('title', 'Rozilik jurnali')
@section('content')
<p class="text-sm text-ink-soft mb-4">{{ __("Joriy versiya") }}: <b>v{{ $version }}</b>. {{ __("Rozilik bermagan foydalanuvchi tizimdan foydalana olmaydi. Yangi versiya Sozlamalar → Sayt bo'limida «qayta rozilik talab qilish» orqali yaratiladi.") }}</p>
<form class="mb-4 flex flex-wrap gap-2 items-end">
    <input name="q" value="{{ request('q') }}" class="input !w-60" placeholder="{{ __('Ism, telefon yoki bog\'cha') }}">
    <input name="version" value="{{ request('version') }}" class="input !w-28" placeholder="{{ __('Versiya') }}" inputmode="numeric">
    <input type="date" name="from" value="{{ request('from') }}" class="input !w-40"><input type="date" name="to" value="{{ request('to') }}" class="input !w-40">
    <button class="btn">{{ __('Saralash') }}</button>
    <a class="btn" href="{{ route('admin.consents.csv', request()->query()) }}">{{ __('CSV yuklab olish') }}</a>
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Vaqt') }}</th><th>{{ __('Foydalanuvchi') }}</th><th>{{ __("Bog'cha") }}</th><th>{{ __('Versiya') }}</th><th>IP</th><th>{{ __('Qurilma') }}</th></tr></thead><tbody>
@forelse ($items as $r)
    <tr><td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->accepted_at)->format('d.m.Y H:i') }}</td><td>{{ $r->user_name }}<div class="text-xs text-ink-mute">{{ $r->user_phone }}</div></td><td>{{ $r->tenant_name ?? '—' }}</td><td>v{{ $r->version }}</td><td>{{ $r->ip }}</td><td class="text-xs text-ink-soft max-w-xs truncate">{{ $r->user_agent }}</td></tr>
@empty<tr><td colspan="6"><div class="empty">{{ __("Yozuv yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
