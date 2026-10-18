@extends('layouts.app')
@section('title', 'Bolalar')
@section('actions')@can('kids.manage')<a href="{{ route('app.kids.create') }}" class="btn btn-sm btn-accent"><x-icon name="plus" /> Bola qo'shish</a>@endcan @endsection
@section('content')
<form class="flex flex-wrap gap-3 mb-5" method="get">
    <input name="q" value="{{ request('q') }}" class="input !w-64" placeholder="Ism yoki telefon bo'yicha qidirish">
    <select name="group" class="select !w-48" onchange="this.form.submit()"><option value="">Barcha guruhlar</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(request('group')==$g->id)>{{ $g->name }}</option>@endforeach</select>
    <select name="status" class="select !w-36" onchange="this.form.submit()"><option value="active">Faol</option><option value="archived" @selected($status==='archived')>Arxiv</option></select>
    <label class="check"><input type="checkbox" name="debt" value="1" @checked(request()->boolean('debt')) onchange="this.form.submit()"> Faqat qarzdorlar</label>
    <button class="btn">Qidirish</button>
    @if($totalDebt)<span class="ml-auto self-center text-sm font-semibold text-danger">Jami qarz: {{ money($totalDebt) }} so'm</span>@endif
</form>
<div class="card table-wrap" x-data x-init="window.addEventListener('kd:activity', e => { if (['finance','kid'].includes(e.detail?.kind)) location.reload() })"><div class="table-scroll"><table class="table">
    <thead><tr><th>Bola</th><th>Guruh</th><th>Ota-ona</th><th class="text-right">Hisob</th></tr></thead><tbody>
    @forelse ($kids as $k)
        @php $warn = (int) $tenant->setting('billing.debt_warning', 0); @endphp
        <tr>
            <td><a href="{{ route('app.kids.show', $k) }}" class="flex items-center gap-3"><span class="avatar" style="background:{{ $k->gender==='female' ? 'var(--color-berry-100)' : 'var(--color-sky-100)' }}">{{ mb_substr($k->full_name,0,1) }}</span>
                <span><span class="block font-semibold hover:underline">{{ $k->full_name }}</span><span class="block text-xs font-semibold text-ink-soft">{{ $k->age() }}</span></span></a></td>
            <td>{{ $k->currentLink?->group?->name ?? '—' }}</td>
            <td><span class="block font-medium">{{ $k->parent_name }}</span><span class="text-xs font-semibold text-ink-soft">{{ format_phone($k->parent_phone) }}</span></td>
            <td class="text-right font-semibold {{ $k->balance < 0 ? 'text-danger' : 'text-leaf-700' }}">
                {{ $k->balance < 0 ? '−' : '' }}{{ money(abs($k->balance)) }}
                @if($warn && $k->balance < 0 && abs($k->balance) >= $warn)<span class="badge badge-red ml-1">Qarz</span>@endif</td>
        </tr>
    @empty
        <tr><td colspan="4"><div class="empty"><x-icon name="kids" class="size-10" /><p class="font-semibold mt-2">Bolalar topilmadi</p>@can('kids.manage')<a href="{{ route('app.kids.create') }}" class="btn btn-accent btn-sm mt-3">Birinchi bolani qo'shing</a>@endcan</div></td></tr>
    @endforelse
    </tbody></table></div></div>
<div class="mt-4">{{ $kids->links() }}</div>
@endsection
