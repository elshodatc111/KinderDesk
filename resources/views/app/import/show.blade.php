@extends('layouts.app')
@section('title', "Importni oldindan ko'rish")
@section('content')
@php $s = $run->summary; @endphp
<div class="flex flex-wrap gap-2 mb-4">
    <span class="badge badge-gray">{{ __('Jami') }}: {{ $s['total'] ?? 0 }}</span>
    <span class="badge badge-leaf">{{ __('Yaroqli') }}: {{ $s['ok'] ?? 0 }}</span>
    <span class="badge badge-orange">{{ __('Ogohlantirish') }}: {{ $s['warn'] ?? 0 }}</span>
    <span class="badge badge-red">{{ __("O'tkaziladi") }}: {{ $s['skip'] ?? 0 }}</span>
    @if(! empty($s['new_groups']))<span class="badge badge-orange">{{ __('Yangi guruhlar') }}: {{ implode(', ', $s['new_groups']) }}</span>@endif
</div>
@if($run->status === 'preview')
<div class="flex gap-2 mb-4">
    <form method="post" action="{{ route('app.import.commit', $run) }}" onsubmit="return confirm('{{ __("Yaroqli qatorlar bazaga yoziladi. Davom etasizmi?") }}')">@csrf<button class="btn btn-primary" @disabled(($s['ok'] ?? 0) + ($s['warn'] ?? 0) === 0)>{{ __('Tasdiqlash va import qilish') }}</button></form>
    <form method="post" action="{{ route('app.import.discard', $run) }}">@csrf @method('delete')<button class="btn">{{ __('Bekor qilish') }}</button></form>
</div>
<div class="card table-wrap"><div class="table-scroll"><table class="table">
<thead><tr><th>{{ __('Qator') }}</th><th>{{ __('Ism') }}</th><th>{{ __('Telefon') }}</th><th>{{ __('Guruh') }}</th><th>{{ __('Holat') }}</th></tr></thead><tbody>
@foreach($items as $it)
<tr><td>{{ $it['line'] }}</td><td class="font-semibold">{{ $it['d']['full_name'] }}</td><td>{{ $it['d']['parent_phone'] ?? $it['d']['phone'] ?? '' }}</td><td>{{ $it['d']['group'] ?? '' }}</td>
    <td><span class="badge {{ ['ok' => 'badge-leaf', 'warn' => 'badge-orange', 'skip' => 'badge-red'][$it['status']] }}">{{ ['ok' => 'OK', 'warn' => __('Ogohlantirish'), 'skip' => __("O'tkaziladi")][$it['status']] }}</span>
        <span class="text-xs text-ink-soft">{{ collect($it['msgs'])->map(fn ($m) => __($m))->implode('; ') }}</span></td></tr>
@endforeach
</tbody></table></div></div>
@else
<p class="banner banner-info">{{ __('Import yakunlangan: :n ta yozilgan.', ['n' => $s['made'] ?? 0]) }}</p>
@endif
@endsection
