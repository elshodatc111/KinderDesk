@extends('layouts.app')
@section('title', 'Fanlar va darslar')
@section('content')
@php $money = fn ($n) => number_format($n, 0, '.', ' '); @endphp
<div class="w-full space-y-6" x-data="{ tab: 'journal', modal: false, presets: [['Ingliz tili',250000],['Gimnastika',125000],['Karate',100000],['Mental arifmetika',125000],['Shaxmat',87500]], f: { name: '', kind: 'lesson', pay_mode: 'per_lesson', lesson_rate: 0 } }">
    <div class="flex flex-wrap items-center gap-3 justify-between">
        <div class="flex gap-2">
            <button class="btn" :class="tab==='journal' && 'btn-primary'" @click="tab='journal'">{{ __('Dars jurnali') }}</button>
            <button class="btn" :class="tab==='subjects' && 'btn-primary'" @click="tab='subjects'">{{ __('Fanlar') }}</button>
            <button class="btn" :class="tab==='profit' && 'btn-primary'" @click="tab='profit'">{{ __("To'garak foydasi") }}</button>
        </div>
        <form method="get" class="flex items-center gap-2"><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input" onchange="this.form.submit()"></form>
    </div>

    {{-- Dars jurnali --}}
    <div x-show="tab==='journal'" class="space-y-4">
        <div class="banner banner-info"><x-icon name="info" /><p>{{ __("Dars o'tilganini shu yerda belgilang. Bir dars bir nechta guruhga o'tilsa ham — bitta dars hisoblanadi va pedagogga bir marta to'lanadi.") }}</p></div>
        <form method="post" action="{{ route('app.subjects.lessons.store') }}" class="card card-pad grid sm:grid-cols-2 xl:grid-cols-5 gap-3 items-end">@csrf
            <div><label class="label">{{ __('Sana') }}</label><input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input" required></div>
            <div><label class="label">{{ __('Fan') }}</label><select name="subject_id" class="select" required><option value="">—</option>@foreach($subjects->where('status','active') as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Dars o\'tgan pedagog') }}</label><select name="employee_id" class="select" required><option value="">—</option>@foreach($pedagogs as $p)<option value="{{ $p->id }}">{{ $p->full_name }}</option>@endforeach</select></div>
            <div class="xl:col-span-2"><label class="label">{{ __('Guruhlar') }}</label>
                <div class="flex flex-wrap gap-3">@foreach($groups as $g)<label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="group_ids[]" value="{{ $g->id }}"> {{ $g->name }}</label>@endforeach</div></div>
            <div class="xl:col-span-3"><label class="label">{{ __('Izoh') }}</label><input name="note" class="input" maxlength="200"></div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="hidden" name="held" value="0"><input type="checkbox" name="held" value="1" checked> {{ __("Dars o'tkazildi") }}</label>
            <button class="btn btn-accent">{{ __('Saqlash') }}</button>
        </form>
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Fan') }}</th><th>{{ __('Pedagog') }}</th><th>{{ __('Guruhlar') }}</th><th>{{ __('Holat') }}</th><th></th></tr></thead><tbody>
            @forelse($lessons as $l)
                <tr><td>{{ $l->date->format('d.m.Y') }}</td><td>{{ $l->subject?->name }}</td><td>{{ $l->employee?->full_name }}</td><td>{{ $l->groups->pluck('name')->join(', ') ?: '—' }}</td>
                    <td>@if($l->held)<span class="badge badge-leaf">{{ __("O'tkazildi") }}</span>@else<span class="badge badge-red">{{ __("O'tkazilmadi") }}</span>@endif</td>
                    <td><form method="post" action="{{ route('app.subjects.lessons.destroy', $l) }}" onsubmit="return confirm(@js(__("Dars o'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></td></tr>
            @empty
                <tr><td colspan="6"><div class="empty">{{ __("Bu oyda dars yozilmagan") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
        @if($pays->isNotEmpty())
        <div class="card card-pad"><h3 class="font-semibold mb-2">{{ __("Pedagoglar: bu oy hisoblangan ish haqi") }}</h3>
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-2">@foreach($pays as $id => $amount)<div class="flex justify-between rounded-lg border border-ink/10 px-3 py-2 text-sm"><span>{{ $pedagogs->firstWhere('id', $id)?->full_name }}</span><b>{{ $money($amount) }}</b></div>@endforeach</div></div>
        @endif
    </div>

    {{-- Fanlar --}}
    <div x-show="tab==='subjects'" x-cloak class="space-y-4">
        <div class="flex justify-end"><button class="btn btn-primary" @click="modal=true"><x-icon name="plus" class="size-4" /> {{ __("Fan qo'shish") }}</button></div>
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Nomi') }}</th><th>{{ __('Turi') }}</th><th>{{ __('Pedagog') }}</th><th>{{ __('Haq') }}</th><th></th></tr></thead><tbody>
            @forelse($subjects as $s)
                <tr class="{{ $s->status !== 'active' ? 'opacity-50' : '' }}"><td class="font-medium">{{ $s->name }}</td>
                    <td>{{ $s->kind === 'club' ? __("Pullik to'garak").($s->club ? ': '.$s->club->name : '') : __("Bog'cha ichidagi dars") }}</td>
                    <td>{{ $s->employee?->full_name ?? '—' }}</td>
                    <td>{{ $s->pay_mode === 'fixed' ? $money($s->fixed_salary).' '.__("so'm / oy") : $money($s->lesson_rate).' '.__("so'm / dars") }}</td>
                    <td><form method="post" action="{{ route('app.subjects.destroy', $s) }}" onsubmit="return confirm(@js(__("Fan o'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></td></tr>
            @empty
                <tr><td colspan="5"><div class="empty">{{ __("Hali fan yo'q. «Fan qo'shish» tugmasini bosing.") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>

    {{-- To'garak foydasi --}}
    <div x-show="tab==='profit'" x-cloak class="space-y-4">
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Fan') }}</th><th class="text-right">{{ __('Tushum') }}</th><th class="text-right">{{ __('Pedagog haqi') }}</th><th class="text-right">{{ __('Foyda') }}</th></tr></thead><tbody>
            @forelse($profit as $p)
                <tr><td>{{ $p['subject'] }}</td><td class="text-right">{{ $money($p['income']) }}</td><td class="text-right">{{ $money($p['pay']) }}</td><td class="text-right font-semibold {{ $p['profit'] < 0 ? 'text-danger' : '' }}">{{ $money($p['profit']) }}</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">{{ __("Pullik to'garakka bog'langan fan yo'q") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>

    <div x-show="modal" x-cloak class="modal-backdrop" @keydown.escape.window="modal=false"><div class="modal-panel" @click.outside="modal=false">
        <form method="post" action="{{ route('app.subjects.store') }}" class="space-y-3">@csrf
            <h3 class="text-lg font-semibold">{{ __("Fan qo'shish") }}</h3>
            <div class="flex flex-wrap gap-2"><template x-for="p in presets"><button type="button" class="badge badge-sky" @click="f.name=p[0]; f.lesson_rate=p[1]" x-text="p[0]"></button></template></div>
            <div><label class="label">{{ __('Nomi') }}</label><input name="name" x-model="f.name" class="input" required></div>
            <div><label class="label">{{ __('Turi') }}</label><select name="kind" x-model="f.kind" class="select"><option value="lesson">{{ __("Bog'cha ichidagi dars") }}</option><option value="club">{{ __("Pullik to'garak") }}</option></select></div>
            <div x-show="f.kind==='club'"><label class="label">{{ __("Qaysi to'garak") }}</label><select name="club_id" class="select"><option value="">—</option>@foreach($clubs as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Pedagog') }}</label><select name="employee_id" class="select"><option value="">—</option>@foreach($pedagogs as $p)<option value="{{ $p->id }}">{{ $p->full_name }}</option>@endforeach</select></div>
            <div><label class="label">{{ __("Haq to'lash usuli") }}</label><select name="pay_mode" x-model="f.pay_mode" class="select"><option value="per_lesson">{{ __("Har bir dars uchun") }}</option><option value="fixed">{{ __("Oylik belgilangan") }}</option></select></div>
            <div x-show="f.pay_mode==='per_lesson'"><label class="label">{{ __("Bir dars narxi (so'm)") }}</label><input type="number" min="0" name="lesson_rate" x-model="f.lesson_rate" class="input"></div>
            <div x-show="f.pay_mode==='fixed'"><label class="label">{{ __("Oylik (so'm)") }}</label><input type="number" min="0" name="fixed_salary" value="0" class="input"></div>
            <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><button type="button" class="btn" @click="modal=false">{{ __('Bekor qilish') }}</button></div>
        </form>
    </div></div>
</div>
@endsection
