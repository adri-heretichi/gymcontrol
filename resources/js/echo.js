import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const role = document.querySelector('meta[name="gym-realtime-role"]')?.content;
const userId = document.querySelector('meta[name="gym-realtime-user"]')?.content;

if (role === 'admin' || role === 'reception') {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
        },
    });

    const refreshFromDatabase = () => window.Livewire?.dispatch('gym-realtime-updated');
    window.Echo.private(role === 'admin' ? 'gym.admin' : 'gym.reception')
        .listen('.gym.realtime-update', refreshFromDatabase);

    if (role === 'reception' && userId) {
        window.Echo.private(`gym.reception.${userId}`)
            .listen('.gym.realtime-update', refreshFromDatabase);
    }

    window.Echo.connector.pusher.connection.bind('connected', refreshFromDatabase);
    window.addEventListener('livewire:navigated', refreshFromDatabase);
    document.addEventListener('livewire:init', refreshFromDatabase, { once: true });
}
