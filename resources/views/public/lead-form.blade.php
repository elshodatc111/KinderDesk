<!DOCTYPE html>
<html lang="uz">
<head>@include('partials.head')<meta name="robots" content="noindex"></head>
<body class="bg-cloud paper-dots min-h-screen" x-data="{ type: '{{ old('type', 'kid') }}' }">
<div class="min-h-screen grid place-items-center px-4 py-10">
<div class="w-full max-w-lg">
    <div class="text-center mb-6">
        @if($t->logo_path)<img src="{{ asset('storage/'.$t->logo_path) }}" class="size-16 rounded-2xl border border-line mx-auto object-cover" alt="">@endif
        <h1 class="font-display text-2xl font-semibold mt-3">{{ $t->name }}</h1>
        @if($t->address)<p class="font-medium text-ink-soft">{{ $t->address }}</p>@endif
    </div>
    <div class="card-sticker p-6 sm:p-8">
    @if ($done)
        <div class="text-center py-6"><span class="grid place-items-center size-16 rounded-full bg-leaf-100 border border-line mx-auto"><x-icon name="check" class="size-8 text-leaf-700" /></span>
            <h2 class="text-xl font-semibold mt-4">Arizangiz qabul qilindi</h2><p class="font-semibold text-ink-soft mt-1">Tez orada siz bilan bog'lanamiz.</p></div>
    @else
        <h2 class="text-xl font-semibold mb-4">Ariza qoldiring</h2>
        <form method="post" action="{{ route('lead.submit', $t->public_key) }}" class="space-y-4">@csrf
            <div class="tabs"><label class="tab cursor-pointer" :class="type==='kid' && 'is-active'"><input type="radio" name="type" value="kid" x-model="type" class="sr-only">Bola uchun</label>
                <label class="tab cursor-pointer" :class="type==='employee' && 'is-active'"><input type="radio" name="type" value="employee" x-model="type" class="sr-only">Ishga kirish</label></div>
            <div><label class="label">Ismingiz</label><input name="name" value="{{ old('name') }}" class="input" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Telefon raqamingiz</label><input name="phone" value="{{ old('phone') }}" class="input" inputmode="tel" placeholder="90 123 45 67" required>@error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
            <div x-show="type==='kid'" class="grid grid-cols-2 gap-3"><div><label class="label">Bola ismi</label><input name="child_name" class="input"></div><div><label class="label">Bola yoshi</label><input name="child_age" class="input"></div></div>
            <div x-show="type==='employee'" x-cloak><label class="label">Qaysi lavozim</label><input name="position" class="input"></div>
            <div><label class="label">Izoh</label><textarea name="note" rows="2" class="textarea">{{ old('note') }}</textarea></div>
            <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
            <button class="btn btn-accent btn-lg btn-block">Yuborish</button>
        </form>
    @endif
    </div>
    <p class="text-center text-xs font-medium text-ink-mute mt-6">KinderDesk orqali</p>
</div></div>
@include('partials.flash')
</body></html>
