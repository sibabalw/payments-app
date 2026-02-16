import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type ReactNode } from 'react';

import { AnimatedSection } from '@/components/public-motion';

type PublicInnerHeroProps = {
    title: string;
    description?: string;
    backHref?: string;
    backLabel?: string;
    children?: ReactNode;
    className?: string;
};

export function PublicInnerHero({
    title,
    description,
    backHref = '/',
    backLabel = 'Back to home',
    children,
    className,
}: PublicInnerHeroProps) {
    return (
        <AnimatedSection
            className={`bg-gradient-to-b from-primary/5 to-background py-12 dark:from-primary/10 dark:to-background ${className ?? ''}`}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {backHref && (
                    <Link
                        href={backHref}
                        className="mb-8 inline-flex items-center text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        {backLabel}
                    </Link>
                )}
                <div className="text-center">
                    <h1 className="font-display text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
                        {title}
                    </h1>
                    {description && (
                        <p className="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                            {description}
                        </p>
                    )}
                    {children}
                </div>
            </div>
        </AnimatedSection>
    );
}
