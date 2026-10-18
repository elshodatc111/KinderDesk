{{-- Bildirishnomalar qo'ng'iroqchasi: jonli yangilanadi --}}
<div class="relative" x-data="bell(@js($bellUrl), @js($readUrl))" @click.outside="open = false" @kd-activity.window="refresh()">
    <button class="btn btn-sm !px-2.5 relative" @click="toggle()" aria-label="Bildirishnomalar" :aria-expanded="open">
        <x-icon name="bell" />
        <span x-show="$store.live.unread > 0" x-cloak x-text="$store.live.unread > 9 ? '9+' : $store.live.unread"
              class="absolute -top-2 -right-2 min-w-5 h-5 px-1 grid place-items-center rounded-full bg-berry-500 text-white text-[.7rem] font-semibold border border-line"></span>
    </button>
    <div x-show="open" x-cloak x-transition.opacity class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] card-sticker z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-line">
            <p class="font-semibold">Bildirishnomalar</p>
            <button class="btn-quiet" @click="readAll()" x-show="$store.live.unread > 0">Hammasini o'qilgan qilish</button>
        </div>
        <div class="max-h-96 overflow-y-auto">
            <template x-if="items.length === 0">
                <p class="px-4 py-8 text-center text-sm font-medium text-ink-mute">Hozircha yangi xabar yo'q</p>
            </template>
            <template x-for="n in items" :key="n.id">
                <a :href="n.url || '#'" class="flex gap-3 px-4 py-3 hover:bg-cloud border-b border-line last:border-0" :class="n.read ? 'opacity-70' : ''">
                    <span class="mt-1 size-2.5 rounded-full flex-none"
                          :class="{'bg-teal-500': n.level==='info'||n.level==='success', 'bg-marigold-500': n.level==='warning', 'bg-danger': n.level==='danger', 'opacity-0': n.read}"></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold leading-snug" x-text="n.title"></span>
                        <span class="block text-xs font-semibold text-ink-soft" x-show="n.body" x-text="n.body"></span>
                        <span class="block text-[.7rem] font-medium text-ink-mute mt-0.5" x-text="n.time"></span>
                    </span>
                </a>
            </template>
        </div>
        @isset($allUrl)<a href="{{ $allUrl }}" class="block text-center py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50 border-t border-line">Barchasini ko'rish</a>@endisset
    </div>
</div>
@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bell', (url, readUrl) => ({
            open: false, items: [], loaded: false,
            async refresh() { if (this.open || !this.loaded) { await this.load(); } },
            async load() {
                try {
                    const r = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (r.ok) { const j = await r.json(); this.items = j.items; Alpine.store('live').unread = j.unread; this.loaded = true; }
                } catch (e) {}
            },
            async toggle() { this.open = !this.open; if (this.open) await this.load(); },
            async readAll() {
                await fetch(readUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': window.KD.csrf, Accept: 'application/json' } });
                Alpine.store('live').unread = 0; this.items = this.items.map(i => ({ ...i, read: true }));
            },
        }));
        window.addEventListener('kd:activity', () => window.dispatchEvent(new CustomEvent('kd-activity')));
    });
</script>
@endonce
