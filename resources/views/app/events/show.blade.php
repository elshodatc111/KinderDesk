@extends('layouts.app')
@section('title', $event->title)
@section('content')
@php
    $money = fn ($n) => number_format($n, 0, '.', ' ');
    $st = ['draft'=>['Qoralama','badge-gray'],'pending'=>['Tasdiqlashni kutmoqda','badge-orange'],'registration'=>["Ro'yxatga olish",'badge-sky'],'locked'=>['Narx qotirilgan','badge-grape'],'held'=>["O'tkazildi",'badge-leaf'],'closed'=>['Yopilgan','badge-gray'],'cancelled'=>['Bekor qilingan','badge-red']];
    $s = $event->status;
    $editable = in_array($s, ['draft','pending','registration','locked'], true);
    $kindLabel = ['transport'=>'Transport','ticket'=>'Chipta','food'=>'Ovqat','other'=>'Boshqa'];
    $btn = fn ($route, $label, $cls = 'btn-primary', $confirm = null) => '<form method="post" action="'.$route.'" '.($confirm ? 'onsubmit="return confirm(\''.e($confirm).'\')"' : '').'>'.csrf_field().'<button class="btn '.$cls.'">'.e(__($label)).'</button></form>';
@endphp
<div class="w-full space-y-6">
    <div class="card card-pad flex flex-wrap gap-4 items-center justify-between">
        <div>
            <div class="flex items-center gap-2"><span class="badge {{ $st[$s][1] }}">{{ __($st[$s][0]) }}</span>@if($event->reapproval)<span class="badge badge-orange">{{ __('Qayta tasdiqlash kerak') }}</span>@endif</div>
            <p class="mt-2 text-sm text-ink-mute">{{ __('Sana') }}: <b>{{ $event->date->format('d.m.Y') }}</b>@if($event->deadline) · {{ __("Ro'yxat muddati") }}: <b>{{ $event->deadline->format('d.m.Y') }}</b>@endif</p>
            @if($event->note)<p class="mt-1 text-sm">{{ $event->note }}</p>@endif
        </div>
        <div class="text-right">
            <p class="text-sm text-ink-mute">{{ $event->price ? __('Qotirilgan narx') : __('Taxminiy narx (hozirgi ro\'yxat bo\'yicha)') }}</p>
            <p class="text-2xl font-bold">{{ $money($price) }} <span class="text-sm font-normal">{{ __("so'm / bola") }}</span></p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @if($s === 'draft')
            {!! $btn(route('app.events.submit', $event), "Direktor tasdig'iga yuborish") !!}
        @endif
        @if($s === 'pending')
            @can('events.approve')
                {!! $btn(route('app.events.approve', $event), 'Tasdiqlash') !!}
                {!! $btn(route('app.events.reject', $event), 'Qaytarish', 'btn') !!}
            @else
                <p class="text-sm text-ink-mute">{{ __("Direktor tasdig'ini kutmoqda") }}</p>
            @endcan
        @endif
        @if($s === 'registration')
            {!! $btn(route('app.events.lock', $event), "Ro'yxatni yopish va narxni qotirish", 'btn-primary', __("Narx qotiriladi, keyin bola qo'shib bo'lmaydi")) !!}
        @endif
        @if($s === 'locked')
            {!! $btn(route('app.events.hold', $event), "Tadbir o'tkazildi") !!}
        @endif
        @if($s === 'held')
            {!! $btn(route('app.events.close', $event), 'Yakunlash', 'btn-primary', __('Yakunlangach xodimlar haqi ish haqiga yoziladi')) !!}
        @endif
        @if(! in_array($s, ['closed','cancelled'], true))
            <form method="post" action="{{ route('app.events.destroy', $event) }}" onsubmit="return confirm(@js(__('Tadbir bekor qilinadi')))">@csrf @method('delete')<button class="btn !text-danger">{{ in_array($s, ['draft','pending'], true) ? __("O'chirish") : __('Bekor qilish') }}</button></form>
        @endif
    </div>

    {{-- Smeta --}}
    <div class="card card-pad space-y-3">
        <h3 class="font-semibold">{{ __('Xarajatlar smetasi') }}</h3>
        @if(in_array($s, ['locked','held'], true))
        <form method="post" action="{{ route('app.events.costs.actual', $event) }}">@csrf @method('put')
        @endif
        <div class="table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Xarajat') }}</th><th>{{ __('Turi') }}</th><th class="text-right">{{ __('Reja') }}</th><th class="text-right">{{ __('Haqiqiy') }}</th><th></th></tr></thead><tbody>
            @forelse($event->costs as $c)
                <tr><td>{{ $c->title }}</td><td>{{ __($kindLabel[$c->kind]) }}</td><td class="text-right">{{ $money($c->planned) }}</td>
                    <td class="text-right">@if(in_array($s, ['locked','held'], true))<input type="number" min="0" name="actual[{{ $c->id }}]" value="{{ $c->actual }}" class="input !w-32 text-right">@else{{ $c->actual !== null ? $money($c->actual) : '—' }}@endif</td>
                    <td>@if($editable)<button form="del-cost-{{ $c->id }}" class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button>@endif</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty">{{ __("Xarajat kiritilmagan") }}</div></td></tr>
            @endforelse
            @foreach($event->staff as $m)
                <tr><td>{{ $m->employee?->full_name }}</td><td>{{ __('Xodim kunlik haqi') }}</td><td class="text-right">{{ $money($m->pay) }}</td><td class="text-right">{{ $money($m->pay) }}</td>
                    <td>@if($editable)<button form="del-staff-{{ $m->id }}" class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button>@endif</td></tr>
            @endforeach
            <tr class="font-semibold"><td colspan="2">{{ __('Jami') }}</td><td class="text-right">{{ $money($variance['planned']) }}</td><td class="text-right">{{ $money($variance['actual']) }}</td><td></td></tr>
        </tbody></table></div></div>
        @if(in_array($s, ['locked','held'], true))<button class="btn btn-accent">{{ __('Haqiqiy xarajatlarni saqlash') }}</button></form>@endif
        @foreach($event->costs as $c)<form id="del-cost-{{ $c->id }}" method="post" action="{{ route('app.events.costs.destroy', [$event, $c]) }}">@csrf @method('delete')</form>@endforeach
        @foreach($event->staff as $m)<form id="del-staff-{{ $m->id }}" method="post" action="{{ route('app.events.staff.destroy', [$event, $m]) }}">@csrf @method('delete')</form>@endforeach

        @if($editable)
        <div class="grid lg:grid-cols-2 gap-4 pt-2">
            <form method="post" action="{{ route('app.events.costs.store', $event) }}" class="grid grid-cols-2 gap-2 items-end">@csrf
                <div><label class="label">{{ __('Turi') }}</label><select name="kind" class="select">@foreach($kindLabel as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                <div><label class="label">{{ __('Summa (so\'m)') }}</label><input type="number" min="0" name="planned" class="input" required></div>
                <div class="col-span-2"><label class="label">{{ __('Nomi') }}</label><input name="title" class="input" required placeholder="{{ __('Masalan: avtobus ijarasi') }}"></div>
                <button class="btn btn-accent col-span-2">{{ __("Xarajat qo'shish") }}</button>
            </form>
            <form method="post" action="{{ route('app.events.staff.store', $event) }}" class="grid grid-cols-2 gap-2 items-end">@csrf
                <div class="col-span-2"><label class="label">{{ __('Xodim') }}</label><select name="employee_id" class="select" required><option value="">—</option>@foreach($employees as $p)<option value="{{ $p->id }}">{{ $p->full_name }}</option>@endforeach</select></div>
                <div class="col-span-2"><label class="label">{{ __("Tadbir kuni uchun haq (so'm)") }}</label><input type="number" min="0" name="pay" class="input" required></div>
                <button class="btn btn-accent col-span-2">{{ __("Xodim qo'shish") }}</button>
            </form>
        </div>
        @if($event->approved_total !== null)<p class="help">{{ __("Tasdiqlangan summa") }}: {{ $money($event->approved_total) }}. {{ __("Summa oshsa, direktor qayta tasdiqlaydi.") }}</p>@endif
        @endif
    </div>

    {{-- Bolalar --}}
    <div class="card card-pad space-y-3">
        <h3 class="font-semibold">{{ __('Bolalar') }} ({{ $event->kids->count() }})</h3>
        @if($s === 'registration')
        <div class="grid lg:grid-cols-2 gap-4">
            <form method="post" action="{{ route('app.events.kids.group', $event) }}" class="flex gap-2 items-end">@csrf
                <div class="flex-1"><label class="label">{{ __('Butun guruhni yozish') }}</label><select name="group_id" class="select">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
                <button class="btn btn-accent">{{ __("Qo'shish") }}</button></form>
            <form method="post" action="{{ route('app.events.kids.add', $event) }}" class="flex gap-2 items-end">@csrf
                <div class="flex-1"><label class="label">{{ __('Bolalarni tanlash') }}</label><select name="kid_ids[]" class="select" multiple size="4">@foreach($kidsAvailable as $k)<option value="{{ $k->id }}">{{ $k->full_name }}</option>@endforeach</select></div>
                <button class="btn btn-accent">{{ __("Qo'shish") }}</button></form>
        </div>
        @endif
        <div class="table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Bola') }}</th><th>{{ __('Davomat') }}</th><th>{{ __('Yechildi') }}</th><th></th></tr></thead><tbody>
            @forelse($event->kids as $row)
                <tr><td>{{ $row->kid?->full_name }}</td>
                    <td>@if(in_array($s, ['locked','held'], true))
                        <div class="flex gap-1">@foreach(['present' => 'Keldi', 'absent' => 'Kelmadi'] as $v => $l)
                        <form method="post" action="{{ route('app.events.kids.mark', [$event, $row->kid_id]) }}">@csrf<input type="hidden" name="status" value="{{ $v }}"><button class="btn btn-sm {{ $row->attended === $v ? 'btn-primary' : '' }}">{{ __($l) }}</button></form>@endforeach</div>
                        @else{{ $row->attended ? __($row->attended === 'present' ? 'Keldi' : 'Kelmadi') : '—' }}@endif</td>
                    <td>{{ $row->charged ? '✓' : '—' }}</td>
                    <td>@if($s === 'registration')<form method="post" action="{{ route('app.events.kids.remove', [$event, $row->kid_id]) }}">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form>@endif</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">{{ __("Hali bola yozilmagan") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
        @if($s === 'locked')<p class="help">{{ $collectBefore ? __("Pul ro'yxat yopilganda hammadan yechilgan.") : __("«Keldi» deb belgilangan bolaning hisobidan tadbir narxi yechiladi.") }}</p>@endif
    </div>

    {{-- Farq hisoboti --}}
    @if(in_array($s, ['locked','held','closed'], true))
    <div class="card card-pad">
        <h3 class="font-semibold mb-3">{{ __('Farq hisoboti') }}</h3>
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <div class="rounded-lg border border-ink/10 p-3"><p class="text-xs text-ink-mute">{{ __('Reja') }}</p><p class="text-lg font-semibold">{{ $money($variance['planned']) }}</p></div>
            <div class="rounded-lg border border-ink/10 p-3"><p class="text-xs text-ink-mute">{{ __('Haqiqiy xarajat') }}</p><p class="text-lg font-semibold">{{ $money($variance['actual']) }}</p></div>
            <div class="rounded-lg border border-ink/10 p-3"><p class="text-xs text-ink-mute">{{ __("Yig'ilgan") }}</p><p class="text-lg font-semibold">{{ $money($variance['collected']) }}</p></div>
            <div class="rounded-lg border border-ink/10 p-3"><p class="text-xs text-ink-mute">{{ __('Farq (yig\'ilgan − xarajat)') }}</p><p class="text-lg font-semibold {{ $variance['variance'] < 0 ? 'text-danger' : 'text-leaf-700' }}">{{ $money($variance['variance']) }}</p></div>
        </div>
    </div>
    @endif
</div>
@endsection
