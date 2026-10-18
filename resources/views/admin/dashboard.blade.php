@extends('layouts.admin')
@section('title', 'Boshqaruv paneli')
@section('content')
<div x-data="liveData('{{ route('admin.dashboard.stats') }}', @js($stats))">
    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <a href="{{ route('admin.tenants.index') }}" class="tile" style="--tile-bg:var(--color-teal-50);--tile-accent:var(--color-teal-500)"><span class="tile-icon"><x-icon name="groups" /></span>
            <p class="tile-label">Jami bog'chalar</p><p class="tile-value" x-text="data.tenants"></p><p class="text-xs font-medium text-ink-soft mt-1">Shu hafta yangi: <span x-text="data.new_week"></span></p></a>
        <a href="{{ route('admin.topups.index') }}" class="tile" style="--tile-bg:var(--color-marigold-50);--tile-accent:var(--color-marigold-500)"><span class="tile-icon"><x-icon name="receipt" /></span>
            <p class="tile-label">Tasdiq kutayotgan cheklar</p><p class="tile-value" x-text="data.pending_topups"></p><p class="text-xs font-medium text-ink-soft mt-1"><span x-text="new Intl.NumberFormat('ru-RU').format(data.pending_amount)"></span> so'm</p></a>
        <div class="tile" style="--tile-bg:var(--color-leaf-50);--tile-accent:var(--color-leaf-500)"><span class="tile-icon"><x-icon name="cash" /></span>
            <p class="tile-label">Shu oy obuna savdosi</p><p class="tile-value"><span x-text="new Intl.NumberFormat('ru-RU').format(data.revenue_month)"></span></p><p class="text-xs font-medium text-ink-soft mt-1">Tushgan to'lovlar: <span x-text="new Intl.NumberFormat('ru-RU').format(data.topups_month)"></span></p></div>
        <a href="{{ route('admin.tenants.index', ['state' => 'delete']) }}" class="tile" style="--tile-bg:var(--color-berry-50);--tile-accent:var(--color-danger)"><span class="tile-icon"><x-icon name="alert" /></span>
            <p class="tile-label">O'chirishga tayyor (90+ kun)</p><p class="tile-value" x-text="data.due_delete.length"></p></a>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 mt-6">
        <div class="card card-pad">
            <h2 class="section-title mb-3">Holat bo'yicha</h2>
            <ul class="space-y-2 font-medium">
                <li class="flex justify-between"><span>Bepul sinovda</span><span class="badge badge-orange" x-text="data.states.trial"></span></li>
                <li class="flex justify-between"><span>Obunasi faol</span><span class="badge badge-leaf" x-text="data.states.active"></span></li>
                <li class="flex justify-between"><span>Umrbod</span><span class="badge badge-sky" x-text="data.states.lifetime"></span></li>
                <li class="flex justify-between"><span>Faqat ko'rish</span><span class="badge badge-red" x-text="data.states.read_only"></span></li>
                <li class="flex justify-between"><span>Bloklangan</span><span class="badge badge-gray" x-text="data.states.suspended"></span></li>
                <li class="flex justify-between border-t border-line pt-2"><span>3 kunda tugaydi</span><span class="badge badge-sky" x-text="data.expiring"></span></li>
            </ul>
        </div>
        <div class="card card-pad">
            <h2 class="section-title mb-3">Nashrlar</h2>
            <ul class="space-y-2 font-medium"><template x-for="e in data.by_edition" :key="e.name"><li class="flex justify-between"><span x-text="e.name"></span><span class="badge" x-text="e.count"></span></li></template></ul>
            <p class="help mt-4">Sinovdan foydalangan kontaktlar: <span x-text="data.trial_registry"></span></p>
        </div>
        <div class="card card-pad">
            <h2 class="section-title mb-3">Yangi bog'chalar</h2>
            <ul class="space-y-2"><template x-for="r in data.recent" :key="r.id"><li class="flex justify-between gap-2"><a :href="'{{ url('admin/tenants') }}/'+r.id" class="font-semibold hover:underline truncate" x-text="r.name"></a><span class="text-xs font-medium text-ink-mute whitespace-nowrap" x-text="r.at"></span></li></template></ul>
        </div>
    </div>

    <template x-if="data.due_delete.length">
        <div class="banner banner-danger mt-6"><x-icon name="alert" /><div>
            <p>Obunasi 90 kundan beri tugagan bog'chalar:</p>
            <p class="text-sm font-semibold"><template x-for="d in data.due_delete" :key="d.id"><a :href="'{{ url('admin/tenants') }}/'+d.id" class="underline mr-3"><span x-text="d.name"></span> (<span x-text="d.days"></span> kun)</a></template></p></div></div>
    </template>
</div>
@endsection
