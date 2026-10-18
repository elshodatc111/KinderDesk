@extends('layouts.app')
@section('title', 'Ma\'lumot import qilish')
@section('content')
<div class="grid xl:grid-cols-[1fr_22rem] gap-6 items-start">
<form method="post" action="{{ route('app.import.upload') }}" enctype="multipart/form-data" class="card card-pad space-y-4">@csrf
    <p class="text-sm text-ink-soft">{{ __("Bolalar, vasiylar, guruhlar va boshlang'ich qoldiqlarni yoki xodimlarni CSV fayldan yuklang. Avval oldindan ko'rish ko'rsatiladi — tasdiqlamaguncha hech narsa yozilmaydi.") }}</p>
    <div class="grid sm:grid-cols-2 gap-3">
        <div><label class="label">{{ __('Nima import qilinadi') }}</label><select name="type" class="select"><option value="kids">{{ __('Bolalar (vasiy, guruh, qoldiq)') }}</option><option value="employees">{{ __('Xodimlar') }}</option></select></div>
        <div><label class="label">{{ __('Fayl manbasi') }}</label><select name="source" class="select"><option value="template">{{ __('Bizning shablon') }}</option><option value="other">{{ __('Boshqa tizimdan eksport (CSV)') }}</option></select></div>
    </div>
    <div><label class="label">{{ __('CSV fayl (UTF-8)') }}</label><input type="file" name="file" accept=".csv,.txt" class="input" required>
        @error('file')<p class="field-error">{{ $message }}</p>@enderror</div>
    <button class="btn btn-primary">{{ __("Oldindan ko'rish") }}</button>
    <ul class="help list-disc pl-5 space-y-1">
        <li>{{ __("Excel'da «Saqlash turi → CSV UTF-8» ni tanlang.") }}</li>
        <li>{{ __("Boshlang'ich qoldiq: musbat — avans, manfiy — qarz. U joriy oydan oldingi qoldiq bo'lsin: joriy oy to'lovini tizim o'zi hisoblaydi.") }}</li>
        <li>{{ __("Telefon bo'yicha takror, qora ro'yxatdagi va noto'g'ri qatorlar o'tkazib yuboriladi.") }}</li>
    </ul>
</form>
<div class="space-y-4">
    <div class="card card-pad space-y-2 text-sm font-medium">
        <h3 class="font-semibold">{{ __('Shablonlar') }}</h3>
        <a class="btn btn-sm" href="{{ route('app.import.template', 'kids') }}">{{ __('Bolalar shabloni') }}</a>
        <a class="btn btn-sm" href="{{ route('app.import.template', 'employees') }}">{{ __('Xodimlar shabloni') }}</a>
    </div>
    <div class="card card-pad text-sm">
        <h3 class="font-semibold mb-2">{{ __('Oxirgi importlar') }}</h3>
        @forelse($runs as $r)<p class="py-1"><a class="hover:underline" href="{{ route('app.import.show', $r) }}">{{ $r->created_at->format('d.m.Y H:i') }}</a> · {{ $r->summary['type'] ?? '' }} · {{ $r->status === 'done' ? ($r->summary['made'] ?? 0).' '.__('ta qo\'shildi') : __("ko'rib chiqilmoqda") }}</p>@empty<p class="text-ink-mute">—</p>@endforelse
    </div>
</div>
</div>
@endsection
