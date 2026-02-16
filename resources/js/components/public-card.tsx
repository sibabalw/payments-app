import { cva, type VariantProps } from 'class-variance-authority';
import { motion, useReducedMotion } from 'framer-motion';

import { cn } from '@/lib/utils';

const publicCardVariants = cva(
    'rounded-xl transition-[box-shadow,transform,border-color] duration-200',
    {
        variants: {
            variant: {
                default:
                    'border border-border bg-card p-6 shadow-sm hover:-translate-y-0.5 hover:shadow-md',
                glass:
                    'border border-[color:var(--card-glass-border-value)] bg-card/80 p-6 shadow-sm backdrop-blur-xl hover:-translate-y-0.5 hover:shadow-md dark:bg-card/70 dark:border-white/10',
                elevated:
                    'border border-border bg-card p-6 shadow-md hover:-translate-y-1 hover:shadow-lg hover:border-primary/20 dark:hover:border-primary/30',
                'gradient-border':
                    'relative border-0 bg-transparent shadow-sm hover:shadow-lg',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

type PublicCardProps = React.ComponentProps<'div'> &
    VariantProps<typeof publicCardVariants>;

export function PublicCard({
    className,
    variant,
    ...props
}: PublicCardProps) {
    const isGradientBorder = variant === 'gradient-border';
    const reducedMotion = useReducedMotion();
    const cardClassName = cn(publicCardVariants({ variant }), className);

    return (
        <motion.div
            data-slot="public-card"
            className={cardClassName}
            whileHover={
                reducedMotion ? undefined : { scale: 1.02, transition: { duration: 0.2 } }
            }
            initial={false}
        >
            {isGradientBorder ? (
                <>
                    <div
                        className="pointer-events-none absolute inset-0 -z-10 rounded-xl p-[1px] [background:linear-gradient(var(--primary-from-value),var(--primary-to-value))] opacity-80"
                        aria-hidden
                    />
                    <div className="relative rounded-[calc(1rem-1px)] bg-card p-6 dark:bg-card">
                        {props.children}
                    </div>
                </>
            ) : (
                props.children
            )}
        </motion.div>
    );
}
