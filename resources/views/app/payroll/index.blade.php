@extends('layouts.app')
@section('title', 'Ish haqi')
@section('content')
@php
    $accLabels = \App\Models\FinanceTransaction::accountLabels();
    $sLabels = ['fixed'=>'Belgilangan oylik','attendance'=>'Davomadga qarab','hybrid'=>'Aralash (asosiy + KPI)','scheme'=>'Sxema bo\'yicha'];
    $stLabels = ['draft' => ['Qoralama', 'badge-marigold'], 'approved' => ['Tasdiqlangan', 'badge-sky'], 'paid' => ["To'langan", 'badge-leaf']];
    $itemLabels = ['premium' => 'Premiya', 'event_bonus' => 'Tadbir bonusi', 'fine' => 'Jarima', 'deduction' => 'Ushlanma', 'advance' => 'Avans'];
@endphp
<div x-data="payroll()" @keydown.escape.window="close()">
<div class="flex flex-wrap items-end justify-between gap-4 mb-5">
    <form method="get"><label class="label">Oy</label><input type="month" name="month" value="{{ $period->format('Y-m') }}" class="input !w-48" onchange="this.form.submit()"></form>
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge badge-sky">Usul: {{ __($schemes ? $sLabels['scheme'] : $sLabels[$strategy]) }}</span>
        @if($schemes)
            @can('payroll.manage')<a href="{{ route('app.payroll.schemes') }}" class="btn btn-sm">Sxemalar</a>@endcan
            @can('payroll.items')<a href="{{ route('app.payroll.complaints') }}" class="btn btn-sm">Shikoyatlar</a>
                <button type="button" class="btn btn-sm" @click="modal='bulk'">Hammaga premiya / jarima</button>@endcan
            @can('payroll.approve')<form method="post" action="{{ route('app.payroll.approve-all') }}" class="inline">@csrf<input type="hidden" name="month" value="{{ $period->format('Y-m') }}"><button class="btn btn-sm btn-primary">Hammasini tasdiqlash</button></form>@endcan
        @endif
        @can('payroll.manage')<form method="post" action="{{ route('app.payroll.generate') }}">@csrf<input type="hidden" name="month" value="{{ $period->format('Y-m') }}"><button class="btn btn-accent">Oy uchun hisoblash</button></form>@endcan
    </div>
</div>
@if($totalDue > 0)<div class="banner banner-warn mb-4"><x-icon name="wallet" /><p>To'lanishi kerak: <b>{{ money($totalDue) }} so'm</b></p></div>@endif
@if($schemes)<div class="banner banner-info mb-4"><x-icon name="info" /><p>Har oyning birinchi ishchi kunida tizim o'tgan oy uchun <b>qoralama</b> tayyorlaydi. Direktor tafsilotni ko'rib, premiya/jarima qo'shadi va tasdiqlaydi; tasdiqlangandan keyingina to'lanadi.</p></div>@endif
<div class="card table-wrap"><div class="table-scroll"><table class="table">
    <thead><tr><th>Xodim</th><th>Asos</th><th>Kun / KPI</th><th>Bonus / jarima</th><th class="text-right">Jami</th><th class="text-right">To'langan</th><th></th></tr></thead><tbody>
