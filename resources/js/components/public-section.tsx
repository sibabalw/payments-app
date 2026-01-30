import { cn } from '@/lib/utils';

type PublicSectionProps = React.ComponentProps<'section'> & {
    /** Use narrow max-width for text-heavy content */
    narrow?: boolean;
};

export function PublicSection({
    className,
    narrow = false,
    ...props
}: PublicSectionProps) {
    return (
        <section
            className={cn(
                'py-16 sm:py-20 lg:py-24',
                className,
            )}
            {...props}
        />
    );
}

type PublicSectionInnerProps = React.ComponentProps<'div'> & {
    narrow?: boolean;
};

export function PublicSectionInner({
    className,
    narrow = false,
    ...props
}: PublicSectionInnerProps) {
    return (
        <div
            className={cn(
                'mx-auto px-4 sm:px-6 lg:px-8',
                narrow ? 'max-w-3xl' : 'max-w-7xl',
                className,
            )}
            {...props}
        />
    );
}
