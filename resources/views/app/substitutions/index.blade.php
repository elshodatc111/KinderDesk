@extends('layouts.app')
@section('title', 'Almashtirish')
@section('content')
<div class="w-full space-y-6" x-data="{ groups: @js($groups), gid: '', absent: '' , get staff() { return (this.groups.find(g => g.id == this.gid)?.staff) || []; } }">
    <div class="banner banner-info"><x-icon name="info" /><p>Bugun kim kelmadi va o'rniga kim ishladi? O'sha kunning <b>to'liq ish haqi</b> (shu kungi plus × plus narxi) o'rindosh xodimga yoziladi. O'rindosh tanlanmasa, o'sha kun hech kimga yozilmaydi.</p></div>

    <form method="post" action="{{ route('app.substitutions.store') }}" class="card card-pad grid sm:grid-cols-2 xl:grid-cols-5 gap-3 items-end">@csrf
        <div><label class="label">Sana</label><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="input" required></div>
        <div><label class="label">Guruh</label>
            <select name="group_id" class="input" x-model="gid" required><option value="">—</option>@foreach ($groups as $g)<option value="{{ $g['id'] }}">{{ $g['name'] }}</option>@endforeach</select></div>
        <div><label class="label">Kelmagan xodim</label>
            <select name="absent_employee_id" class="input" x-model="absent" required><option value="">—</option><template x-for="s in staff" :key="s.id"><option :value="s.id" x-text="s.name + ' (' + (s.role === 'assistant' ? `Yordamchi` : `Tarbiyachi`) + ')'"></option></template></select></div>
        <div><label class="label">O'rniga ishlagan</label>
            <select name="substitute_employee_id" class="input"><option value="">O'rindosh yo'q</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select></div>
        <button class="btn btn-accent">Saqlash</button>
    </form>

    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>Sana</th><th>Guruh</th><th>Kelmagan</th><th>O'rniga ishlagan</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr><td>{{ $r->date->format('d.m.Y') }}</td><td>{{ $groupNames[$r->group_id] ?? '—' }}</td><td>{{ $employeeNames[$r->absent_employee_id] ?? '—' }}</td>
                <td>{{ $r->substitute_employee_id ? ($employeeNames[$r->substitute_employee_id] ?? '—') : __("O'rindosh yo'q") }}</td>
                <td><form method="post" action="{{ route('app.substitutions.destroy', $r) }}" onsubmit="return confirm(@js(__("Almashtirish o'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></td></tr>
        @empty
            <tr><td colspan="5"><div class="empty">Oxirgi 60 kunda almashtirish yo'q</div></td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection
