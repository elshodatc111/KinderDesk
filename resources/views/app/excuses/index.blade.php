@extends('layouts.app')
@section('title', 'Sababli kelmagan chegirmalari')
@section('content')
<div class="w-full space-y-6">
    <div class="banner banner-info"><x-icon name="info" /><p>Bola sababli kelmagan kunlar uchun administrator chegirma kiritadi, <b>direktor tasdiqlaydi</b>. Summa = (oylik narx − aka-uka chegirmasi) ÷ oydagi ishchi kunlar × sababli kunlar.</p></div>

    @can('discount.enter')
    <form method="post" action="{{ route('app.excuses.store') }}" class="card p-4 grid sm:grid-cols-5 gap-3 items-end">@csrf
        <div class="sm:col-span-2"><label class="label">Bola</label>
            <select name="kid_id" class="input" required>@foreach ($kids as $k)<option value="{{ $k->id }}">{{ $k->full_name }}</option>@endforeach</select></div>
        <div><label class="label">Oy</label><input type="month" name="month" value="{{ now()->format('Y-m') }}" class="input" required></div>
        <div><label class="label">Kunlar soni</label><input type="number" name="days" min="1" max="31" class="input" required></div>
        <div class="sm:col-span-4"><label class="label">Sabab</label><input type="text" name="reason" maxlength="200" class="input" required></div>
        <button class="btn btn-accent">Yuborish</button>
    </form>
    @endcan

    <div class="flex gap-2">
        @foreach (['pending' => 'Kutilmoqda', 'approved' => 'Tasdiqlangan', 'rejected' => 'Rad etilgan', 'all' => 'Hammasi'] as $k => $label)
            <a href="{{ route('app.excuses.index', ['status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-primary' : '' }}">{{ __($label) }}@if($k==='pending' && $pendingCount) ({{ $pendingCount }})@endif</a>
        @endforeach
    </div>

    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>Bola</th><th>Oy</th><th>Kun</th><th>Sabab</th><th class="text-right">Chegirma</th><th>Holat</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            <tr>
                <td><a href="{{ route('app.kids.show', $r->kid) }}" class="font-semibold hover:underline">{{ $r->kid->full_name }}</a></td>
                <td>{{ $r->period->format('m.Y') }}</td><td>{{ $r->days }}</td><td>{{ $r->reason }}</td>
                <td class="text-right">{{ $r->status === 'approved' ? money($r->amount) : '—' }}</td>
                <td><span class="badge {{ ['pending' => 'badge-marigold', 'approved' => 'badge-leaf', 'rejected' => 'badge-berry'][$r->status] }}">{{ __(\App\Models\KidExcuse::statusLabels()[$r->status]) }}</span></td>
                <td class="whitespace-nowrap">
                    @if ($r->status === 'pending') @can('discount.approve')
                        <form method="post" action="{{ route('app.excuses.approve', $r) }}" class="inline">@csrf<button class="btn btn-sm btn-primary">Tasdiqlash</button></form>
                        <form method="post" action="{{ route('app.excuses.reject', $r) }}" class="inline">@csrf<button class="btn btn-sm">Rad etish</button></form>
                    @endcan @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty">Yozuv yo'q</div></td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection
