@extends('layouts.app')
@section('title', $kid->full_name)
@section('actions')
@can('kids.manage')<a href="{{ route('app.kids.edit', $kid) }}" class="btn btn-sm"><x-icon name="edit" /> Tahrirlash</a>@endcan
@endsection
@section('content')
@php
    $accLabels = \App\Models\FinanceTransaction::accountLabels();
    $statusBadge = ['pending' => ['Kutilmoqda', 'badge-orange'], 'canceled' => ['Bekor', 'badge-gray']];
@endphp
<div class="grid lg:grid-cols-[22rem_1fr] gap-6 items-start" x-data="{ modal: null }" @keydown.escape.window="modal = null">
    {{-- Chap: kartochka --}}
    <aside class="space-y-4">
        <div class="card-sticker p-5">
            <div class="flex items-center gap-4">
                <span class="avatar avatar-lg" style="background:{{ $kid->gender==='female' ? 'var(--color-berry-100)' : 'var(--color-sky-100)' }}">{{ mb_substr($kid->full_name,0,1) }}</span>
                <div class="min-w-0"><h2 class="font-semibold text-xl leading-tight">{{ $kid->full_name }}</h2><p class="text-sm font-medium text-ink-soft">{{ $kid->age() }} · {{ $kid->birth_date->format('d.m.Y') }}</p>
                    @if($kid->status==='archived')<span class="badge badge-gray mt-1">Arxivda</span>@endif</div>
            </div>
            <dl class="mt-4 space-y-2 text-sm font-medium">
                <div class="flex justify-between gap-3"><dt class="text-ink-mute">Guruh</dt><dd>{{ $kid->currentLink?->group?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-mute">Ota-ona</dt><dd>{{ $kid->parent_name ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-mute">Telefon</dt><dd><a href="tel:+{{ $kid->parent_phone }}" class="underline">{{ format_phone($kid->parent_phone) }}</a></dd></div>
                @if($kid->parent_phone2)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Qo'shimcha</dt><dd>{{ format_phone($kid->parent_phone2) }}</dd></div>@endif
                @php $gg = $kid->guardians->firstWhere('position', 2); @endphp
                @if($gg)<div class="flex justify-between gap-3"><dt class="text-ink-mute">{{ __('Vasiy') }} 2 ({{ __(\App\Models\KidGuardian::relations()[$gg->relation] ?? 'Boshqa') }})</dt><dd>{{ $gg->name ?: '—' }} · <a href="tel:+{{ $gg->phone }}" class="underline">{{ format_phone($gg->phone) }}</a>@if($gg->is_payer) · {{ __("to'lovchi") }}@endif</dd></div>@endif
                @if($kid->address)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Manzil</dt><dd class="text-right">{{ $kid->address }}</dd></div>@endif
                @if($kid->certificate_no)<div class="flex justify-between gap-3"><dt class="text-ink-mute">Guvohnoma</dt><dd>{{ $kid->certificate_no }}</dd></div>@endif
                <div class="flex justify-between gap-3"><dt class="text-ink-mute">Oylik narx</dt><dd>{{ money($kid->custom_fee ?? $kid->currentLink?->group?->monthly_fee ?? 0) }} so'm @if($kid->custom_fee !== null)<span class="badge badge-sun">shaxsiy</span>@endif</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-mute">Qabul sanasi</dt><dd>{{ $kid->enrolled_on?->format('d.m.Y') ?? '—' }}</dd></div>
            </dl>
            @if($kid->note)<p class="mt-3 text-sm font-semibold text-ink-soft border-t border-line pt-3">{{ $kid->note }}</p>@endif
        </div>

        @if($kid->status === 'active' && ($kid->extraLinks->isNotEmpty() || $kid->clubLinks->isNotEmpty() || $extraGroups->isNotEmpty() || $clubs->isNotEmpty()))
        <div class="card card-pad space-y-3">
            <p class="label !mb-0">{{ __("Qo'shimcha guruh va to'garaklar") }}</p>
            @foreach($kid->extraLinks as $l)
                <div class="flex items-center justify-between gap-2 text-sm"><span class="font-semibold"><span class="badge {{ $l->group->type==='evening' ? 'badge-grape' : 'badge-orange' }} mr-1">{{ __($l->group->typeLabel()) }}</span>{{ $l->group->name }}</span>
                    @can('groups.manage')<form method="post" action="{{ route('app.groups.kids.leave', [$l->group, $l]) }}">@csrf @method('delete')<button class="btn-quiet is-danger">{{ __('Chiqarish') }}</button></form>@endcan</div>
            @endforeach
            @foreach($kid->clubLinks as $l)
                <div class="flex items-center justify-between gap-2 text-sm"><span class="font-semibold"><span class="badge badge-sky mr-1">{{ __("To'garak") }}</span>{{ $l->club->name }}</span>
                    @can('clubs.manage')<form method="post" action="{{ route('app.clubs.leave', [$l->club, $l]) }}">@csrf @method('delete')<button class="btn-quiet is-danger">{{ __('Chiqarish') }}</button></form>@endcan</div>
            @endforeach
            @can('kids.manage')
            @if($extraGroups->isNotEmpty() || $clubs->isNotEmpty())
            <form method="post" action="{{ route('app.kids.extra.add', $kid) }}" class="flex gap-2 pt-2 border-t border-line">@csrf
                <select name="target" class="select">
                    @foreach($extraGroups as $g)<option value="g:{{ $g->id }}">{{ $g->name }} ({{ __($g->typeLabel()) }})</option>@endforeach
                    @foreach($clubs as $c)<option value="c:{{ $c->id }}">{{ $c->name }} ({{ __("To'garak") }})</option>@endforeach
                </select><button class="btn btn-sm">{{ __('Yozish') }}</button></form>
            @endif
            @endcan
        </div>
        @endif

        @can('kids.manage')
        @if ($kid->status === 'active')
        <div class="card card-pad space-y-3">
            <form method="post" action="{{ route('app.kids.transfer', $kid) }}" class="space-y-2">@csrf
                <p class="label">Boshqa guruhga o'tkazish</p>
                <select name="group_id" class="select">@foreach($groups as $g)@continue($g->id === $kid->currentLink?->group_id)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select>
                <button class="btn btn-sm btn-block">O'tkazish</button>
            </form>
            <form method="post" action="{{ route('app.kids.archive', $kid) }}" onsubmit="return confirm(@js(__('Bola arxivga o\'tkaziladi va guruhdan chiqariladi. Davom etasizmi?')))">@csrf
                <button class="btn btn-sm btn-block !text-danger">Arxivga o'tkazish (bog'chadan chiqdi)</button></form>
        </div>
        @else
        <form method="post" action="{{ route('app.kids.restore', $kid) }}" class="card card-pad space-y-2">@csrf
            <p class="label">Qayta qabul qilish</p>
            <select name="group_id" class="select">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select>
            <button class="btn btn-primary btn-sm btn-block">Qayta qabul qilish</button>
        </form>
        @endif
        @endcan
    </aside>

    {{-- O'ng --}}
    <div class="space-y-5 min-w-0">
        <div class="grid sm:grid-cols-3 gap-4">
            <div class="tile sm:col-span-2" style="--tile-bg:{{ $kid->balance < 0 ? 'var(--color-berry-50)' : 'var(--color-leaf-50)' }};--tile-accent:{{ $kid->balance < 0 ? 'var(--color-danger)' : 'var(--color-leaf-500)' }}">
                <span class="tile-icon"><x-icon name="wallet" /></span>
                <p class="tile-label">{{ $kid->balance < 0 ? 'Qarz' : 'Hisobda' }}</p>
                <p class="tile-value">{{ money(abs($kid->balance)) }} <span class="text-sm">so'm</span></p>
                @if($pending > 0)<p class="text-xs font-medium text-marigold-700 mt-1">Tasdiq kutayotgan: {{ money($pending) }} so'm</p>@endif
            </div>
            @feature('attendance')
            <div class="tile" style="--tile-bg:var(--color-sky-50);--tile-accent:var(--color-sky-500)"><span class="tile-icon"><x-icon name="attendance" /></span>
                <p class="tile-label">Shu oy davomad</p><p class="tile-value">{{ $att['present'] ?? 0 }} <span class="text-sm">kun</span></p>
                <p class="text-xs font-medium text-ink-soft">Kelmagan: {{ ($att['absent'] ?? 0) + ($att['sick'] ?? 0) }}</p></div>
            @endfeature
        </div>

        @feature('desk')
        @can('finance.payments')
        @if($kid->status === 'active')
        <div class="flex flex-wrap gap-2">
            <button class="btn btn-accent" @click="modal = 'pay'"><x-icon name="plus" /> To'lov qabul qilish</button>
            <button class="btn" @click="modal = 'refund'">Qaytarish</button>
            <button class="btn" @click="modal = 'adjust'">Hisobni tuzatish</button>
        </div>
        @endif
        @endcan
        @endfeature

        <div class="tabs">
            <a class="tab {{ $tab==='finance' ? 'is-active' : '' }}" href="{{ route('app.kids.show', [$kid, 'tab' => 'finance']) }}">Hisob-kitob</a>
            <a class="tab {{ $tab==='groups' ? 'is-active' : '' }}" href="{{ route('app.kids.show', [$kid, 'tab' => 'groups']) }}">Guruhlar tarixi</a>
            @feature('health')<a class="tab" href="{{ route('app.health.show', $kid) }}">{{ __("Sog'liq") }}@if($kid->health?->has_alert) <span class="text-danger">⚠</span>@endif</a>@endfeature
            <a class="tab {{ $tab==='notes' ? 'is-active' : '' }}" href="{{ route('app.kids.show', [$kid, 'tab' => 'notes']) }}">Izohlar ({{ $kid->notes->count() }})</a>
        </div>

        @if ($tab === 'finance')
        <div class="card table-wrap"><div class="table-scroll"><table class="table"><thead><tr><th>Sana</th><th>Turi</th><th>Izoh</th><th class="text-right">Summa</th></tr></thead><tbody>
            @forelse ($ledger as $l)
                <tr class="{{ $l->status==='canceled' ? 'opacity-50' : '' }}"><td class="whitespace-nowrap">{{ $l->created_at->format('d.m.Y') }}</td>
                    <td>{{ $l->typeLabel() }} @if(isset($statusBadge[$l->status]))<span class="badge {{ $statusBadge[$l->status][1] }}">{{ $statusBadge[$l->status][0] }}</span>@endif</td>
                    <td class="text-ink-soft">{{ $l->description }}</td>
                    <td class="text-right font-semibold {{ $l->amount < 0 ? 'text-danger' : 'text-leaf-700' }}">{{ $l->amount > 0 ? '+' : '−' }}{{ money(abs($l->amount)) }}</td></tr>
            @empty<tr><td colspan="4"><div class="empty">Hali yozuv yo'q</div></td></tr>@endforelse
        </tbody></table></div></div>
        @elseif ($tab === 'groups')
        <div class="card table-wrap"><table class="table"><thead><tr><th>Guruh</th><th>Boshlangan</th><th>Tugagan</th></tr></thead><tbody>
            @foreach ($kid->links as $l)<tr><td class="font-semibold">{{ $l->group?->name }}</td><td>{{ $l->started_on->format('d.m.Y') }}</td><td>{{ $l->ended_on?->format('d.m.Y') ?? 'Hozir' }}</td></tr>@endforeach
        </tbody></table></div>
        @else
        <div class="space-y-3">
            @can('kids.manage')<form method="post" action="{{ route('app.kids.notes.store', $kid) }}" class="flex gap-2">@csrf<input name="body" class="input" placeholder="Izoh yozing (allergiya, ota-ona bilan suhbat...)" required><button class="btn btn-primary">Qo'shish</button></form>@endcan
            @forelse ($kid->notes as $n)
                <div class="card card-pad flex justify-between gap-3"><div><p class="font-semibold">{{ $n->body }}</p><p class="text-xs font-medium text-ink-mute mt-1">{{ $n->author?->name }} · {{ $n->created_at->format('d.m.Y H:i') }}</p></div>
                    @can('kids.manage')<form method="post" action="{{ route('app.kids.notes.destroy', [$kid, $n]) }}">@csrf @method('delete')<button class="btn-quiet !text-danger">O'chirish</button></form>@endcan</div>
            @empty<div class="empty">Izoh yo'q</div>@endforelse
        </div>
        @endif
    </div>

    {{-- Modallar --}}
    @feature('desk')
    @foreach (['pay' => ["To'lov qabul qilish", 'app.kids.payment'], 'refund' => ["To'lovni qaytarish", 'app.kids.refund']] as $key => [$title, $route])
    <div x-show="modal === '{{ $key }}'" x-cloak class="modal-backdrop" @click.self="modal = null">
        <form method="post" action="{{ route($route, $kid) }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
            <h3 class="text-xl font-semibold">{{ $title }}</h3>
            <div><label class="label">Summa (so'm)</label><input name="amount" inputmode="numeric" class="input text-xl font-semibold" :value="shown" @input="onInput($event)" required
                @if($key==='pay' && $kid->balance < 0) x-init="raw = '{{ abs($kid->balance) }}'" @endif></div>
            <div><label class="label">Hisob</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }}</option>@endforeach</select></div>
            <div><label class="label">Izoh</label><input name="description" class="input"></div>
            <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal = null">Bekor qilish</button><button class="btn btn-primary">Tasdiqlash</button></div>
        </form>
    </div>
    @endforeach
    <div x-show="modal === 'adjust'" x-cloak class="modal-backdrop" @click.self="modal = null">
        <form method="post" action="{{ route('app.kids.adjust', $kid) }}" class="modal-panel space-y-4" x-data="moneyInput()">@csrf
            <h3 class="text-xl font-semibold">Hisobni tuzatish</h3>
            <div><label class="label">Turi</label><select name="direction" class="select"><option value="credit">Qarzni kechish / hisobga qo'shish (+)</option><option value="debit">Qo'shimcha qarz yozish (−)</option></select></div>
            <div><label class="label">Summa (so'm)</label><input name="amount" inputmode="numeric" class="input" :value="shown" @input="onInput($event)" required></div>
            <div><label class="label">Sabab</label><input name="description" class="input" required></div>
            <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal = null">Bekor qilish</button><button class="btn btn-primary">Saqlash</button></div>
        </form>
    </div>
    @endfeature
</div>
@endsection
