@extends('layouts.app')
@section('title', 'AI hisobot')
@section('content')
@php $st = ['ready' => ['Tayyor', 'badge-leaf'], 'unreliable' => ['Ishonchsiz', 'badge-orange'], 'failed' => ['Tayyor emas', 'badge-red']]; @endphp
<div class="w-full space-y-4">
    <div class="banner banner-info"><x-icon name="info" /><p>{{ __("AI hisobot administrator hisobotidan mustaqil: tizimning xom faktlarini tahlil qiladi. AI'ga ism, telefon yoki hujjat yuborilmaydi — faqat sonlar va foizlar. Hisobotni faqat direktor ko'radi.") }}</p></div>
    @unless($enabled)<div class="banner banner-warn"><x-icon name="lock" /><p>{{ __("AI o'chirilgan. Sozlamalar → AI bo'limidan yoqishingiz mumkin.") }}</p></div>@endunless
    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>{{ __('Sana') }}</th><th>{{ __('Davr') }}</th><th>{{ __('Holat') }}</th><th>{{ __('Kamchiliklar') }}</th><th></th></tr></thead><tbody>
        @forelse($reports as $r)
            <tr><td>{{ $r->date->format('d.m.Y') }}</td><td>{{ $r->period === 'weekly' ? __('Haftalik') : __('Kunlik') }}</td>
                <td><span class="badge {{ $st[$r->status][1] }}">{{ __($st[$r->status][0]) }}</span></td>
                <td>{{ count($r->result['issues'] ?? []) }}</td>
                <td>@if($r->result)<a class="btn btn-sm" href="{{ route('app.ai.reports.show', $r) }}">{{ __("Ko'rish") }}</a>@endif</td></tr>
        @empty
            <tr><td colspan="5"><div class="empty">{{ __("Hali AI hisobot yo'q. Har kuni yarim tundan keyin tayyorlanadi.") }}</div></td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection
