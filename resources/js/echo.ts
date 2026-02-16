import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: any;
    }
}

window.Pusher = Pusher;

function getEcho(): InstanceType<typeof Echo> {
    if (typeof window !== 'undefined' && window.Echo) {
        return window.Echo;
    }
    const key = import.meta.env.VITE_PUSHER_APP_KEY ?? (import.meta.env.DEV ? 'da70bd0415e161cfbb00' : '');
    const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER ?? (import.meta.env.DEV ? 'ap2' : '');
    const echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        },
        enabledTransports: ['ws', 'wss'],
    });
    if (typeof window !== 'undefined') {
        window.Echo = echo;
        if (import.meta.env.DEV) {
            echo.connector.pusher.connection.bind('connected', () => {
                console.log('[Echo] Connected to Pusher');
            });
            echo.connector.pusher.connection.bind('disconnected', () => {
                console.log('[Echo] Disconnected from Pusher');
            });
            echo.connector.pusher.connection.bind('error', (err: unknown) => {
                console.error('[Echo] Pusher connection error:', err);
            });
            echo.connector.pusher.connection.bind('state_change', (states: { previous: string; current: string }) => {
                console.log('[Echo] Connection state changed:', states.previous, '->', states.current);
            });
        }
    }
    return echo;
}

/**
 * Disconnect Pusher and clear the Echo singleton. For explicit teardown only
 * (e.g. logout or app shutdown). Per Pusher docs, connections close on page close;
 * for in-app navigation, pages should only leave their channel(s), not call this.
 */
export function disconnectEcho(): void {
    if (typeof window === 'undefined' || !window.Echo) return;
    try {
        const conn = window.Echo.connector?.pusher?.connection;
        if (conn && typeof conn.disconnect === 'function') {
            conn.disconnect();
        }
    } catch {
        // ignore
    }
    window.Echo = null;
}

export default getEcho;
