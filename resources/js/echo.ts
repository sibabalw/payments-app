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
    const echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY || 'da70bd0415e161cfbb00',
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap2',
        forceTLS: true,
        encrypted: true,
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
 * Disconnect Pusher and clear the Echo singleton. Call when leaving ticket pages
 * so the WebSocket is closed and the next visit creates a fresh connection.
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
