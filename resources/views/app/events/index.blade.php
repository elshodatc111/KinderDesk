@extends('layouts.app')
@section('title', 'Tadbirlar')
@section('content')
@php $st = ['draft'=>['Qoralama','badge-gray'],'pending'=>['Tasdiqlashni kutmoqda','badge-orange'],'registration'=>["Ro'yxatga olish",'badge-sky'],'locked'=>['Narx qotirilgan','badge-grape'],'held'=>["O'tkazildi",'badge-leaf'],'closed'=>['Yopilgan','badge-gray'],'cancelled'=>['Bekor qilingan','badge-red']]; @endphp
<div class="w-full space-y-4">
    <div class="flex flex-wrap gap-3 justify-between items-center">
        <p class="text-sm text-ink-mute">{{ __("Ekskursiya, sayohat va boshqa bir martalik tadbirlar: smeta → direktor tasdig'i → ro'yxat → narx → tadbir kuni → yakun.") }}</p>
        <a href="{{ route('app.events.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> {{ __('Yangi tadbir') }}</a>
    </div>
    <div class="card table-wrap"><div class="table-scroll"><table class="table">
        <thead><tr><th>{{ __('Tadbir') }}</th><th>{{ __('Sana') }}</th><th>{{ __("Yozilganlar") }}</th><th>{{ __('Narx') }}</th><th>{{ __('Holat') }}</th></tr></thead><tbody>
        @forelse($events as $e)
            <tr><td><a class="font-medium text-teal-700 hover:underline" href="{{ route('app.events.show', $e) }}">{{ $e->title }}</a>@if($e->reapproval)<span class="badge badge-orange ml-2">{{ __('Qayta tasdiqlash') }}</span>@endif</td>
                <td>{{ $e->date->format('d.m.Y') }}</td><td>{{ $e->kids_count }}</td><td>{{ $e->price ? number_format($e->price, 0, '.', ' ') : '—' }}</td>
                <td><span class="badge {{ $st[$e->status][1] }}">{{ __($st[$e->status][0]) }}</span></td></tr>
        @empty
            <tr><td colspan="5"><div class="empty">{{ __("Hali tadbir yo'q") }}</div></td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection
