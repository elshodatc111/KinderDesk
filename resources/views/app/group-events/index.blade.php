@extends('layouts.app')
@section('title', 'Guruh tadbirlari')
@section('content')
@php $money = fn ($n) => number_format($n, 0, '.', ' ');
$kinds = ['matinee'=>'Bayram tadbiri (utrennik)','excursion'=>'Ekskursiya','contest'=>'Tanlov','other'=>'Boshqa'];
$st = ['pending'=>['Tasdiqlashni kutmoqda','badge-orange'],'approved'=>['Tasdiqlangan','badge-leaf'],'rejected'=>['Rad etilgan','badge-red']]; @endphp
<div class="w-full space-y-6" x-data="{ groups: @js($groups), gid: '', get staff() { return (this.groups.find(g => g.id == this.gid)?.staff) || []; } }">
    <div class="banner banner-info"><x-icon name="info" /><p>{{ __("Guruh tadbirida qatnashgan xodimlarni belgilang. Direktor tasdiqlagach, bonus faqat shu guruh xodimlariga yoziladi: tarbiyachiga") }} <b>{{ $money($bonusTeacher) }}</b>, {{ __('yordamchiga') }} <b>{{ $money($bonusAssistant) }}</b> {{ __("so'm.") }}</p></div>
    <form method="post" action="{{ route('app.group-events.store') }}" class="card card-pad grid sm:grid-cols-2 xl:grid-cols-4 gap-3 items-end">@csrf
        <div><label class="label">{{ __('Sana') }}</label><input type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="input" required></div>
        <div><label class="label">{{ __('Guruh') }}</label><select name="group_id" class="select" x-model="gid" required><option value="">—</option>@foreach($groups as $g)<option value="{{ $g['id'] }}">{{ $g['name'] }}</option>@endforeach</select></div>
        <div><label class="label">{{ __('Turi') }}</label><select name="kind" class="select">@foreach($kinds as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
        <div><label class="label">{{ __('Nomi') }}</label><input name="title" class="input" required></div>
        <div class="sm:col-span-2 xl:col-span-3"><label class="label">{{ __('Qatnashgan xodimlar') }}</label>
            <div class="flex flex-wrap gap-4"><template x-for="s in staff" :key="s.id"><label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" name="staff[]" :value="s.id"> <span x-text="s.name + ' (' + (s.role === 'assistant' ? `{{ __('Yordamchi') }}` : `{{ __('Tarbiyachi') }}`) + ')'"></span></label></template><span x-show="!gid" class="text-sm text-ink-mute">{{ __('Avval guruhni tanlang') }}</span></div>@error('staff')<p class="field-error">{{ $message }}</p>@enderror</div>
        <button class="btn btn-accent">{{ __('Yuborish') }}</button>
    </form>
    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Guruh') }}</th><th>{{ __('Tadbir') }}</th><th>{{ __('Qatnashganlar') }}</th><th>{{ __('Holat') }}</th><th></th></tr></thead><tbody>
        @forelse($rows as $r)
            <tr><td>{{ $r->date->format('d.m.Y') }}</td><td>{{ $r->group?->name }}</td><td>{{ $r->title }} <span class="text-xs text-ink-mute">({{ __($kinds[$r->kind]) }})</span></td>
                <td>{{ $r->staff->pluck('full_name')->join(', ') }}</td><td><span class="badge {{ $st[$r->status][1] }}">{{ __($st[$r->status][0]) }}</span></td>
                <td class="whitespace-nowrap"><div class="flex gap-1">
                    @if($r->status === 'pending')@can('events.approve')
                        <form method="post" action="{{ route('app.group-events.approve', $r) }}">@csrf<button class="btn btn-sm btn-primary">{{ __('Tasdiqlash') }}</button></form>
                        <form method="post" action="{{ route('app.group-events.reject', $r) }}">@csrf<button class="btn btn-sm">{{ __('Rad etish') }}</button></form>
                    @endcan @endif
                    <form method="post" action="{{ route('app.group-events.destroy', $r) }}" onsubmit="return confirm(@js(__("O'chiriladi")))">@csrf @method('delete')<button class="btn-quiet !text-danger"><x-icon name="x" class="size-4" /></button></form></div></td></tr>
        @empty
            <tr><td colspan="6"><div class="empty">{{ __("Guruh tadbiri yozilmagan") }}</div></td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection
