@extends('layouts.app')
@section('title', $group->name)
@section('actions')@can('groups.manage')<a href="{{ route('app.groups.edit', $group) }}" class="btn btn-sm"><x-icon name="edit" /> Tahrirlash</a>
<form method="post" action="{{ route('app.groups.archive', $group) }}">@csrf<button class="btn btn-sm">{{ $group->status==='active' ? 'Arxivlash' : 'Qayta faollashtirish' }}</button></form>@endcan @endsection
@section('content')
<div class="grid lg:grid-cols-[1fr_22rem] gap-6 items-start">
    <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>{{ __('Bola') }}</th><th>{{ __('Ota-ona') }}</th><th class="text-right">{{ __('Hisob') }}</th>@if($group->isDaily())<th></th>@endif</tr></thead><tbody>
        @forelse ($group->activeKidLinks as $l) @php $k = $l->kid; @endphp
            <tr><td><a href="{{ route('app.kids.show', $k) }}" class="font-semibold hover:underline">{{ $k->full_name }}</a><span class="block text-xs font-semibold text-ink-soft">{{ $k->age() }}</span></td>
                <td>{{ format_phone($k->parent_phone) }}</td><td class="text-right font-semibold {{ $k->balance<0?'text-danger':'text-leaf-700' }}">{{ $k->balance<0?'−':'' }}{{ money(abs($k->balance)) }}</td>@if($group->isDaily())<td class="text-right">@can('groups.manage')<form method="post" action="{{ route('app.groups.kids.leave', [$group, $l]) }}">@csrf @method('delete')<button class="btn-quiet is-danger">{{ __('Chiqarish') }}</button></form>@endcan</td>@endif</tr>
        @empty<tr><td colspan="4"><div class="empty">{{ __("Guruhda bola yo'q") }}</div></td></tr>@endforelse
    </tbody></table></div>
    @if($group->isDaily() && $availableKids->isNotEmpty())@can('groups.manage')
        <form method="post" action="{{ route('app.groups.kids.enroll', $group) }}" class="flex flex-wrap gap-3 p-4 border-t border-line">@csrf
            <select name="kid_id" class="select flex-1 min-w-48">@foreach($availableKids as $k)<option value="{{ $k->id }}">{{ $k->full_name }}</option>@endforeach</select><button class="btn btn-sm">{{ __("Bolani qo'shish") }}</button></form>
    @endcan @endif
    </div>
    <div class="space-y-4">
        <div class="card card-pad"><p class="tile-label">@if(! $group->isDaily() && feature('pay_schemes')){{ __($group->sizeLabel()) }} · @endif{{ $group->isDaily() ? __("Bir kunlik to'lov") : __("Oylik to'lov") }}@if($group->type!=='main') · {{ __($group->typeLabel()) }}@endif</p><p class="text-2xl font-semibold">{{ money($group->isDaily() ? $group->daily_fee : $group->monthly_fee) }} {{ __("so'm") }}</p>@if($group->timeLabel())<p class="text-sm font-medium text-ink-soft">{{ $group->timeLabel() }}</p>@endif
            <p class="text-sm font-medium text-ink-soft mt-1">{{ $group->activeKidLinks->count() }}@if($group->capacity) / {{ $group->capacity }}@endif bola @if($group->room) · {{ $group->room }}@endif</p></div>
        <div class="card card-pad space-y-3"><h2 class="section-title">Tarbiyachilar</h2>
            @forelse ($group->activeEmployeeLinks as $l)
                <div class="flex items-center justify-between gap-2"><span><a href="{{ route('app.employees.show', $l->employee) }}" class="font-semibold hover:underline">{{ $l->employee?->full_name }}</a>@if(feature('pay_schemes'))<span class="badge ml-1">{{ $l->role_in_group === 'assistant' ? __('Yordamchi') : __('Tarbiyachi') }}</span>@endif</span>
                    @can('groups.manage')<form method="post" action="{{ route('app.groups.employees.remove', [$group, $l]) }}">@csrf @method('delete')<button class="btn-quiet !text-danger">Chiqarish</button></form>@endcan</div>
            @empty<p class="text-sm font-medium text-ink-mute">Biriktirilmagan</p>@endforelse
            @can('groups.manage')
            @if($availableEmployees->isEmpty())<p class="pt-2 border-t border-line text-sm font-medium text-ink-soft">{{ __("Biriktirish mumkin bo'lgan tarbiyachi yoki yordamchi yo'q.") }}@if($busyCount) {{ __("Qolganlari boshqa guruhda band — xodim faqat bitta guruhda bo'ladi.") }}@endif</p>@endif
            @if($availableEmployees->isNotEmpty())
            <form method="post" action="{{ route('app.groups.employees.assign', $group) }}" class="flex gap-2 pt-2 border-t border-line">@csrf
                <select name="employee_id" class="select">@foreach($availableEmployees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}@if($e->role) — {{ $e->role->name }}@endif</option>@endforeach</select>
                <button class="btn btn-sm">Biriktirish</button></form>
            @endif
            @endcan
        </div>
    </div>
</div>
@endsection
