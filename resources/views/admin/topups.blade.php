@extends('layouts.admin')
@section('title', "To'lov cheklari")
@section('content')
@php $map = ['pending' => ['Kutilmoqda', 'badge-orange'], 'approved' => ['Tasdiqlangan', 'badge-leaf'], 'rejected' => ['Rad etilgan', 'badge-red']]; @endphp
<div class="tabs mb-5">
    @foreach (['pending' => 'Kutilayotgan ('.$pending.')', 'approved' => 'Tasdiqlangan', 'rejected' => 'Rad etilgan', 'all' => 'Hammasi'] as $k => $l)
        <a href="{{ route('admin.topups.index', ['status' => $k]) }}" class="tab {{ $status===$k ? 'is-active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="space-y-4" x-data x-init="window.addEventListener('kd:activity', e => { if (e.detail?.kind === 'topup.pending') location.reload() })">
@forelse ($items as $t)
    <div class="card card-pad grid md:grid-cols-[1fr_auto] gap-4" x-data="{ reject: false }">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.tenants.show', $t->tenant) }}" class="font-semibold text-lg hover:underline">{{ $t->tenant->name }}</a>
                <span class="badge {{ $map[$t->status][1] }}">{{ $map[$t->status][0] }}</span><span class="badge badge-gray">{{ $t->method === 'payme' ? 'Payme' : 'Karta + chek' }}</span>
            </div>
            <p class="text-2xl font-semibold mt-1">{{ money($t->amount) }} <span class="text-sm text-ink-mute">so'm</span></p>
            <p class="text-sm font-medium text-ink-soft mt-1">Yuborildi: {{ $t->created_at->format('d.m.Y H:i') }} @if($t->creator) · {{ $t->creator->name }} @endif
                @if($t->paid_on) · To'lov sanasi: {{ \Illuminate\Support\Carbon::parse($t->paid_on)->format('d.m.Y') }} @endif @if($t->sender_card) · Karta: {{ $t->sender_card }} @endif</p>
            @if($t->note)<p class="text-sm font-semibold mt-1">Izoh: {{ $t->note }}</p>@endif
            @if($t->reject_reason)<p class="text-sm font-medium text-danger mt-1">Sabab: {{ $t->reject_reason }}</p>@endif
            @if ($t->status==='pending')
            <div class="flex flex-wrap gap-2 mt-4">
                <form method="post" action="{{ route('admin.topups.approve', $t) }}">@csrf <button class="btn btn-primary" onclick="return confirm(@js(__(':sum so\'m balansga tushiriladi. Tasdiqlaysizmi?', ['sum' => money($t->amount)])))">Tasdiqlash</button></form>
                <button type="button" class="btn" @click="reject = !reject">Rad etish</button>
            </div>
            <form x-show="reject" x-cloak method="post" action="{{ route('admin.topups.reject', $t) }}" class="mt-3 flex gap-2">@csrf <input name="reason" class="input" placeholder="Rad etish sababi" required><button class="btn btn-danger">Rad etish</button></form>
            @endif
        </div>
        @if ($t->receipt_path)
            <a href="{{ route('admin.topups.receipt', $t) }}" target="_blank" class="block w-full md:w-48 h-40 rounded-2xl border border-line overflow-hidden bg-cloud grid place-items-center">
                @if (Str::endsWith($t->receipt_path, '.pdf'))<span class="font-semibold">PDF chek</span>
                @else <img src="{{ route('admin.topups.receipt', $t) }}" alt="Chek" class="w-full h-full object-cover" loading="lazy">@endif</a>
        @endif
    </div>
@empty
    <div class="empty"><x-icon name="receipt" class="size-10" /><p class="font-semibold mt-2">Bu bo'limda chek yo'q</p></div>
@endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
