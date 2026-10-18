@extends('layouts.admin')
@section('title', 'Audit jurnali')
@section('content')
<form class="mb-4"><input name="q" value="{{ request('q') }}" class="input !w-72" placeholder="Amal bo'yicha qidirish (masalan: topup)"></form>
<div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Vaqt</th><th>Kim</th><th>Amal</th><th>Obyekt</th><th>Tafsilot</th></tr></thead><tbody>
@forelse ($items as $a)
    <tr><td class="whitespace-nowrap">{{ $a->created_at->format('d.m.Y H:i') }}</td><td>{{ $a->user?->name ?? 'Tizim' }}</td><td class="font-semibold">{{ $a->action }}</td>
        <td>{{ $a->subject_type }} {{ $a->subject_id ? '#'.$a->subject_id : '' }}</td><td class="text-xs font-semibold text-ink-soft max-w-xs truncate">{{ $a->meta ? json_encode($a->meta, JSON_UNESCAPED_UNICODE) : '' }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">Yozuv yo'q</div></td></tr>@endforelse
</tbody></table></div></div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
