@extends('layouts.app')
@section('title', 'Arizalar')
@section('content')
<div x-data="{ modal: false }" x-init="window.addEventListener('kd:activity', e => { if (e.detail?.kind === 'lead') location.reload() })">
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="tabs"><a class="tab {{ $type==='kid'?'is-active':'' }}" href="{{ route('app.leads.index', ['type'=>'kid']) }}">Bolalar uchun</a><a class="tab {{ $type==='employee'?'is-active':'' }}" href="{{ route('app.leads.index', ['type'=>'employee']) }}">Xodimlar uchun</a></div>
    @can('leads.manage')<button class="btn btn-accent btn-sm" @click="modal = true"><x-icon name="plus" /> Ariza qo'shish</button>@endcan
</div>
@if($formEnabled)
<div class="card card-pad mb-5 flex flex-wrap items-center gap-3"><span class="font-semibold">Ochiq ariza havolasi:</span>
    <input readonly value="{{ $formUrl }}" class="input !w-full sm:!w-96" onclick="this.select()">
    <button type="button" class="btn btn-sm" x-data @click="navigator.clipboard.writeText('{{ $formUrl }}'); $store.toasts.add({title:'Havola nusxalandi', level:'success'})">Nusxalash</button>
    <span class="help">Ota-onalarga yuboring: ariza shu yerga tushadi.</span></div>
@endif
<div class="flex gap-4 overflow-x-auto pb-4">
@foreach ($statuses as $i => $st)
    @php $col = $leads[$st] ?? collect(); $n = count($statuses); $kind = ($n >= 3 && $i === $n-2) ? 'leaf' : (($n >= 3 && $i === $n-1) ? 'berry' : 'teal'); @endphp
    <section class="w-72 shrink-0 rounded-2xl p-3 border border-line" style="background:var(--color-{{ $kind }}-50)">
        <h2 class="flex items-center justify-between font-semibold mb-3 px-1">{{ $st }} <span class="badge">{{ $col->count() }}</span></h2>
        <div class="space-y-3">
        @foreach ($col as $l)
            <article class="card p-3.5 space-y-2" x-data="{ open: false }">
                <div class="flex justify-between gap-2"><p class="font-semibold">{{ $l->name }}</p><span class="text-xs font-medium text-ink-mute">{{ $l->created_at->format('d.m') }}</span></div>
                <div class="flex flex-wrap gap-1">
                    @if(in_array($l->id, $staleIds))<span class="badge badge-red">{{ __("24 soatdan oshdi") }}</span>@endif
                    @if(in_array($l->id, $blocked))<span class="badge badge-red">{{ __("Qora ro'yxat") }}</span>@endif
                    @if($l->next_call_on)<span class="badge {{ $l->result==='open' && $l->next_call_on->lt(today()) ? 'badge-orange' : 'badge-sky' }}">{{ __("Qo'ng'iroq") }}: {{ $l->next_call_on->format('d.m') }}</span>@endif
                    @if($l->assigned_to)<span class="badge badge-gray">{{ $staff->firstWhere('id', $l->assigned_to)?->name }}</span>@endif
                </div>
                <a href="tel:+{{ $l->phone }}" class="text-sm font-medium text-teal-700 underline">{{ format_phone($l->phone) }}</a>
                @if($l->source)<span class="badge badge-gray">{{ $l->source }}</span>@endif
                @if(!empty($l->data))<p class="text-xs font-semibold text-ink-soft">@foreach($l->data as $k => $v)@if($v){{ $k }}: {{ $v }}@if(!$loop->last) · @endif @endif @endforeach</p>@endif
                @if($l->note)<p class="text-sm font-semibold text-ink-soft">{{ $l->note }}</p>@endif
                @can('leads.manage')
                <form method="post" action="{{ route('app.leads.move', $l) }}" class="space-y-1" x-data="{ s: @js($l->status), last: @js(last($statuses)) }">@csrf
                    <select name="status" x-model="s" class="select !py-1 !text-sm" @change="if (s !== last) $el.form.submit()">@foreach($statuses as $s2)<option @selected($s2===$l->status)>{{ $s2 }}</option>@endforeach</select>
                    <div x-show="s === last && s !== @js($l->status)" x-cloak class="space-y-1">
                        <input name="lost_reason" class="input !py-1 !text-sm" placeholder="{{ __('Rad etish sababi') }}" :required="s === last && s !== @js($l->status)">
                        @if($type==='employee' && feature('blacklist'))@can('blacklist.manage')<label class="flex items-center gap-2 text-xs"><input type="checkbox" name="add_blacklist" value="1"> {{ __("Qora ro'yxatga qo'shish") }}</label>@endcan @endif
                        <button class="btn btn-sm btn-block">{{ __('Saqlash') }}</button></div></form>
                @if($l->result==='lost' && $l->lost_reason)<p class="text-xs text-danger font-semibold">{{ $l->lost_reason }}</p>@endif
                <div class="flex items-center justify-between">
                    @if($type==='kid' && $l->result==='won' && !$l->converted_id)<a class="btn btn-sm btn-primary" href="{{ route('app.kids.create', ['name' => $l->name, 'phone' => $l->phone, 'lead' => $l->id]) }}">Bola qilib qabul qilish</a>@elseif($type==='employee' && $l->result==='won')<a class="btn btn-sm btn-primary" href="{{ route('app.employees.create', ['name' => $l->name, 'phone' => $l->phone, 'lead' => $l->id]) }}">Xodim qilish</a>@else<span></span>@endif
                    <form method="post" action="{{ route('app.leads.destroy', $l) }}" onsubmit="return confirm(@js(__('Ariza o\'chiriladi')))">@csrf @method('delete')<button class="btn-quiet !text-danger">O'chirish</button></form></div>
                @endcan
            </article>
        @endforeach
        </div>
    </section>
