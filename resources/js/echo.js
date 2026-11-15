import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { SyncBatch, affects } from './gym-sync';
window.Pusher = Pusher;

function createController(role, userId) {
    const batch = new SyncBatch();
    const flights = new Map();
    let timer, clockTimer, noticeTimer;
    let destroyed = false;
    const labels = { members: 'Socios actualizados', groups: 'Grupos, horarios y cupos actualizados', payments: 'Pagos actualizados', medical: 'Situación médica actualizada', access: 'Nuevo evento de acceso', requests: 'Solicitud actualizada', operators: 'Operadores actualizados', permissions: 'Información de sesión actualizada', availability: 'Datos operativos actualizados' };
    const status = text => document.querySelectorAll('[data-gym-realtime-status]').forEach(el => { el.textContent = text; });
    const notice = resources => {
        const text = [...new Set(resources.map(resource => labels[resource]).filter(Boolean))].join(' · ');
        if (!text) return;
        document.querySelectorAll('[data-gym-realtime-notice]').forEach(el => { el.textContent = text; el.classList.remove('hidden'); });
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => document.querySelectorAll('[data-gym-realtime-notice]').forEach(el => el.classList.add('hidden')), 10000);
    };
    const echo = new Echo({
        broadcaster: 'reverb', key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080), wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https', enabledTransports: ['ws', 'wss'],
        authorizer: channel => ({
            authorize(socketId, callback) {
                fetch('/broadcasting/auth', { method: 'POST', credentials: 'same-origin', headers: {
                    'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                }, body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }) })
                    .then(async response => { if (!response.ok) throw new Error('Canal no autorizado'); callback(null, await response.json()); })
                    .catch(error => callback(error, null));
            },
        }),
    });
    window.Echo = echo;
    const queueComponent = (root, resources) => {
        const id = root.getAttribute('wire:id');
        if (!id) return;
        let flight = flights.get(id);
        if (!flight) { flight = { pending: new Set(), running: false, failures: 0, retryTimer: null }; flights.set(id, flight); }
        resources.forEach(resource => flight.pending.add(resource));
        if (flight.retryTimer) { clearTimeout(flight.retryTimer); flight.retryTimer = null; }
        const run = () => {
            if (destroyed || flight.running || !flight.pending.size) return;
            const component = window.Livewire?.find(id);
            if (!component || !root.isConnected) { flights.delete(id); return; }
            const requested = [...flight.pending]; flight.pending.clear(); flight.running = true;
            Promise.resolve().then(() => component.$call('syncGymState', requested))
                .then(() => {
                    flight.failures = 0;
                    if (echo.connector.pusher.connection.state === 'connected') status('En vivo');
                    window.dispatchEvent(new CustomEvent('gym-sync-refreshed', { detail: { id, resources: requested } }));
                })
                .catch(error => {
                    status('Actualización pendiente. Revisá la conexión o tu sesión.');
                    const authorizationError = [401, 403, 419].includes(error?.status);
                    if (!authorizationError && ++flight.failures <= 3 && !destroyed) {
                        requested.forEach(resource => flight.pending.add(resource));
                        flight.retryTimer = setTimeout(() => { flight.retryTimer = null; run(); }, [1000, 3000, 10000][flight.failures - 1]);
                    } else { flight.pending.clear(); }
                })
                .finally(() => { flight.running = false; if (flight.retryTimer) return; if (flight.pending.size) run(); else flights.delete(id); });
        };
        run();
    };
    const drain = () => {
        timer = null;
        const resources = batch.take();
        if (destroyed || !resources.length) return;
        document.querySelectorAll('[data-gym-sync]').forEach(root => {
            const dependencies = (root.dataset.gymSync ?? '').split(/\s+/);
            if (affects(dependencies, resources)) queueComponent(root, resources);
        });
    };
    const schedule = resources => {
        if (destroyed) return;
        batch.invalidate(resources);
        clearTimeout(timer); timer = setTimeout(drain, 140);
    };
    const receive = event => {
        if (!batch.push(event)) return;
        notice(event.resources);
        clearTimeout(timer); timer = setTimeout(drain, 140);
    };
    const subscribed = () => { status('En vivo'); schedule(['*']); };
    const names = role === 'terminal' ? ['gym.terminal'] : role === 'admin' ? ['gym.admin'] : ['gym.reception', `gym.reception.${userId}`];
    for (const name of names) {
        const channel = role === 'terminal' ? echo.channel(name) : echo.private(name);
        channel.listen('.gym.state-changed', receive).subscribed(subscribed)
            .error(() => status('No se pudo autorizar la actualización. Revisá tu sesión.'));
        if (role !== 'terminal') channel.listen('.gym.realtime-update', () => schedule(['*']));
    }
    echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        status(current === 'connected' ? 'En vivo' : 'Reconectando actualizaciones…');
    });
    const onVisible = () => { if (!document.hidden) schedule(['*']); };
    const onFocus = () => schedule(['*']);
    const onOnline = () => { echo.connector.pusher.connect(); schedule(['*']); };
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('focus', onFocus);
    window.addEventListener('online', onOnline);
    const scheduleClock = () => {
        clearTimeout(clockTimer);
        const next = Number(document.querySelector('meta[name="gym-next-state-refresh"]')?.content);
        const delay = Number.isFinite(next) && next > Date.now() ? next - Date.now() + 1000 : 86400000;
        clockTimer = setTimeout(() => { schedule(['clock']); scheduleClock(); }, Math.min(delay, 86400000));
    };
    scheduleClock();
    return { role, userId, echo, schedule, scheduleClock, destroy() {
        destroyed = true; clearTimeout(timer); clearTimeout(clockTimer); clearTimeout(noticeTimer);
        document.removeEventListener('visibilitychange', onVisible); window.removeEventListener('focus', onFocus); window.removeEventListener('online', onOnline);
        echo.disconnect(); for (const flight of flights.values()) clearTimeout(flight.retryTimer); flights.clear();
    } };
}

function boot() {
    const role = document.querySelector('meta[name="gym-realtime-role"]')?.content;
    const userId = document.querySelector('meta[name="gym-realtime-user"]')?.content ?? '';
    const previous = window.__gymRealtime;
    if (!['admin', 'reception', 'terminal'].includes(role)) { previous?.destroy(); window.__gymRealtime = null; return; }
    if (previous?.role === role && previous?.userId === userId) {
        previous.schedule(['*']); previous.scheduleClock(); return;
    }
    previous?.destroy();
    window.__gymRealtime = createController(role, userId);
}
window.__gymRealtime?.destroy();
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:initialized', boot);
boot();
if (import.meta.hot) import.meta.hot.dispose(() => {
    window.__gymRealtime?.destroy(); window.__gymRealtime = null;
    document.removeEventListener('livewire:navigated', boot); document.removeEventListener('livewire:initialized', boot);
});