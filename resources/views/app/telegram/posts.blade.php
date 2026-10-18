@extends('layouts.app')
@section('title', 'Telegram')
@section('content')
@include('app.telegram._tabs')
@if($canSend)
<form method="post" action="{{ route('app.telegram.announce') }}" class="card card-pad space-y-3 mb-6 max-w-3xl">@csrf
    <h2 class="section-title">{{ __("Ota-onalarga e'lon yuborish") }}</h2>
    <textarea name="text" rows="4" maxlength="3500" class="textarea" required placeholder="{{ __("E'lon matni") }}"></textarea>
    <div class="flex flex-wrap items-end gap-3">
        <div><label class="label">{{ __('Kimga') }}</label><select name="group_id" class="select"><option value="">{{ __('Barcha ota-onalar') }}</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
        <button class="btn btn-primary">{{ __('Yuborish') }}</button>
    </div>
    <p class="help">{{ __("Faqat rozilik bergan ota-onalarga boradi. Yuborilgandan keyin 30 daqiqa ichida o'chirish mumkin.") }}</p>
</form>
@endif
<div class="card table-wrap"><div class="table-scroll"><table class="table">
<thead><tr><th>{{ __('Vaqt') }}</th><th>{{ __('Turi') }}</th><th>{{ __('Kimga') }}</th><th>{{ __('Kim yubordi') }}</th><th>{{ __('Qabul qiluvchilar') }}</th><th></th></tr></thead><tbody>
@forelse($posts as $p)
<tr>
    <td class="whitespace-nowrap">{{ ($p->sent_at ?? $p->created_at)->format('d.m.Y H:i') }}</td>
    <td>{{ __($p->kindLabel()) }} @if($p->state === 'deleted')<span class="badge badge-gray">{{ __("o'chirilgan") }}</span>@endif</td>
    <td>{{ $p->group?->name ?? __('Hamma') }}</td>
    <td>{{ $p->employee?->full_name ?? ($p->user_id ? __('Veb') : '—') }}</td>
    <td>{{ $p->recipients }}</td>
    <td>@if($p->canDelete())<form method="post" action="{{ route('app.telegram.post.delete', $p) }}" onsubmit="return confirm('{{ __("Ota-onalardan o'chirilsinmi?") }}')">@csrf @method('delete')<button class="btn btn-sm">{{ __("O'chirish") }}</button></form>@endif</td>
</tr>
@empty<tr><td colspan="6"><div class="empty">{{ __("Hali post yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
@endsection
