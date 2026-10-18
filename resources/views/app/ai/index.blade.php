@extends('layouts.app')
@section('title', 'AI yordamchi')
@section('content')
<div class="w-full space-y-4" x-data="aiChat(@js($history->map(fn ($h) => ['id' => $h->id, 'q' => $h->question, 'a' => $h->answer, 'rating' => $h->rating, 'masked' => $h->masked])->values()), {{ max(0, $limit - $used) }})">
    <div class="banner banner-info"><x-icon name="info" /><p>{{ __("Men KinderDesk'dan foydalanishni tushuntiraman. Bog'cha ma'lumotlarini (bolalar, xodimlar, pul) ko'rmayman va telefon, passport yoki karta raqamini yozmang — ular avtomatik yashiriladi.") }}</p></div>
    @if($blocked)
        <div class="banner banner-warn"><x-icon name="lock" /><p>{{ __($blocked) }}</p></div>
    @endif
    <div class="card card-pad space-y-4">
        <div class="space-y-3 max-h-[28rem] overflow-y-auto" x-ref="box">
            <template x-for="m in items" :key="m.id || m.tmp">
                <div class="space-y-2">
                    <div class="ml-auto max-w-[85%] rounded-2xl bg-teal-50 px-4 py-2 text-sm" x-text="m.q"></div>
                    <div class="max-w-[85%] rounded-2xl border border-ink/10 px-4 py-2 text-sm whitespace-pre-line">
                        <span x-text="m.a"></span>
                        <span x-show="m.typing && !m.a" class="kd-dots" aria-label="{{ __('Javob yozilmoqda') }}"><i></i><i></i><i></i></span>
                        <span x-show="m.typing && m.a" class="kd-caret"></span>
                        <div class="mt-2 flex items-center gap-2 text-xs text-ink-mute" x-show="m.id">
                            <span x-show="m.masked" class="badge badge-orange">{{ __('Shaxsiy raqam yashirildi') }}</span>
                            <button class="btn-quiet" :class="m.rating===1 && 'text-leaf-700'" @click="rate(m,1)">👍 {{ __('Foydali') }}</button>
                            <button class="btn-quiet" :class="m.rating===-1 && 'text-danger'" @click="rate(m,-1)">👎 {{ __('Foydasiz') }}</button>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="!items.length" class="text-sm text-ink-mute">{{ __("Masalan: «Bola qanday qo'shiladi?», «Chegirma qayerda?»") }}</p>
        </div>
        <p x-show="error" x-cloak class="text-sm text-danger" x-text="error"></p>
        <form @submit.prevent="send" class="flex gap-2">
            <input x-model="q" maxlength="600" class="input flex-1" placeholder="{{ __('Savolingizni yozing') }}" autocomplete="off" :disabled="busy || left<=0 || {{ $blocked ? 'true' : 'false' }}">
            <button class="btn btn-primary" :disabled="busy || !q.trim() || left<=0 || {{ $blocked ? 'true' : 'false' }}">{{ __('Yuborish') }}</button>
        </form>
        <p class="text-xs text-ink-mute">{{ __('Bugun qolgan savollar') }}: <b x-text="left"></b></p>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('aiChat', (initial, left) => ({
        items: initial, q: '', busy: false, error: '', left,
        scroll() { this.$nextTick(() => { const b = this.$refs.box; if (b) b.scrollTop = b.scrollHeight; }); },
        async send() {
            const text = this.q.trim(); if (!text || this.busy) return;
            this.busy = true; this.error = '';
            const tmp = 't' + Date.now(); const row = Alpine.reactive({ tmp, q: text, a: '', typing: true }); this.items.push(row); this.q = ''; this.scroll();
            const fail = (msg) => { this.items = this.items.filter(i => i.tmp !== tmp); this.error = msg; this.q = text; };
            try {
                const r = await fetch(@js(route('app.ai.stream')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/x-ndjson', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ question: text }) });
                if (!r.ok || !r.body) { let m = @js(__('Xatolik')); try { m = (await r.json()).message || m; } catch (e) {} fail(m); this.busy = false; return; }
                const reader = r.body.getReader(); const dec = new TextDecoder(); let buf = ''; let finished = false;
                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    buf += dec.decode(value, { stream: true });
                    let nl;
                    while ((nl = buf.indexOf('\n')) >= 0) {
                        const line = buf.slice(0, nl).trim(); buf = buf.slice(nl + 1);
                        if (!line) continue;
                        let ev; try { ev = JSON.parse(line); } catch (e) { continue; }
                        if (ev.t === 'delta') { row.a += ev.text; this.scroll(); }
                        else if (ev.t === 'done') { Object.assign(row, { id: ev.id, a: ev.answer || row.a, masked: ev.masked, typing: false }); this.left = ev.left; finished = true; }
                        else if (ev.t === 'error') { fail(ev.message); finished = true; }
                    }
                }
                if (!finished) { row.typing = false; if (!row.a) fail(@js(__("Ulanish uzildi. Qayta urinib ko'ring."))); else this.error = @js(__("Ulanish uzildi. Javob to'liq bo'lmasligi mumkin; sahifani yangilang.")); }
            } catch (e) { fail(@js(__("Ulanib bo'lmadi"))); }
            this.busy = false; this.scroll();
        },
        async rate(m, v) {
            m.rating = v;
            await fetch(@js(url('/app/ai')) + '/' + m.id + '/baho', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ rating: v }) });
        },
    }));
});
</script>
@endpush
@endsection
