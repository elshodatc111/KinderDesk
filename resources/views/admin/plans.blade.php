@extends('layouts.admin')
@section('title', 'Nashrlar va narxlar')
@section('content')
<p class="help mb-4 max-w-2xl">Narxni o'zgartirsangiz, faqat yangi xaridlarga ta'sir qiladi. Mavjud obunalar o'z narxida davom etadi.</p>
<div class="grid xl:grid-cols-3 gap-5">
@foreach ($editions as $ed)
    <form method="post" action="{{ route('admin.plans.update', $ed) }}" class="card-sticker p-5 space-y-4">@csrf @method('put')
        <div class="flex items-center justify-between"><h2 class="text-xl font-semibold">{{ $ed->name }}</h2>
            <label class="flex items-center gap-2 font-medium text-sm"><input type="checkbox" class="check" name="is_active" value="1" @checked($ed->is_active)> Sotuvda</label></div>
        <div><label class="label">Qisqa tavsif</label><input name="tagline" class="input" value="{{ $ed->tagline }}" required></div>
        <div class="space-y-2"><p class="label">Narxlar (so'm)</p>
            @foreach ($ed->plans->sortBy('months') as $p)
                <div class="flex items-center gap-3"><span class="w-16 font-semibold">{{ $p->label() }}</span>
                    <input name="plans[{{ $p->id }}][price]" type="number" min="0" value="{{ $p->price }}" class="input">
                    <label class="flex items-center gap-1 text-xs font-medium"><input type="checkbox" class="check" name="plans[{{ $p->id }}][is_active]" value="1" @checked($p->is_active)>Faol</label></div>
            @endforeach</div>
        <div><p class="label">Imkoniyatlar</p>
            <div class="space-y-1.5">
                @foreach ($features as $key => $f)
                    <label class="flex items-start gap-2.5 font-medium text-sm"><input type="checkbox" class="check mt-0.5" name="features[]" value="{{ $key }}" @checked(in_array($key, $ed->features ?? []) || !empty($f['core'])) @disabled(!empty($f['core']))> <span>{{ $f['label'] }}<span class="block text-xs font-semibold text-ink-mute">{{ $f['desc'] }}</span></span></label>
                @endforeach</div></div>
        <button class="btn btn-primary btn-block">Saqlash</button>
    </form>
@endforeach
</div>
@endsection
