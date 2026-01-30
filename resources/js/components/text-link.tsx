import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';

type LinkProps = ComponentProps<typeof Link>;

export default function TextLink({
    className = '',
    children,
    ...props
}: LinkProps) {
    return (
        <Link
            className={cn(
                'text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-200 ease-out hover:decoration-accent-public hover:text-accent-public dark:decoration-neutral-500 dark:hover:decoration-accent-public dark:hover:text-accent-public',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
