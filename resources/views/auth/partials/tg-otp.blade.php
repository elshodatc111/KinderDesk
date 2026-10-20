{{-- SMS kod kelmasa: kodni Telegramdan olish. Shu formaning ichiga qo'yiladi; telefon/email/STIR maydonlarini formadan o'qiydi.
     Kod yuborilganda `tg-otp-sent` hodisasini chiqaradi (forma kod maydonini ochadi). --}}
@if (app(\App\Services\Auth\TelegramOtp::class)->available())
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tgOtp', (purpose) => ({
        purpose, busy: false, link: '', token: '', state: 'idle', msg: '', ok: true, timer: null, tries: 0,
        csrf() { return window.KD?.csrf || document.querySelector('meta[name=csrf-token]')?.content || ''; },
        async start() {
            const f = new FormData(this.$el.closest('form'));
            const phone = (f.get('phone') || '').toString().trim();
            if (!phone) { this.ok = false; this.msg = 'Avval telefon raqamingizni kiriting'; return; }
            this.busy = true; this.msg = ''; this.link = ''; this.stop();
            try {
                const r = await fetch('{{ route('otp.telegram') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf()},
                    body: JSON.stringify({phone, purpose: this.purpose, email: f.get('email') || null, tax_id: f.get('tax_id') || null}),
                });
                const j = await r.json().catch(() => ({ok: false, message: "Xatolik. Qayta urinib ko'ring"}));
                this.ok = !!j.ok;
                this.msg = j.message || (j.errors ? Object.values(j.errors)[0][0] : '');
                if (j.ok && j.sent) { this.state = 'sent'; this.$dispatch('tg-otp-sent'); }
                else if (j.ok && j.link) { this.link = j.link; this.token = j.token; this.state = 'pending'; this.poll(); }
            } catch (e) { this.ok = false; this.msg = "Xatolik. Qayta urinib ko'ring"; }
            this.busy = false;
        },
        poll() {
            this.tries = 0;
            this.timer = setInterval(async () => {
                if (++this.tries > 240) { this.stop(); return; } // ~10 daqiqa
                try {
                    const r = await fetch('{{ route('otp.telegram.status') }}?token=' + encodeURIComponent(this.token), {headers: {'Accept': 'application/json'}});
                    const j = await r.json();
                    this.state = j.state; this.msg = j.message;
                    if (j.state === 'sent') { this.ok = true; this.stop(); this.$dispatch('tg-otp-sent'); }
                    else if (j.state === 'failed' || j.state === 'expired') { this.ok = false; this.stop(); this.link = ''; }
                } catch (e) {}
            }, 2500);
        },
        stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
    }));
});
</script>
@endonce
<div x-data="tgOtp('{{ $purpose }}')" class="space-y-2" x-cloak>
    <button type="button" class="btn btn-block" @click="start()" :disabled="busy">
        <span>✈️ SMS kelmadimi? Kodni Telegramdan oling</span>
    </button>
    <a x-show="link" :href="link" target="_blank" rel="noopener" class="btn btn-primary btn-block" x-cloak>Telegramni ochish</a>
    <p class="text-sm font-medium" :class="ok ? 'text-ink-soft' : 'text-danger'" x-show="msg" x-text="msg" x-cloak></p>
</div>
@endif
