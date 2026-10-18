@php
    $u = auth()->user();
    $tabs = array_filter([
        ['app.telegram.groups', 'Ota-onalar', $u->can('tg.contacts')],
        ['app.telegram.posts', "Postlar va e'lonlar", $u->can('tg.broadcast')],
        ['app.telegram.employees', 'Xodimlar', $u->can('tg.manage')],
        ['app.telegram.settings', 'Bot sozlamalari', $u->can('tg.manage')],
    ], fn ($t) => $t[2]);
@endphp
<div class="tabs mb-5 overflow-x-auto" role="tablist">
    @foreach($tabs as [$r, $l])<a class="tab {{ request()->routeIs($r.'*') ? 'is-active' : '' }}" href="{{ route($r) }}">{{ __($l) }}</a>@endforeach
</div>
