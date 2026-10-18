@extends('layouts.app')
@section('title', 'Hisobot zanjiri')
@section('content')
@php
    $user = auth()->user();
    $canReview = $user->can('report.review') || $user->can('report.approve');
    $freq = ['daily' => 'Kunlik', 'weekly' => 'Haftalik', 'monthly' => 'Oylik'];
    $cnt = ['text' => 'Matn', 'file' => 'Fayl', 'media' => 'Foto / video', 'auto' => 'Tizim ma\'lumotidan avtomatik'];
    $stl = ['submitted' => ['Topshirildi', 'badge-sky'], 'accepted' => ['Qabul qilindi', 'badge-leaf'], 'returned' => ['Qaytarildi', 'badge-orange'], 'missing' => ['Topshirilmagan', 'badge-red']];
    $first = $me ? 'mine' : ($canReview ? 'queue' : 'types');
@endphp
<div class="w-full space-y-6" x-data="{ tab: '{{ $first }}', sub: null, subOpen: false, typeOpen: false, t: {}, waiveOpen: false, w: { employee_id: '', employee: '' },
    openSubmit(item) { this.sub = item; this.subOpen = true; },
    newType() { this.t = { id: null, name: '', role_key: 'teacher', level: 1, frequency: 'daily', deadline_time: '23:59', content: 'text', is_parent: false, affects_bonus: true, active: true, hint: '' }; this.typeOpen = true; },
    editType(x) { this.t = JSON.parse(JSON.stringify(x)); this.typeOpen = true; } }">

    <div class="banner banner-info"><x-icon name="info" /><p>{{ __("Hisobot topshirilgan va qabul qilingan bo'lsagina hisoblanadi. Oyda bitta ishchi kun o'tkazib yuborilsa, shu oy bonuslari qo'shilmaydi (asosiy maosh saqlanadi). Direktor sabab yozib kechirishi mumkin.") }}</p></div>

    <div class="flex flex-wrap gap-2">
        @if($me)<button class="btn" :class="tab==='mine' && 'btn-primary'" @click="tab='mine'">{{ __('Mening hisobotlarim') }} @if(count($todo))<span class="badge badge-orange ml-1">{{ count($todo) }}</span>@endif</button>@endif
        @if($canReview)
            <button class="btn" :class="tab==='queue' && 'btn-primary'" @click="tab='queue'">{{ __('Qabul qilish') }} @if($queue->count())<span class="badge badge-orange ml-1">{{ $queue->count() }}</span>@endif</button>
            <button class="btn" :class="tab==='status' && 'btn-primary'" @click="tab='status'">{{ __('Oy holati') }}</button>
        @endif
        @can('report.types')<button class="btn" :class="tab==='types' && 'btn-primary'" @click="tab='types'">{{ __('Hisobot turlari') }}</button>@endcan
    </div>

    {{-- Mening hisobotlarim --}}
    @if($me)
    <div x-show="tab==='mine'" class="space-y-3">
        @forelse($todo as $item)
            @php $s = $item['submission']; $st = $s ? $stl[$s->status] : null;
                $payload = ['type_id' => $item['type']->id, 'name' => $item['type']->name, 'date' => $item['key']->toDateString(), 'content' => $item['type']->content, 'body' => $s ? $s->body : null, 'employee_id' => $me->id]; @endphp
            <div class="card card-pad flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ $item['type']->name }} <span class="text-sm text-ink-mute">· {{ $item['key']->format('d.m.Y') }}</span></p>
                    <p class="text-sm text-ink-mute">{{ __($freq[$item['type']->frequency]) }} · {{ __('muddat') }} {{ $item['type']->deadline_time }}@if($item['type']->hint) · {{ $item['type']->hint }}@endif</p>
                    @if($s && $s->status === 'returned')<p class="mt-1 text-sm text-danger">{{ __('Qaytarilgan sabab') }}: {{ $s->note }}</p>@endif
                </div>
                <div class="flex items-center gap-2">
                    @if($st)<span class="badge {{ $st[1] }}">{{ __($st[0]) }}</span>@endif
                    @if($item['overdue'] && (! $s || $s->status === 'returned'))<span class="badge badge-red">{{ __('Kechikdi') }}</span>@endif
                    @if(! $s || in_array($s->status, ['returned', 'submitted'], true))
                    <button class="btn btn-primary btn-sm" @click='openSubmit(@json($payload))'>{{ $s ? __('Qayta topshirish') : __('Topshirish') }}</button>
                    @endif
                </div>
            </div>
        @empty
            <div class="card card-pad"><div class="empty">{{ __("Hozir topshiriladigan hisobot yo'q") }}</div></div>
        @endforelse
    </div>
    @endif

    {{-- Qabul qilish --}}
    @if($canReview)
    <div x-show="tab==='queue'" x-cloak class="space-y-3">
        @forelse($queue as $s)
            <div class="card card-pad space-y-2" x-data="{ ret: false }">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-medium">{{ $s->employee?->full_name }} <span class="text-sm text-ink-mute">· {{ $s->type->name }} · {{ $s->date->format('d.m.Y') }}</span>@if($s->late)<span class="badge badge-orange ml-2">{{ __('Kechikdi') }}</span>@endif</p>
                    <span class="badge badge-sky">{{ $s->type->level === 2 ? __('2-daraja') : __('1-daraja') }}</span>
                </div>
                @if($s->body)<p class="whitespace-pre-line text-sm">{{ $s->body }}</p>@endif
                @if($s->files)<div class="flex flex-wrap gap-2">@foreach($s->files as $i => $f)<a class="badge badge-gray" href="{{ route('app.chain.file', [$s, $i]) }}">{{ $f['name'] }}</a>@endforeach</div>@endif
                @if($s->meta)
                    <p class="text-sm">{{ __('1-daraja natijasi') }}: <b>{{ $s->meta['accepted'] }}</b> {{ __('qabul qilingan') }}, <b class="{{ $s->meta['missing'] ? 'text-danger' : '' }}">{{ $s->meta['missing'] }}</b> {{ __('topshirmagan') }}</p>
                    @if($s->meta['missing'])<ul class="text-sm list-disc pl-5">@foreach(array_filter($s->meta['rows'], fn ($r) => $r['status'] === 'missing') as $r)<li>{{ $r['employee'] }} — {{ $r['type'] }}</li>@endforeach</ul>@endif
                @endif
                <form method="post" action="{{ route('app.chain.review', $s) }}" class="flex flex-wrap items-end gap-2">@csrf
                    <input type="hidden" name="action" :value="ret ? 'return' : 'accept'">
                    <input name="note" x-show="ret" class="input flex-1 min-w-48" placeholder="{{ __('Qaytarish sababi') }}">
                    <button class="btn btn-primary" x-show="!ret">{{ __('Qabul qilish') }}</button>
                    <button type="button" class="btn" x-show="!ret" @click="ret=true">{{ __('Qaytarish') }}</button>
                    <button class="btn btn-primary" x-show="ret">{{ __('Qaytarish') }}</button>
                    <button type="button" class="btn" x-show="ret" @click="ret=false">{{ __('Bekor qilish') }}</button>
                </form>
            </div>
        @empty
            <div class="card card-pad"><div class="empty">{{ __("Ko'rib chiqishni kutayotgan hisobot yo'q") }}</div></div>
        @endforelse
    </div>

    <div x-show="tab==='status'" x-cloak class="space-y-3">
        <form method="get" class="flex items-center gap-2"><input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input" onchange="this.form.submit()"></form>
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Xodim') }}</th><th>{{ __("O'tkazib yuborilgan") }}</th><th>{{ __('Bonus') }}</th><th></th></tr></thead><tbody>
            @forelse($overview as $o)
                <tr><td class="font-medium">{{ $o['employee']->full_name }}</td>
                    <td>@forelse(array_slice($o['missing'], 0, 6) as $m)<span class="badge {{ $stl[$m['state']][1] }} mr-1 mb-1">{{ $m['type']->name }} {{ $m['key']->format('d.m') }}</span>@empty<span class="text-ink-mute">—</span>@endforelse @if(count($o['missing']) > 6)<span class="text-xs text-ink-mute">+{{ count($o['missing']) - 6 }}</span>@endif</td>
                    <td>@if($o['allowed'])<span class="badge badge-leaf">{{ __("Qo'shiladi") }}</span>@else<span class="badge badge-red">{{ __("Qo'shilmaydi") }}</span>@endif @if($o['waivers'])<span class="badge badge-gray">{{ __('Kechirilgan') }}</span>@endif</td>
                    <td>@can('report.waive')@if(! $o['allowed'] || $o['missing'])<button class="btn btn-sm" @click="w={employee_id: {{ $o['employee']->id }}, employee: @js($o['employee']->full_name)}; waiveOpen=true">{{ __('Kechirish') }}</button>@endif @endcan</td></tr>
            @empty
                <tr><td colspan="4"><div class="empty">{{ __("Hisobot topshiradigan xodim yo'q. Avval «Hisobot turlari»ni sozlang.") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>
    @endif

    {{-- Turlar --}}
    @can('report.types')
    <div x-show="tab==='types'" x-cloak class="space-y-3">
        <div class="flex justify-end"><button class="btn btn-primary" @click="newType()"><x-icon name="plus" class="size-4" /> {{ __("Hisobot turi qo'shish") }}</button></div>
        <div class="card table-wrap"><div class="table-scroll"><table class="table">
            <thead><tr><th>{{ __('Nomi') }}</th><th>{{ __('Kim topshiradi') }}</th><th>{{ __('Davriylik') }}</th><th>{{ __('Muddat') }}</th><th>{{ __('Mazmuni') }}</th><th></th></tr></thead><tbody>
            @forelse($types as $ty)
                <tr class="{{ $ty->active ? '' : 'opacity-50' }}"><td class="font-medium">{{ $ty->name }} @if($ty->is_parent)<span class="badge badge-berry">{{ __('Ota-onaga') }}</span>@endif @if($ty->level == 2)<span class="badge badge-sky">{{ __('2-daraja') }}</span>@endif</td>
                    <td>{{ $roles[$ty->role_key] ?? $ty->role_key }}</td><td>{{ __($freq[$ty->frequency]) }}</td><td>{{ $ty->deadline_time }}</td><td>{{ __($cnt[$ty->content]) }}</td>
                    <td class="whitespace-nowrap"><button class="btn btn-sm" @click='editType(@json($ty->toArray()))'>{{ __('Tahrirlash') }}</button>
                        <form method="post" action="{{ route('app.chain.types.destroy', $ty) }}" class="inline" onsubmit="return confirm(@js(__("Hisobot turi o'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></td></tr>
            @empty
                <tr><td colspan="6"><div class="empty">{{ __("Hisobot turi yo'q. Masalan: tarbiyachi — kunlik foto hisobot; oshpaz — kunlik oziq-ovqat hisoboti.") }}</div></td></tr>
            @endforelse
        </tbody></table></div></div>
    </div>
    @endcan

    {{-- Topshirish oynasi --}}
    <div x-show="subOpen" x-cloak class="modal-backdrop" @keydown.escape.window="subOpen=false"><div class="modal-panel" @click.outside="subOpen=false">
        <form method="post" action="{{ route('app.chain.submit') }}" enctype="multipart/form-data" class="space-y-3">@csrf
            <h3 class="text-lg font-semibold" x-text="sub?.name"></h3>
            <input type="hidden" name="type_id" :value="sub?.type_id"><input type="hidden" name="date" :value="sub?.date"><input type="hidden" name="employee_id" :value="sub?.employee_id">
            <p class="text-sm text-ink-mute" x-text="sub?.date"></p>
            <div x-show="sub?.content==='text' || sub?.content==='file' || sub?.content==='media'"><label class="label">{{ __('Matn') }}</label><textarea name="body" rows="4" class="textarea" x-text="sub?.body"></textarea></div>
            <p x-show="sub?.content==='auto'" class="text-sm">{{ __("Matn tizim ma'lumotidan avtomatik tuziladi (bolalar, xodimlar, arizalar soni).") }}</p>
            <div x-show="sub?.content==='file' || sub?.content==='media'"><label class="label">{{ __('Fayl, foto yoki video') }}</label><input type="file" name="files[]" multiple accept="image/*,video/*,.pdf,.docx" class="input"><p class="help">{{ __("Foto va videolar faqat bog'cha ichida, ruxsati bor xodimlarga ko'rinadi.") }}</p></div>
            <div class="flex gap-3"><button class="btn btn-primary">{{ __('Topshirish') }}</button><button type="button" class="btn" @click="subOpen=false">{{ __('Bekor qilish') }}</button></div>
        </form>
    </div></div>

    {{-- Tur oynasi --}}
    @can('report.types')
    <div x-show="typeOpen" x-cloak class="modal-backdrop" @keydown.escape.window="typeOpen=false"><div class="modal-panel" @click.outside="typeOpen=false">
        <form method="post" :action="t.id ? '{{ url('/app/hisobot-zanjiri/turlar') }}/' + t.id : '{{ route('app.chain.types.store') }}'" class="space-y-3">@csrf
            <template x-if="t.id"><input type="hidden" name="_method" value="put"></template>
            <h3 class="text-lg font-semibold">{{ __('Hisobot turi') }}</h3>
            <div><label class="label">{{ __('Nomi') }}</label><input name="name" x-model="t.name" class="input" required></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">{{ __('Kim topshiradi') }}</label><select name="role_key" x-model="t.role_key" class="select" :disabled="t.level==2">@foreach($roles as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select></div>
                <div><label class="label">{{ __('Daraja') }}</label><select name="level" x-model="t.level" class="select"><option value="1">{{ __('1 — xodim → administrator') }}</option><option value="2">{{ __('2 — administrator → direktor') }}</option></select></div>
                <div><label class="label">{{ __('Davriylik') }}</label><select name="frequency" x-model="t.frequency" class="select">@foreach($freq as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                <div><label class="label">{{ __('Muddat (soat:daqiqa)') }}</label><input name="deadline_time" x-model="t.deadline_time" class="input" pattern="[0-2][0-9]:[0-5][0-9]" required></div>
            </div>
            <div><label class="label">{{ __('Mazmuni') }}</label><select name="content" x-model="t.content" class="select">@foreach($cnt as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
            <div><label class="label">{{ __('Izoh (xodimga ko\'rinadi)') }}</label><input name="hint" x-model="t.hint" class="input" maxlength="300"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_parent" value="1" :checked="t.is_parent"> {{ __("Ota-onaga hisobot (KPI sharti)") }}</label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="affects_bonus" value="0"><input type="checkbox" name="affects_bonus" value="1" :checked="t.affects_bonus"> {{ __("O'tkazib yuborilsa bonus qo'shilmasin") }}</label>
            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" :checked="t.active"> {{ __('Faol') }}</label>
            <div class="flex gap-3"><button class="btn btn-primary">{{ __('Saqlash') }}</button><button type="button" class="btn" @click="typeOpen=false">{{ __('Bekor qilish') }}</button></div>
        </form>
    </div></div>
    @endcan

    {{-- Kechirish oynasi --}}
    @can('report.waive')
    <div x-show="waiveOpen" x-cloak class="modal-backdrop" @keydown.escape.window="waiveOpen=false"><div class="modal-panel" @click.outside="waiveOpen=false">
        <form method="post" action="{{ route('app.chain.waive') }}" class="space-y-3">@csrf
            <h3 class="text-lg font-semibold">{{ __('Hisobotni kechirish') }}: <span x-text="w.employee"></span></h3>
            <input type="hidden" name="employee_id" :value="w.employee_id"><input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div><label class="label">{{ __("Sana (bo'sh qoldirsangiz — butun oy)") }}</label><input type="date" name="date" class="input"></div>
            <div><label class="label">{{ __('Sabab') }}</label><input name="reason" class="input" required maxlength="300"></div>
            <p class="help">{{ __("Kechirish jurnalga yoziladi.") }}</p>
            <div class="flex gap-3"><button class="btn btn-primary">{{ __('Kechirish') }}</button><button type="button" class="btn" @click="waiveOpen=false">{{ __('Bekor qilish') }}</button></div>
        </form>
    </div></div>
    @endcan
</div>
@endsection
