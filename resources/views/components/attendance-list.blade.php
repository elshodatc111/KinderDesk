@props(['rows', 'payload' => [], 'date', 'locked' => false, 'url', 'emptyText' => null])
<div x-data="attendance(@js($rows), @js($payload), '{{ $date }}', {{ $locked ? 'true' : 'false' }}, '{{ $url }}', @js(['saving' => __('Saqlanmoqda...'), 'saved' => __('Saqlandi'), 'offline' => __("Internet yo'q. Davomad qurilmada saqlandi, internet kelganda yuboriladi.")]))" x-init="init()" class="max-w-3xl">
    @if ($locked)<div class="banner banner-info mb-4"><x-icon name="lock" /><p>{{ __("O'tgan kun davomadi faqat ko'rish uchun. Davomad faqat bugungi kun uchun belgilanadi.") }}</p></div>@endif
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex flex-wrap gap-2">
            <span class="badge badge-leaf">{{ __('Keldi') }}: <b x-text="count('present')"></b></span>
            <span class="badge badge-red">{{ __('Kelmadi') }}: <b x-text="count('absent')"></b></span>
            <span class="badge badge-orange">{{ __('Kasal') }}: <b x-text="count('sick')"></b></span>
            <span class="badge badge-gray">{{ __('Belgilanmagan') }}: <b x-text="count(null)"></b></span>
        </div>
        @unless($locked)<button type="button" class="btn btn-sm ml-auto" @click="markAll('present')">{{ __('Hammasi keldi') }}</button>@endunless
    </div>
    <div class="card divide-y divide-line">
        <template x-for="r in rows" :key="r.id">
            <div class="flex flex-wrap items-center justify-between gap-3 p-3.5">
                <span class="font-medium"><span x-text="r.name"></span> <span x-show="r.alert" x-cloak class="badge badge-red ml-1" :title="r.alert_text">⚠ {{ __('Allergiya / holat') }}</span></span>
                <div class="seg" role="group">
                    <button type="button" :disabled="locked" @click="set(r, 'present')" :class="r.status === 'present' ? 'is-on is-leaf' : ''">{{ __('Keldi') }}</button>
                    <button type="button" :disabled="locked" @click="set(r, 'absent')" :class="r.status === 'absent' ? 'is-on is-berry' : ''">{{ __('Kelmadi') }}</button>
                    <button type="button" :disabled="locked" @click="set(r, 'sick')" :class="r.status === 'sick' ? 'is-on is-sun' : ''">{{ __('Kasal') }}</button>
                </div>
            </div>
        </template>
        <template x-if="!rows.length"><p class="p-8 text-center font-semibold text-ink-mute">{{ $emptyText ?? __("Bu sanada ro'yxatda bola yo'q") }}</p></template>
    </div>
    <p class="help mt-3" x-text="status"></p>
</div>
