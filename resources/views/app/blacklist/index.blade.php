@extends('layouts.app')
@section('title', "Qora ro'yxat")
@section('content')
<div class="space-y-6 w-full">
    <p class="text-sm text-ink-soft">{{ __("Ro'yxat faqat shu bog'chada ishlaydi. Yangi bola, ariza yoki xodim qo'shilganda telefon yoki guvohnoma mos kelsa, ogohlantirish chiqadi.") }}</p>
    @can('blacklist.manage')
    <form method="post" action="{{ route('app.blacklist.store') }}" class="card card-pad grid sm:grid-cols-2 lg:grid-cols-3 gap-4">@csrf
        <div><label class="label">Turi</label><select name="type" class="select"><option value="family">{{ __('Oila') }}</option><option value="staff">{{ __('Xodim') }}</option></select></div>
        <div><label class="label">Ism</label><input name="name" class="input" required value="{{ old('name') }}"></div>
        <div><label class="label">Telefon</label><input name="phone" class="input" inputmode="tel" required placeholder="90 123 45 67" value="{{ old('phone') }}"></div>
        <div><label class="label">Qo'shimcha telefon</label><input name="phone2" class="input" inputmode="tel" value="{{ old('phone2') }}"></div>
        <div><label class="label">Guvohnoma raqami (ixtiyoriy)</label><input name="certificate_no" class="input" value="{{ old('certificate_no') }}"></div>
        <div><label class="label">Sabab</label><input name="reason" class="input" required maxlength="300" value="{{ old('reason') }}"></div>
        <div class="sm:col-span-2 lg:col-span-3"><button class="btn btn-primary">Qo'shish</button></div>
    </form>
    @endcan
    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>Ism</th><th>Turi</th><th>Telefon</th><th>Sabab</th><th>Kim qo'shgan</th><th></th></tr></thead><tbody>
        @forelse($entries as $e)<tr><td class="font-semibold">{{ $e->name }}</td><td><span class="badge {{ $e->type === 'staff' ? 'badge-grape' : 'badge-sky' }}">{{ $e->type === 'staff' ? __('Xodim') : __('Oila') }}</span></td>
            <td>{{ collect($e->phones)->map(fn ($p) => format_phone($p))->join(', ') }}</td><td>{{ $e->reason }}</td><td class="text-sm text-ink-soft">{{ $e->author?->name ?? '—' }} · {{ $e->created_at->format('d.m.Y') }}</td>
            <td class="text-right">@can('blacklist.manage')<form method="post" action="{{ route('app.blacklist.destroy', $e) }}" onsubmit="return confirm('{{ __("Ro'yxatdan olib tashlansinmi?") }}')">@csrf @method('delete')<button class="btn btn-sm btn-danger">{{ __('Olib tashlash') }}</button></form>@endcan</td></tr>
        @empty<tr><td colspan="6"><div class="empty">{{ __("Ro'yxat bo'sh") }}</div></td></tr>@endforelse
    </tbody></table></div></div>
</div>
@endsection