@forelse ($rows as $r) @php $e = $r['employee']; $s = $r['slip']; $p = $r['preview']; $eItems = $items[$e->id] ?? collect(); @endphp
    <tr>
        <td><a href="{{ route('app.employees.show', $e) }}" class="font-semibold hover:underline">{{ $e->full_name }}</a><span class="block text-xs font-semibold text-ink-soft">{{ $e->positionLabel() }}</span>
            @if($s && $schemes)<span class="badge {{ $stLabels[$s->status][1] }} mt-1">{{ __($stLabels[$s->status][0]) }}</span>@endif</td>
        @if ($s)
            <td>{{ money($s->base_amount) }}</td>
            <td class="text-sm font-semibold text-ink-soft">@if($s->strategy==='attendance'){{ $s->worked_days }}/{{ $s->working_days }} kun @elseif($s->strategy==='hybrid'){{ $s->kids_count }} bola → {{ money($s->kpi_amount) }}@elseif($s->strategy==='scheme')KPI / bonus: {{ money($s->kpi_amount) }}@else —@endif @if($s->late_count)<span class="block">Kechikish: {{ $s->late_count }}</span>@endif</td>
            <td>
                @if($schemes)
                    <span class="text-sm font-semibold">+{{ money($s->bonus) }} / −{{ money($s->penalty) }}@if($s->advance) / avans −{{ money($s->advance) }}@endif</span>
                @else
                    @can('payroll.manage')
                    <form method="post" action="{{ route('app.payroll.adjust', $s) }}" class="flex items-center gap-1">@csrf @method('put')
                        <input name="bonus" type="number" min="0" value="{{ $s->bonus }}" class="input !w-24 !py-1" title="Bonus"><input name="penalty" type="number" min="0" value="{{ $s->penalty }}" class="input !w-24 !py-1" title="Jarima"><button class="btn btn-sm">OK</button></form>
                    @else +{{ money($s->bonus) }} / −{{ money($s->penalty) }} @endcan
                @endif
            </td>
            <td class="text-right font-semibold">{{ money($s->total) }}</td>
            <td class="text-right font-semibold {{ $s->status==='paid' ? 'text-leaf-700' : '' }}">{{ money($s->paid) }}</td>
            <td class="whitespace-nowrap">
                @if($schemes)<button type="button" class="btn btn-sm" @click="openDetail({{ $s->id }})">Tafsilot</button>
                    @can('payroll.items')@if($s->status==='draft')<button type="button" class="btn btn-sm" @click="openItem({{ $e->id }}, @js($e->full_name))">Qator +</button>@endif @endcan
                    @can('payroll.approve')
                        @if($s->status==='draft')<form method="post" action="{{ route('app.payroll.approve', $s) }}" class="inline">@csrf<button class="btn btn-sm btn-primary">Tasdiqlash</button></form>
                        @elseif($s->paid == 0)<form method="post" action="{{ route('app.payroll.reopen', $s) }}" class="inline">@csrf<button class="btn btn-sm">Qayta ochish</button></form>@endif
                    @endcan
                @endif
                @can('payroll.manage')
                    @if($s->status !== 'draft')
                        @if($s->remaining() > 0)<button class="btn btn-sm btn-primary" @click="pay = {{ $s->id }}; $nextTick(() => $refs['amt{{ $s->id }}'] && ($refs['amt{{ $s->id }}'].value = '{{ $s->remaining() }}'))">To'lash</button>@else<span class="badge badge-leaf">To'langan</span>@endif
                    @endif
                    @if($s->paid == 0 && ($s->status === 'draft' || ! $schemes))<form method="post" action="{{ route('app.payroll.recalc', $s) }}" class="inline">@csrf<button class="btn-quiet" title="Qayta hisoblash"><x-icon name="refresh" class="size-4" /></button></form>
                    <form method="post" action="{{ route('app.payroll.destroy', $s) }}" class="inline" onsubmit="return confirm(@js(__('Varaqa o\'chiriladi')))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form>@endif
                @endcan
            </td>
        @else
            <td class="text-ink-mute">{{ money($p['base_amount']) }}</td>
            <td class="text-sm font-semibold text-ink-mute">@if($p['strategy']==='attendance'){{ $p['worked_days'] }}/{{ $p['working_days'] }} kun @elseif($p['strategy']==='hybrid'){{ $p['kids_count'] }} bola → {{ money($p['kpi_amount']) }}@elseif($p['strategy']==='scheme')KPI / bonus: {{ money($p['kpi_amount']) }}@else —@endif</td>
            <td class="text-ink-mute">@if($schemes && ($p['bonus'] || $p['penalty'] || $p['advance']))+{{ money($p['bonus']) }} / −{{ money($p['penalty']) }}@if($p['advance']) / avans −{{ money($p['advance']) }}@endif @else — @endif</td>
            <td class="text-right text-ink-mute font-medium">{{ money($p['total']) }} <span class="block text-xs">(taxminiy)</span></td><td></td>
            <td>@if($schemes)@can('payroll.items')<button type="button" class="btn btn-sm" @click="openItem({{ $e->id }}, @js($e->full_name))">Qator +</button>@endcan @endif</td>
        @endif
    </tr>
    @if($schemes && $eItems->isNotEmpty())
    <tr class="bg-cloud"><td colspan="7" class="!py-2 text-xs font-medium text-ink-soft">
        @foreach($eItems as $it)<span class="inline-flex items-center gap-1 mr-3">{{ __($itemLabels[$it->type]) }}: <b>{{ in_array($it->type, ['premium','event_bonus']) ? '+' : '−' }}{{ money($it->amount) }}</b>@if($it->reason) <span class="text-ink-mute">({{ $it->reason }})</span>@endif
            @can('payroll.items')@if($it->type !== 'advance')<form method="post" action="{{ route('app.payroll.items.destroy', $it) }}" class="inline">@csrf @method('delete')<button class="text-danger" title="{{ __("O'chirish") }}">×</button></form>@endif @endcan</span>@endforeach
    </td></tr>
    @endif