@endforeach
</div>
<div class="card table-wrap mt-6"><div class="table-scroll"><table class="table">
    <thead><tr><th>{{ __('Manba') }}</th><th class="text-right">{{ __('Arizalar') }}</th><th class="text-right">{{ __('Qabul qilindi') }}</th><th class="text-right">{{ __('Rad etildi') }}</th><th class="text-right">{{ __('Konversiya') }}</th></tr></thead><tbody>
    @forelse($funnel as $src => $f)<tr><td class="font-semibold">{{ $src }}</td><td class="text-right">{{ $f['total'] }}</td><td class="text-right">{{ $f['won'] }}</td><td class="text-right">{{ $f['lost'] }}</td><td class="text-right font-semibold">{{ $f['total'] ? round($f['won'] / $f['total'] * 100) : 0 }}%</td></tr>
    @empty<tr><td colspan="5"><div class="empty">{{ __("Hali ariza yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
@can('leads.manage')
<div x-show="modal" x-cloak class="modal-backdrop" @click.self="modal=false" @keydown.escape.window="modal=false">
    <form method="post" action="{{ route('app.leads.store') }}" class="modal-panel space-y-4">@csrf<input type="hidden" name="type" value="{{ $type }}">
        <h3 class="text-xl font-semibold">Yangi ariza</h3>
        <div><label class="label">{{ $type==='kid' ? 'Ota-ona / bola ismi' : 'Ism' }}</label><input name="name" class="input" required></div>
        <div><label class="label">Telefon</label><input name="phone" class="input" inputmode="tel" placeholder="90 123 45 67" required></div>
        <div><label class="label">Manba</label><select name="source" class="select"><option value="">—</option>@foreach($sources as $s)<option>{{ $s }}</option>@endforeach</select></div>
        <div><label class="label">Keyingi qo'ng'iroq sanasi</label><input type="date" name="next_call_on" class="input"></div>
        <div><label class="label">Mas'ul xodim</label><select name="assigned_to" class="select"><option value="">—</option>@foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        <div><label class="label">Izoh</label><input name="note" class="input"></div>
        <div class="flex gap-3 justify-end"><button type="button" class="btn" @click="modal=false">Bekor qilish</button><button class="btn btn-primary">Qo'shish</button></div>
    </form>
</div>
@endcan
</div>
@endsection
