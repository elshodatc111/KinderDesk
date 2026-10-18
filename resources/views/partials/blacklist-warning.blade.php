@php $hits = session('blacklist'); @endphp
@if($hits)
<div class="banner banner-danger" role="alert">
    <x-icon name="ban" />
    <div class="flex-1 space-y-2">
        <p class="font-semibold">{{ __("Qora ro'yxatda mos yozuv topildi") }}</p>
        <ul class="text-sm list-disc pl-5">@foreach($hits as $h)<li>{{ $h['name'] }} — {{ $h['reason'] }}</li>@endforeach</ul>
        @can('blacklist.override')
            <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="blacklist_override" value="1"> {{ __("Baribir davom etaman") }}</label>
            <input name="override_reason" class="input" maxlength="300" placeholder="{{ __('Sabab (kamida 5 belgi) — jurnalga yoziladi') }}" value="{{ old('override_reason') }}">
        @else
            <p class="text-sm">{{ __("Davom etish uchun direktor ruxsati kerak.") }}</p>
        @endcan
    </div>
</div>
@endif