@empty<tr><td colspan="7"><div class="empty">Faol xodim yo'q</div></td></tr>@endforelse
</tbody></table></div></div>

@can('payroll.manage')
@foreach ($rows as $r) @if($r['slip'])
<div x-show="pay === {{ $r['slip']->id }}" x-cloak class="modal-backdrop" @click.self="pay = null">
    <form method="post" action="{{ route('app.payroll.pay', $r['slip']) }}" class="modal-panel space-y-4">@csrf
        <h3 class="text-xl font-semibold">Ish haqi to'lash: {{ $r['employee']->full_name }}</h3>
        <p class="text-sm font-medium text-ink-soft">Qolgan summa: {{ money($r['slip']->remaining()) }} so'm</p>
        <div><label class="label">Summa (so'm)</label><input name="amount" x-ref="amt{{ $r['slip']->id }}" value="{{ $r['slip']->remaining() }}" inputmode="numeric" class="input text-xl font-semibold" required></div>
        <div><label class="label">Qaysi hisobdan</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }} ({{ money($balances[$a]) }})</option>@endforeach</select></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="pay=null">Bekor qilish</button><button class="btn btn-primary">To'lash</button></div>
    </form>
</div>
@endif @endforeach
@endcan

@if($schemes)
@can('payroll.items')
<div x-show="modal==='item'" x-cloak class="modal-backdrop" @click.self="close()">
    <form method="post" action="{{ route('app.payroll.items.store') }}" class="modal-panel space-y-4">@csrf
        <h3 class="text-xl font-semibold" x-text="itemName"></h3>
        <input type="hidden" name="employee_id" :value="itemEmp"><input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
        <div><label class="label">Turi</label><select name="type" class="select" x-model="itemType">@foreach($itemLabels as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
        <div><label class="label">Summa (so'm)</label><input name="amount" inputmode="numeric" class="input text-xl font-semibold" required></div>
        <div x-show="itemType==='advance'" x-cloak><label class="label">Qaysi hisobdan</label><select name="account" class="select">@foreach($accounts as $a)<option value="{{ $a }}">{{ $accLabels[$a] }} ({{ money($balances[$a]) }})</option>@endforeach</select><p class="help">Avans moliyada xarajat bo'ladi va oy yakunida ish haqidan ayriladi.</p></div>
        <div><label class="label">Sabab</label><input name="reason" maxlength="200" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="close()">Bekor qilish</button><button class="btn btn-primary">Qo'shish</button></div>
    </form>
</div>
<div x-show="modal==='bulk'" x-cloak class="modal-backdrop" @click.self="close()">
    <form method="post" action="{{ route('app.payroll.items.bulk') }}" class="modal-panel space-y-4">@csrf
        <h3 class="text-xl font-semibold">Hammaga premiya yoki jarima</h3><input type="hidden" name="month" value="{{ $period->format('Y-m') }}">
        <div><label class="label">Turi</label><select name="type" class="select"><option value="premium">Premiya</option><option value="fine">Jarima</option></select></div>
        <div><label class="label">Har bir xodimga (so'm)</label><input name="amount" inputmode="numeric" class="input text-xl font-semibold" required></div>
        <div><label class="label">Sabab (masalan, bayram premiyasi)</label><input name="reason" maxlength="200" class="input" required></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="close()">Bekor qilish</button><button class="btn btn-primary">Qo'shish</button></div>
    </form>
</div>
@endcan
<div x-show="modal==='detail'" x-cloak class="modal-backdrop" @click.self="close()">
    <div class="modal-panel is-wide space-y-3" x-show="detail">
        <h3 class="text-xl font-semibold" x-text="detail ? detail.employee + ' — ' + detail.period : ''"></h3>
        <template x-if="detail">
        <div class="space-y-3 text-sm">
            <p>Asos: <b x-text="fmt(detail.slip.base_amount)"></b> · KPI/bonus: <b x-text="fmt(detail.slip.kpi_amount)"></b> · Premiya: <b x-text="fmt(detail.slip.bonus)"></b> · Jarima: <b x-text="fmt(detail.slip.penalty)"></b> · Avans: <b x-text="fmt(detail.slip.advance)"></b> · <span class="text-base">Jami: <b x-text="fmt(detail.slip.total)"></b></span></p>
            <template x-if="detail.detail && detail.detail.rows && detail.detail.rows.length">
                <div><p class="font-semibold mb-1">Guruhlar bo'yicha (W = <span x-text="detail.detail.W"></span> ishchi kun)</p>
                    <table class="table"><thead><tr><th>Guruh</th><th>Vazifa</th><th>n</th><th>T(n)</th><th>1 plus narxi</th><th>Kun</th><th class="text-right">Summa</th></tr></thead><tbody>
                    <template x-for="r in detail.detail.rows" :key="r.group + r.role"><tr><td x-text="r.group"></td><td x-text="r.role==='assistant' ? `Yordamchi` : (r.role==='cook' ? `Oshpaz` : `Tarbiyachi`)"></td><td x-text="r.n"></td><td x-text="fmt(r.T)"></td><td x-text="r.r"></td><td x-text="r.days"></td><td class="text-right" x-text="fmt(r.amount) + (r.sub_amount ? ' (o\'rindosh: ' + fmt(r.sub_amount) + ')' : '')"></td></tr></template></tbody></table>
                    <p class="help mt-1">n — oydagi o'rtacha kelgan bolalar; 1 plus narxi = T(n) ÷ (n × W); kun ulushi = o'sha kungi plus × plus narxi.</p></div>
            </template>
            <template x-if="detail.detail && detail.detail.kind==='admin'">
                <p>Belgilangan: <b x-text="fmt(detail.detail.fixed)"></b> · Yangi bolalar: <b x-text="detail.detail.new_kids"></b> → <b x-text="fmt(detail.detail.new_kids_amount)"></b> · Faol bolalar: <b x-text="detail.detail.active_kids"></b> → <b x-text="fmt(detail.detail.full_amount)"></b></p>
            </template>
            <template x-if="detail.detail && detail.detail.kpi">
                <p>KPI: ota-onaga hisobot <b x-text="detail.detail.kpi.parent_report ? 'ha' : 'yo\'q'"></b> · ota-onalar mamnun <b x-text="detail.detail.kpi.satisfied ? 'ha' : 'yo\'q'"></b> · tadbirlarda ishtirok <b x-text="detail.detail.kpi.events ? 'ha' : 'yo\'q'"></b> (har biri <span x-text="fmt(detail.detail.kpi.unit)"></span>)</p>
            </template>
            <template x-if="detail.items.length"><p>Qatorlar: <template x-for="i in detail.items"><span class="mr-3"><b x-text="i.type"></b> <span x-text="fmt(i.amount)"></span> <span x-text="i.reason ? '(' + i.reason + ')' : ''"></span></span></template></p></template>
        </div></template>
        <div class="flex justify-end"><button type="button" class="btn" @click="close()">Yopish</button></div>
    </div>
</div>
@endif
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('payroll', () => ({
        pay: null, modal: null, itemEmp: null, itemName: '', itemType: 'premium', detail: null,
        close() { this.pay = null; this.modal = null; },
        fmt(n) { return new Intl.NumberFormat('ru-RU').format(n || 0); },
        openItem(id, name) { this.itemEmp = id; this.itemName = name; this.itemType = 'premium'; this.modal = 'item'; },
        async openDetail(id) {
            this.detail = null; this.modal = 'detail';
            const res = await fetch(@js(url('/app/ish-haqi')) + '/' + id + '/tafsilot', { headers: { Accept: 'application/json' } });
            this.detail = await res.json();
        },
    }));
});
</script>
@endpush
