@extends('layouts.admin')
@section('title', 'Tizim holati')
@section('content')
<div x-data="systemHealth(@js(route('admin.system.data')), @js($data))">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <span class="grid place-items-center size-10 rounded-full" :class="{'bg-leaf-50 text-leaf-700': data.overall==='ok', 'bg-marigold-50 text-marigold-700': data.overall==='warn', 'bg-danger-50 text-danger': data.overall==='fail'}"><x-icon name="refresh" /></span>
            <div><p class="font-semibold" x-text="data.overall==='ok' ? @js(__('Hammasi joyida')) : (data.overall==='warn' ? @js(__("Diqqat talab qiladigan joylar bor")) : @js(__('Jiddiy muammo bor')))"></p>
                <p class="text-sm text-ink-soft">{{ __('Har 5 soniyada yangilanadi') }} · <span x-text="data.info.time"></span></p></div>
        </div>
        <button class="btn btn-sm" @click="load()"><x-icon name="refresh" /> {{ __('Hozir yangilash') }}</button>
    </div>

    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <template x-for="c in data.checks" :key="c.key">
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-semibold" x-text="c.label"></p>
                    <span class="badge" :class="{'badge-leaf': c.status==='ok', 'badge-orange': c.status==='warn', 'badge-red': c.status==='fail', 'badge-gray': c.status==='na'}"
                          x-text="c.status==='ok' ? @js(__('Yaxshi')) : (c.status==='warn' ? @js(__('Diqqat')) : (c.status==='fail' ? @js(__('Muammo')) : '—'))"></span>
                </div>
                <p class="text-xl font-semibold mt-2" x-text="c.value"></p>
                <div class="progress mt-3" x-show="c.percent !== null"><span :style="'width:' + (c.percent||0) + '%; background:' + (c.status==='fail' ? 'var(--color-danger)' : (c.status==='warn' ? 'var(--color-marigold-500)' : 'var(--color-teal-500)'))"></span></div>
                <p class="text-xs text-ink-soft mt-2 leading-relaxed" x-show="c.hint" x-text="c.hint"></p>
            </div>
        </template>
    </div>

    <div class="card card-pad mt-6">
        <h2 class="section-title mb-3">{{ __('Server ma\'lumotlari') }}</h2>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-3 text-sm">
            <template x-for="[k, v] in Object.entries(data.info)" :key="k"><div><dt class="text-ink-mute" x-text="k"></dt><dd class="font-medium" x-text="String(v)"></dd></div></template>
        </dl>
        <p class="help mt-4">{{ __("Ishchi (worker) va rejalashtiruvchi (scheduler) ishlamasa, SMS yuborilmaydi, oylik to'lovlar avtomatik hisoblanmaydi va obuna muddati tekshirilmaydi.") }}</p>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('systemHealth', (url, initial) => ({
        data: initial,
        init() { setInterval(() => { if (!document.hidden) this.load(); }, 5000); },
        async load() { try { const r = await fetch(url, { headers: { Accept: 'application/json' } }); if (r.ok) this.data = await r.json(); } catch (e) {} },
    }));
});
</script>
@endpush
@endsection
