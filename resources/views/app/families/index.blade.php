@extends('layouts.app')
@section('title', 'Oilalar')
@section('content')
<div class="w-full space-y-6">
    <div class="banner banner-info"><x-icon name="info" /><p>Bir oiladan 2 va undan ortiq bola kelsa, aka-uka chegirmasi beriladi. Tizim bir xil telefon bo'yicha taklif beradi, <b>bog'lashni siz tasdiqlaysiz</b>. Chegirma tasdiqlangandan keyingi oyning 1-kunidan boshlanadi. Foizlar Sozlamalarda belgilanadi.</p></div>

    <section>
        <h2 class="text-base font-semibold mb-2">Taklif etilgan oilalar</h2>
        @forelse ($suggestions as $group)
            <form method="post" action="{{ route('app.families.link') }}" class="card p-4 mb-3 flex flex-wrap items-center justify-between gap-3">@csrf
                <div>
                    @foreach ($group as $k)<input type="hidden" name="kids[]" value="{{ $k->id }}"><p class="font-semibold"><a href="{{ route('app.kids.show', $k) }}" class="hover:underline">{{ $k->full_name }}</a> <span class="text-xs font-medium text-ink-soft">{{ $k->currentLink?->group?->name }}</span></p>@endforeach
                    <p class="text-xs text-ink-soft mt-1">Umumiy telefon: {{ $group->first()->parent_phone }}</p>
                </div>
                <button class="btn btn-accent">Aka-uka deb tasdiqlash</button>
            </form>
        @empty
            <div class="empty">Yangi taklif yo'q</div>
        @endforelse
    </section>

    <section>
        <h2 class="text-base font-semibold mb-2">Tasdiqlangan oilalar</h2>
        @forelse ($families as $f)
            <div class="card p-4 mb-3">
                <p class="text-xs font-semibold text-ink-soft mb-2">Chegirma {{ $f->effective_from->format('d.m.Y') }} dan</p>
                @foreach ($f->kids as $k)
                    <div class="flex items-center justify-between py-1">
                        <a href="{{ route('app.kids.show', $k) }}" class="font-semibold hover:underline">{{ $k->full_name }}@if($k->custom_fee !== null) <span class="badge">Shaxsiy narx: chegirmasiz</span>@endif</a>
                        <form method="post" action="{{ route('app.families.unlink', $k) }}" onsubmit="return confirm(@js(__('Bola oiladan chiqariladi')))">@csrf @method('delete')<button class="btn-quiet !text-danger" title="{{ __('Oiladan chiqarish') }}"><x-icon name="x" class="size-4" /></button></form>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="empty">Tasdiqlangan oila yo'q</div>
        @endforelse
    </section>
</div>
@endsection
