import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { initializeUmami } from './lib/umami';

const rawName = import.meta.env.VITE_APP_NAME || 'SwiftPay';
const appName = ['Swift Pay', 'swift pay', 'Swift pay'].includes(rawName) ? 'SwiftPay' : rawName;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );

        initializeUmami();
    },
    progress: {
        color: '#2563eb',
        showSpinner: false,
    },
});

// This will set light / dark mode on load...
initializeTheme();
