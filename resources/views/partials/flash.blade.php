@php
    $flashes = [];
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'status' => 'success'] as $key => $level) {
        if (session()->has($key)) {
            $flashes[] = ['title' => __((string) session($key)), 'level' => $level];
        }
    }
    if ($errors->any() && ! $errors->has('phone') && ! $errors->has('code')) {
        $flashes[] = ['title' => __('Ma\'lumotlarni tekshiring'), 'body' => $errors->first(), 'level' => 'danger'];
    }
@endphp
<div x-data x-init="@js($flashes).forEach(f => $store.toasts.add(f))"></div>
<div class="fixed right-4 bottom-4 z-[80] flex flex-col gap-2 pointer-events-none" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div class="toast pointer-events-auto">
            <span class="mt-0.5 flex-none grid place-items-center size-6 rounded-full text-white"
                  :class="{'bg-teal-600': t.level==='success'||t.level==='info', 'bg-marigold-500 !text-ink': t.level==='warning', 'bg-danger': t.level==='danger'}">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path x-show="t.level!=='danger' && t.level!=='warning'" d="m5 12.5 5 5 9-10"/><path x-show="t.level==='danger'" d="M7 7l10 10M17 7 7 17"/><path x-show="t.level==='warning'" d="M12 7v6M12 17v.01"/></svg>
            </span>
            <div class="min-w-0 text-sm">
                <p class="font-semibold leading-snug" x-text="t.title"></p>
                <p class="text-ink-soft font-semibold leading-snug mt-0.5" x-show="t.body" x-text="t.body"></p>
                <a class="text-teal-700 font-semibold underline mt-1 inline-block" x-show="t.url" :href="t.url">Ochish</a>
            </div>
            <button type="button" class="ml-auto text-ink-mute hover:text-ink" @click="$store.toasts.remove(t.id)" aria-label="Yopish">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </template>
</div>
