@extends('layouts.app')
@section('title', $group->name)
@section('content')
@include('app.telegram._tabs')
<p class="text-sm text-ink-soft mb-4">{{ __("Har bir vasiy uchun shaxsiy havola. Bir vasiyga bir nechta Telegram akkaunt ulanishi mumkin; keraksizini «O'chirish» bilan uzing.") }}</p>
<div class="card table-wrap"><div class="table-scroll"><table class="table">
<thead><tr><th>{{ __('Bola') }}</th><th>{{ __('Vasiy') }}</th><th>{{ __('Ulangan akkauntlar') }}</th><th>{{ __('Havola') }}</th></tr></thead><tbody>
@forelse($rows as $r)
    @foreach($r['guardians'] as $i => $gr)
    <tr>
        @if($i === 0)<td rowspan="{{ max(1, $r['guardians']->count()) }}" class="font-semibold align-top">{{ $r['kid']->full_name }}</td>@endif
        <td class="align-top whitespace-nowrap">{{ $gr['g']->position }}. {{ $gr['g']->name }}<div class="text-xs text-ink-mute">{{ __(\App\Models\KidGuardian::relations()[$gr['g']->relation] ?? '') }}</div></td>
        <td class="align-top">
            @forelse($gr['contacts'] as $c)
                <div class="flex items-center gap-2 py-0.5 text-sm">
                    <span>{{ $c->first_name }} @if($c->username)<span class="text-ink-mute">&#64;{{ $c->username }}</span>@endif <span class="text-xs text-ink-mute">#{{ $c->tg_user_id }}</span></span>
                    @if(! $c->consented)<span class="badge badge-orange">{{ __('rozilik kutilmoqda') }}</span>@elseif($c->blocked)<span class="badge badge-red">{{ __('bloklagan') }}</span>@else<span class="badge badge-leaf">{{ __('ulangan') }}</span>@endif
                    <form method="post" action="{{ route('app.telegram.contact.remove', $c) }}" onsubmit="return confirm('{{ __("Bu akkaunt uzilsinmi?") }}')">@csrf @method('delete')<button class="text-xs font-semibold text-danger hover:underline">{{ __("O'chirish") }}</button></form>
                </div>
            @empty<span class="text-sm text-ink-mute">—</span>@endforelse
        </td>
        <td class="align-top" x-data="{ copied: false, qr: false }">
            @if($gr['link'])
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="btn btn-sm" @click="navigator.clipboard.writeText('{{ $gr['link'] }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? '✓' : '{{ __('Nusxa') }}'"></button>
                    <button type="button" class="btn btn-sm" @click="qr = !qr; if (qr) $nextTick(() => window.kdQr($refs.cv, '{{ $gr['link'] }}'))">QR</button>
                    <form method="post" action="{{ route('app.telegram.revoke', $gr['g']) }}" onsubmit="return confirm('{{ __("Eski havola ishlamay qoladi. Davom etasizmi?") }}')">@csrf<button class="btn btn-sm">{{ __('Yangilash') }}</button></form>
                </div>
                <canvas x-ref="cv" x-show="qr" x-cloak class="mt-2 rounded-lg border border-line"></canvas>
            @else<span class="text-xs text-ink-mute">{{ __('Bot ulanmagan') }}</span>@endif
        </td>
    </tr>
    @endforeach
    @if($r['guardians']->isEmpty())<tr><td class="font-semibold">{{ $r['kid']->full_name }}</td><td colspan="3" class="text-sm text-ink-mute">{{ __("Vasiy kiritilmagan") }}</td></tr>@endif
@empty<tr><td colspan="4"><div class="empty">{{ __("Guruhda bola yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
@endsection
