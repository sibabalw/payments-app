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

// Add connection debugging
if (typeof window !== 'undefined') {
    echo.connector.pusher.connection.bind('connected', () => {
        console.log('[Echo] Connected to Pusher');
    });
    
    echo.connector.pusher.connection.bind('disconnected', () => {
        console.log('[Echo] Disconnected from Pusher');
    });
    
    echo.connector.pusher.connection.bind('error', (err: any) => {
        console.error('[Echo] Pusher connection error:', err);
    });
    
    echo.connector.pusher.connection.bind('state_change', (states: any) => {
        console.log('[Echo] Connection state changed:', states.previous, '->', states.current);
    });
}

window.Echo = echo;

export default echo;
