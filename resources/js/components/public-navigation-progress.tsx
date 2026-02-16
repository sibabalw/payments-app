import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Thin progress bar below the public header, same style as Inertia's default loader.
 * Listens to router start/finish and shows an indeterminate bar during navigation.
 */
export function PublicNavigationProgress() {
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => setLoading(true));
        const removeFinish = router.on('finish', () => setLoading(false));
        const removeNavigate = router.on('navigate', () => setLoading(false));

        return () => {
            removeStart();
            removeFinish();
            removeNavigate();
        };
    }, []);

    if (!loading) {
        return null;
    }

    return (
        <div
            className="fixed left-0 right-0 z-30 h-1"
            style={{
                top: '4rem',
                background: 'linear-gradient(var(--primary-from-value), var(--primary-to-value))',
            }}
            role="progressbar"
            aria-valuetext="Loading"
        >
            <div
                className="h-full w-1/3 opacity-90"
                style={{
                    boxShadow: '0 0 10px 1px var(--color-primary)',
                    animation: 'public-nav-progress 1.2s ease-in-out infinite',
                    background: 'linear-gradient(var(--primary-from-value), var(--primary-to-value))',
                }}
            />
        </div>
    );
}
