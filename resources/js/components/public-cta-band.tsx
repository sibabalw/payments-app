import { type ReactNode } from 'react';

import { cn } from '@/lib/utils';

type PublicCtaBandProps = {
    title: string;
    description?: string;
    children?: ReactNode;
    className?: string;
};

export function PublicCtaBand({
    title,
    description,
    children,
    className,
}: PublicCtaBandProps) {
    return (
        <section
            className={cn(
                'relative overflow-hidden border-y border-white/10 py-16 text-white dark:border-white/5',
                'bg-gradient-to-br from-[var(--cta-band-gradient-start-value)] via-neutral-800 to-[var(--cta-band-gradient-end-value)]',
                'dark:via-neutral-800',
                className,
            )}
        >
            {/* Animated gradient mesh overlay – stronger in dark for glow */}
            <div
                className="pointer-events-none absolute inset-0 opacity-40 dark:opacity-50"
                aria-hidden
                style={{
                    background:
                        'radial-gradient(ellipse 80% 50% at 50% 120%, var(--primary-from-value), transparent 50%), radial-gradient(ellipse 60% 40% at 80% 0%, var(--primary-to-value), transparent 40%)',
                }}
            />
            {/* Optional grain */}
            <div className="public-grain" aria-hidden />
            <div className="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="text-center">
                    <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                        {title}
                    </h2>
                    {description && (
                        <p className="mx-auto mt-4 max-w-2xl text-lg text-neutral-300 dark:text-neutral-200">
                            {description}
                        </p>
                    )}
                    {children && (
                        <div className="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-6">
                            {children}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
