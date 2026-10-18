import Alpine from 'alpinejs';
import { initRealtime } from './realtime';

window.Alpine = Alpine;

/* ---------- Toast xabarlari ---------- */
Alpine.store('toasts', {
    items: [],
    add(t) {
        const id = Date.now() + Math.random();
        this.items.push({ id, level: 'info', ...t });
        setTimeout(() => this.remove(id), t.duration ?? 6500);
    },
    remove(id) {
        this.items = this.items.filter((i) => i.id !== id);
    },
});

/* ---------- Bildirishnomalar soni va jonli aloqa holati ---------- */
Alpine.store('live', {
    state: 'off', // off | live | poll
    unread: 0,
});

/* ---------- Pul kiritish maydoni: 1 250 000 ---------- */
Alpine.data('moneyInput', (initial = '') => ({
    raw: String(initial ?? '').replace(/\D/g, ''),
    get shown() {
        return this.raw.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    },
    onInput(e) {
        this.raw = e.target.value.replace(/\D/g, '');
        e.target.value = this.shown;
    },
}));

/* ---------- Jonli statistika: sahifa eventlarga javoban qayta yuklanadi ---------- */
Alpine.data('liveData', (url, initial = {}) => ({
    data: initial,
    loading: false,
    async load() {
        if (this.loading) return;
        this.loading = true;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) this.data = await res.json();
        } catch (e) {
            /* tarmoq xatosi: keyingi urinishda yangilanadi */
        } finally {
            this.loading = false;
        }
    },
    init() {
        window.addEventListener('kd:activity', () => this.load());
    },
}));

/* ---------- Davomad ro'yxati (guruh va to'garak uchun umumiy) ---------- */
Alpine.data('attendance', (rows, payload, date, locked, url, msgs = {}) => ({
    rows, locked, status: '', timer: null, dirty: {},
    init() { window.kdQueue && window.kdQueue.flush(); },
    count(s) { return this.rows.filter((r) => (r.status ?? null) === s).length; },
    set(r, s) { if (this.locked) return; r.status = s; this.dirty[r.id] = s; this.schedule(); },
    markAll(s) { this.rows.forEach((r) => { if (!r.status) { r.status = s; this.dirty[r.id] = s; } }); this.schedule(); },
    schedule() { clearTimeout(this.timer); this.status = msgs.saving || '...'; this.timer = setTimeout(() => this.save(), 500); },
    async save() {
        const marks = this.dirty; this.dirty = {};
        if (!Object.keys(marks).length) return;
        const body = { ...payload, date, marks };
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': window.KD.csrf }, body: JSON.stringify(body) });
            if (!res.ok) { const j = await res.json().catch(() => ({})); throw new Error(res.status >= 500 ? 'net' : (j.message || 'Error')); }
            this.status = msgs.saved || 'OK';
        } catch (e) {
            if (e.message === 'net' || e instanceof TypeError) {
                window.kdQueue.push({ url, body });
                this.status = msgs.offline || 'Offline';
            } else {
                this.status = e.message;
                Alpine.store('toasts').add({ title: e.message, level: 'danger' });
            }
        }
    },
}));

/* ---------- Chart.js faqat kerak bo'lganda yuklanadi ---------- */
window.kdChart = async (canvas, config) => {
    const { default: Chart } = await import('chart.js/auto');
    Chart.defaults.font.family = "'Nunito Variable', system-ui, sans-serif";
    Chart.defaults.font.weight = 700;
    Chart.defaults.color = '#3f615c';
    return new Chart(canvas, config);
};

/* ---------- QR kod (Telegram havolalari) ---------- */
window.kdQr = async (canvas, text, size = 180) => {
    const QR = (await import('qrcode')).default;
    await QR.toCanvas(canvas, text, { width: size, margin: 1 });
};

/* ---------- Davomadni oflayn saqlash (internet uzilganda) ---------- */
window.kdQueue = {
    key: 'kd.queue.v1',
    all() {
        try { return JSON.parse(localStorage.getItem(this.key) || '[]'); } catch (e) { return []; }
    },
    push(item) {
        const q = this.all();
        q.push({ ...item, at: Date.now() });
        localStorage.setItem(this.key, JSON.stringify(q));
    },
    set(q) { localStorage.setItem(this.key, JSON.stringify(q)); },
    async flush() {
        const q = this.all();
        if (!q.length || !navigator.onLine) return 0;
        const left = [];
        let sent = 0;
        for (const item of q) {
            try {
                const res = await fetch(item.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': window.KD?.csrf ?? '' },
                    body: JSON.stringify(item.body),
                });
                if (res.ok) sent++; else if (res.status >= 500 || res.status === 419) left.push(item);
            } catch (e) {
                left.push(item);
            }
        }
        this.set(left);
        return sent;
    },
};
window.addEventListener('online', () => window.kdQueue.flush().then((n) => {
    if (n) window.Alpine.store('toasts').add({ title: 'Oflayn saqlangan ma\'lumotlar yuborildi', body: n + ' ta yozuv', level: 'success' });
}));

Alpine.start();
initRealtime(window.KD);
window.kdQueue.flush();
