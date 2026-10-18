@extends('layouts.app')
@section('title', 'Shikoyatlar')
@section('content')
<div class="w-full space-y-6">
    <div class="banner banner-info"><x-icon name="info" /><p>Ota-onadan jiddiy shikoyat tushsa, shu yerga yozing. O'sha oyda shikoyat qaydi bor tarbiyachi yoki guruh xodimlari «ota-onalar mamnun» KPI qismini olmaydi.</p></div>
    <form method="post" action="{{ route('app.payroll.complaints.store') }}" class="card card-pad grid sm:grid-cols-2 xl:grid-cols-5 gap-3 items-end">@csrf
        <div><label class="label">Sana</label><input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input" required></div>
        <div><label class="label">Xodim</label><select name="employee_id" class="select"><option value="">—</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
        <div><label class="label">yoki butun guruh</label><select name="group_id" class="select"><option value="">—</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
        <div><label class="label">Izoh</label><input name="note" maxlength="300" class="input" required></div>
        <button class="btn btn-accent">Saqlash</button>
    </form>
    <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Sana</th><th>Kimga</th><th>Izoh</th><th></th></tr></thead><tbody>
        @forelse($rows as $r)<tr><td>{{ $r->date->format('d.m.Y') }}</td>
            <td>{{ $r->employee_id ? ($employees->firstWhere('id', $r->employee_id)->full_name ?? '—') : __('Guruh: :g', ['g' => $groups->firstWhere('id', $r->group_id)->name ?? '—']) }}</td><td>{{ $r->note }}</td>
            <td><form method="post" action="{{ route('app.payroll.complaints.destroy', $r) }}" onsubmit="return confirm(@js(__("Qayd o'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></td></tr>
        @empty<tr><td colspan="4"><div class="empty">Shikoyat qaydi yo'q</div></td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
