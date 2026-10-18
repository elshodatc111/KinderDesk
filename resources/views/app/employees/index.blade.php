@extends('layouts.app')
@section('title', 'Xodimlar')
@section('actions')@can('employees.manage')<a href="{{ route('app.employees.create') }}" class="btn btn-sm btn-accent"><x-icon name="plus" /> Xodim qo'shish</a>@endcan @endsection
@section('content')
<form class="flex flex-wrap gap-3 mb-5" method="get">
    <input name="q" value="{{ request('q') }}" class="input !w-64" placeholder="Ism yoki telefon">
    <select name="role" class="select !w-48" onchange="this.form.submit()"><option value="">{{ __('Barcha lavozimlar') }}</option>@foreach($roles as $r)<option value="{{ $r->id }}" @selected(request('role')==$r->id)>{{ $r->name }}</option>@endforeach</select>
    <select name="status" class="select !w-36" onchange="this.form.submit()"><option value="active">Faol</option><option value="archived" @selected($status==='archived')>Arxiv</option></select>
    <button class="btn">Qidirish</button>
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Xodim</th><th>Lavozim</th><th>Guruh</th><th>Telefon</th></tr></thead><tbody>
@forelse ($employees as $e)
    <tr><td><a href="{{ route('app.employees.show', $e) }}" class="flex items-center gap-3"><span class="avatar" style="background:var(--color-grape-100)">{{ mb_substr($e->full_name,0,1) }}</span><span class="font-semibold hover:underline">{{ $e->full_name }}</span></a></td>
        <td>{{ $e->positionLabel() }}</td><td>{{ $e->activeGroupLinks->map(fn($l)=>$l->group?->name)->filter()->implode(', ') ?: '—' }}</td><td>{{ $e->phone ? format_phone($e->phone) : '—' }}</td></tr>
@empty<tr><td colspan="4"><div class="empty"><x-icon name="employees" class="size-10" /><p class="font-semibold mt-2">Xodim topilmadi</p></div></td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $employees->links() }}</div>
@endsection
