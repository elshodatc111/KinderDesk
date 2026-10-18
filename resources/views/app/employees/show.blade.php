@extends('layouts.app')
@section('title', $employee->full_name)
@section('actions')@can('employees.manage')<a href="{{ route('app.employees.edit', $employee) }}" class="btn btn-sm"><x-icon name="edit" /> Tahrirlash</a>
<form method="post" action="{{ route('app.employees.archive', $employee) }}" onsubmit="return confirm(@js(__('Davom etasizmi?')))">@csrf<button class="btn btn-sm">{{ $employee->status==='active' ? 'Arxivga o\'tkazish' : 'Qayta faollashtirish' }}</button></form>@endcan @endsection
@section('content')
<div class="grid lg:grid-cols-[22rem_1fr] gap-6 items-start">
    <div class="card-sticker p-5">
        <div class="flex items-center gap-4"><span class="avatar avatar-lg" style="background:var(--color-grape-100)">{{ mb_substr($employee->full_name,0,1) }}</span>
            <div><h2 class="font-semibold text-xl leading-tight">{{ $employee->full_name }}</h2><p class="text-sm font-medium text-ink-soft">{{ $employee->positionLabel() }}</p>@if($employee->status==='archived')<span class="badge badge-gray">Arxivda</span>@endif</div></div>
        <dl class="mt-4 space-y-2 text-sm font-medium">
            <div class="flex justify-between gap-3"><dt class="text-ink-mute">Telefon</dt><dd>{{ $employee->phone ? format_phone($employee->phone) : '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-mute">Guruh</dt><dd>{{ $employee->activeGroupLinks->map(fn($l)=>$l->group?->name)->filter()->implode(', ') ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-mute">Ishga kirgan</dt><dd>{{ $employee->hired_on?->format('d.m.Y') ?? '—' }}</dd></div>
            @if($employee->birth_date)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Tug'ilgan sana</dt><dd>{{ $employee->birth_date->format('d.m.Y') }}</dd></div>@endif
            @if($employee->address)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Manzil</dt><dd class="text-right">{{ $employee->address }}</dd></div>@endif
            <div class="flex justify-between gap-3"><dt class="text-ink-mute">Tizimga kirish</dt><dd>{{ $employee->user ? $employee->user->roleName() : 'Yo\'q' }}</dd></div>
            @if($employee->base_salary)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Oylik</dt><dd>{{ money($employee->base_salary) }} so'm</dd></div>@endif
        </dl>
    </div>
    <div class="space-y-5 min-w-0">
        @feature('staff_attendance')
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach (['present' => ['Keldi','leaf'], 'late' => ['Kechikdi','marigold'], 'absent' => ['Kelmadi','berry'], 'leave' => ["Ta'til",'sky']] as $k => [$l,$c])
                <div class="tile" style="--tile-bg:var(--color-{{ $c }}-50);--tile-accent:var(--color-{{ $c }}-500)"><p class="tile-label">{{ $l }} (shu oy)</p><p class="tile-value">{{ $att[$k] ?? 0 }}</p></div>
            @endforeach
        </div>
        @endfeature
        @if($payslips->isNotEmpty())
        <div class="card table-wrap"><table class="table"><thead><tr><th>Oy</th><th class="text-right">Hisoblandi</th><th>Holat</th></tr></thead><tbody>
            @foreach($payslips as $p)<tr><td>{{ $p->period->format('m.Y') }}</td><td class="text-right font-semibold">{{ money($p->total) }}</td><td><span class="badge {{ $p->status==='paid' ? 'badge-leaf' : 'badge-orange' }}">{{ $p->status==='paid' ? "To'langan" : 'Kutilmoqda' }}</span></td></tr>@endforeach
        </tbody></table></div>@endif
        <div class="space-y-3"><h2 class="section-title">Izohlar</h2>
            @can('employees.manage')<form method="post" action="{{ route('app.employees.notes.store', $employee) }}" class="flex gap-2">@csrf<input name="body" class="input" required placeholder="Izoh"><button class="btn btn-primary">Qo'shish</button></form>@endcan
            @forelse($notes as $n)<div class="card card-pad"><p class="font-semibold">{{ $n->body }}</p><p class="text-xs font-medium text-ink-mute mt-1">{{ $n->author?->name }} · {{ $n->created_at->format('d.m.Y') }}</p></div>@empty<p class="text-sm font-medium text-ink-mute">Izoh yo'q</p>@endforelse
        </div>
    </div>
</div>
@endsection
