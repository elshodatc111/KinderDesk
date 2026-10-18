@extends('layouts.app')
@section('title', 'Telegram')
@section('content')
@include('app.telegram._tabs')
<p class="text-sm text-ink-soft mb-4">{{ __("Tarbiyachi va oshpaz shaxsiy havola orqali botga ulanadi. Rasm/video/matnni botga yuborib, ota-onalarga tarqatadi. Ruxsatlar lavozimdan olinadi (Telegram bot bo'limi).") }}</p>
<div class="card table-wrap"><div class="table-scroll"><table class="table">
<thead><tr><th>{{ __('Xodim') }}</th><th>{{ __('Lavozim') }}</th><th>{{ __('Telegram') }}</th><th>{{ __('Havola') }}</th></tr></thead><tbody>
@foreach($rows as $r)
<tr>
    <td class="font-semibold">{{ $r['e']->full_name }}</td><td>{{ $r['e']->position }}</td>
    <td>@if($r['contact'])<span class="text-sm">{{ $r['contact']->first_name }} @if($r['contact']->username)<span class="text-ink-mute">&#64;{{ $r['contact']->username }}</span>@endif</span>
        <form method="post" class="inline" action="{{ route('app.telegram.employee-contact.remove', $r['contact']) }}" onsubmit="return confirm('{{ __("Uzilsinmi?") }}')">@csrf @method('delete')<button class="ml-2 text-xs font-semibold text-danger hover:underline">{{ __("O'chirish") }}</button></form>@else<span class="text-ink-mute">—</span>@endif</td>
    <td x-data="{ copied: false, qr: false }">
        @if($r['link'])<div class="flex gap-1.5"><button type="button" class="btn btn-sm" @click="navigator.clipboard.writeText('{{ $r['link'] }}'); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? '✓' : '{{ __('Nusxa') }}'"></button>
            <button type="button" class="btn btn-sm" @click="qr = !qr; if (qr) $nextTick(() => window.kdQr($refs.cv, '{{ $r['link'] }}'))">QR</button></div>
        <canvas x-ref="cv" x-show="qr" x-cloak class="mt-2 rounded-lg border border-line"></canvas>@else<span class="text-xs text-ink-mute">{{ __('Bot ulanmagan') }}</span>@endif
    </td>
</tr>
@endforeach
</tbody></table></div></div>
@endsection
