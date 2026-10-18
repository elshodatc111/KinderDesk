@extends('layouts.app')
@section('title', "Sog'liq va xavfsizlik")
@section('content')
<form class="mb-4 flex flex-wrap items-end gap-2">
    <input name="q" value="{{ request('q') }}" class="input !w-56" placeholder="{{ __('Bola ismi') }}">
    <select name="group" class="select !w-48"><option value="">{{ __('Barcha guruhlar') }}</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected($groupId === $g->id)>{{ $g->name }}</option>@endforeach</select>
    <label class="flex items-center gap-1.5 text-sm font-medium"><input type="checkbox" class="check" name="all" value="1" @checked(! $only)> {{ __('Hammasini ko\'rsatish') }}</label>
    <button class="btn">{{ __('Saralash') }}</button>
</form>
<div class="card table-wrap"><div class="table-scroll"><table class="table">
<thead><tr><th>{{ __('Bola') }}</th><th>{{ __('Guruh') }}</th><th>{{ __('Allergiya') }}</th><th>{{ __('Kasallik') }}</th><th>{{ __('Dori') }}</th></tr></thead><tbody>
@forelse($kids as $k)
<tr><td class="font-semibold"><a class="hover:underline" href="{{ route('app.health.show', $k) }}">{{ $k->full_name }}</a></td><td>{{ $k->currentLink?->group?->name }}</td>
    <td class="text-sm">@if($k->health?->allergies)<span class="badge badge-red">⚠ {{ \Illuminate\Support\Str::limit($k->health->allergies, 60) }}</span>@endif</td>
    <td class="text-sm">{{ \Illuminate\Support\Str::limit((string) $k->health?->conditions, 60) }}</td>
    <td class="text-sm">{{ \Illuminate\Support\Str::limit((string) $k->health?->medications, 60) }}</td></tr>
@empty<tr><td colspan="5"><div class="empty">{{ __("Maxsus ma'lumot kiritilgan bola yo'q") }}</div></td></tr>@endforelse
</tbody></table></div></div>
@endsection
