import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Real-time: Reverb (WebSocket) ulansa — jonli, ulanmasa — har 20 soniyada "polling".
 * Sahifadagi komponentlar 'kd:activity' hodisasini tinglab, ma'lumotini yangilaydi.
 */
export function initRealtime(cfg) {
    if (!cfg || !cfg.userId) return;

    const A = () => window.Alpine;
    const setState = (s) => { A().store('live').state = s; };
    const emit = (detail) => window.dispatchEvent(new CustomEvent('kd:activity', { detail }));

    let pollTimer = null;
    const poll = async () => {
        if (!cfg.pollUrl || document.hidden) return;
        try {
            const res = await fetch(cfg.pollUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const json = await res.json();
            if (typeof json.unread === 'number') A().store('live').unread = json.unread;
            emit({ kind: 'poll' });
        } catch (e) { /* tarmoq yo'q */ }
    };
    const startPolling = () => {
        if (pollTimer) return;
        setState('poll');
        pollTimer = setInterval(poll, 20000);
    };
    const stopPolling = () => {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    };

    A().store('live').unread = cfg.unread ?? 0;

    if (!cfg.reverb || !cfg.reverb.key) {
        startPolling();
        return;
    }

    let echo;
    try {
        echo = new Echo({
            broadcaster: 'reverb',
            key: cfg.reverb.key,
            wsHost: cfg.reverb.host,
            wsPort: cfg.reverb.port,
            wssPort: cfg.reverb.port,
            forceTLS: cfg.reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': cfg.csrf } },
        });
    } catch (e) {
        startPolling();
        return;
    }

    const conn = echo.connector.pusher.connection;
    conn.bind('connected', () => { stopPolling(); setState('live'); });
    ['unavailable', 'disconnected', 'failed', 'error'].forEach((ev) => conn.bind(ev, startPolling));
    // Ulanish 4 soniyada o'rnatilmasa — polling'ga o'tamiz
    setTimeout(() => { if (A().store('live').state !== 'live') startPolling(); }, 4000);

    const onActivity = (e) => {
        emit(e);
        if (e.data && e.data.message) {
            A().store('toasts').add({ title: e.data.message, body: e.data.detail ?? '', level: e.data.level ?? 'info', url: e.data.url });
        }
    };

    if (cfg.tenantId) echo.private('tenant.' + cfg.tenantId).listen('.activity', onActivity);
    if (cfg.platform) echo.private('platform').listen('.activity', onActivity);
    echo.private('user.' + cfg.userId).listen('.notified', (e) => {
        A().store('live').unread = e.unread ?? A().store('live').unread + 1;
        A().store('toasts').add({ title: e.title, body: e.body, level: e.level, url: e.url });
        emit({ kind: 'notified' });
    });
}
