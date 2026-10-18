@extends('layouts.admin')
@section('title', "Bog'chalar")
@section('content')
@php $stateMap = ['trial' => ['Sinov', 'badge-orange'], 'active' => ['Faol', 'badge-leaf'], 'lifetime' => ['Umrbod', 'badge-sky'], 'read_only' => ["Faqat ko'rish", 'badge-red'], 'suspended' => ['Bloklangan', 'badge-gray']]; @endphp
<div class="flex justify-end mb-3"><a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Yangi bog'cha (demo'siz)</a></div>
<form class="flex flex-wrap gap-3 mb-5" method="get">
    <input name="q" value="{{ request('q') }}" placeholder="Nomi, telefon, STIR, email" class="input !w-72">
    <select name="state" class="select !w-48" onchange="this.form.submit()">
        <option value="">Barcha holatlar</option>
        @foreach (['trial' => 'Sinovda', 'active' => 'Faol', 'lifetime' => 'Umrbod', 'read_only' => "Faqat ko'rish", 'suspended' => 'Bloklangan', 'delete' => "O'chirishga tayyor (90+)"] as $k => $l)<option value="{{ $k }}" @selected(request('state')===$k)>{{ $l }}</option>@endforeach
    </select>
    <select name="edition" class="select !w-40" onchange="this.form.submit()"><option value="">Barcha nashrlar</option>
        @foreach ($editions as $e)<option value="{{ $e->key }}" @selected(request('edition')===$e->key)>{{ $e->name }}</option>@endforeach</select>
    <button class="btn">Qidirish</button>
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table">
    <thead><tr><th>Bog'cha</th><th>Nashr</th><th>Holat</th><th>Tugaydi</th><th class="text-right">Balans</th><th>Ro'yxatdan o'tgan</th></tr></thead>
    <tbody>
    @forelse ($tenants as $t)
        @php $s = $t->accessState(); @endphp
        <tr>
            <td><a href="{{ route('admin.tenants.show', $t) }}" class="font-semibold hover:underline">{{ $t->name }}</a><span class="block text-xs font-semibold text-ink-soft">{{ format_phone($t->phone) }}</span></td>
            <td>{{ $t->edition->name }}</td>
            <td><span class="badge {{ $stateMap[$s][1] }}">{{ $stateMap[$s][0] }}</span>@if ($t->isManualReadOnly())<span class="badge badge-gray">qo'lda</span>@endif
                @if ($s==='read_only' && $t->daysSinceExpiry() >= config('kinderdesk.delete_after_days'))<span class="badge badge-red">90+ kun</span>@endif</td>
            <td class="whitespace-nowrap">{{ $t->lifetime ? '∞' : $t->access_expires_at?->format('d.m.Y') }}</td>
            <td class="text-right font-semibold">{{ money($t->balance) }}</td>
            <td class="whitespace-nowrap">{{ $t->created_at->format('d.m.Y') }}</td>
        </tr>
    @empty
        <tr><td colspan="6"><div class="empty">Hech narsa topilmadi</div></td></tr>
    @endforelse
    </tbody></table></div></div>
@endsection
