import { motion, type Variants } from 'framer-motion';

const defaultSectionVariants: Variants = {
    hidden: { opacity: 0, y: 24 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.4, ease: [0.25, 0.46, 0.45, 0.94] },
    },
};

const defaultStaggerContainer: Variants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: {
            staggerChildren: 0.08,
            delayChildren: 0.1,
        },
    },
};

const defaultStaggerItem: Variants = {
    hidden: { opacity: 0, y: 16 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.35, ease: [0.25, 0.46, 0.45, 0.94] },
    },
};

type AnimatedSectionProps = React.ComponentProps<typeof motion.section> & {
    amount?: number;
    once?: boolean;
};

export function AnimatedSection({
    children,
    className,
    amount = 0.15,
    once = true,
    ...props
}: AnimatedSectionProps) {
    return (
        <motion.section
            initial="hidden"
            whileInView="visible"
            viewport={{ once, amount }}
            variants={defaultSectionVariants}
            className={className}
            {...props}
        >
            {children}
        </motion.section>
    );
}

type AnimatedDivProps = React.ComponentProps<typeof motion.div> & {
    amount?: number;
    once?: boolean;
};

export function AnimatedDiv({
    children,
    className,
    amount = 0.15,
    once = true,
    ...props
}: AnimatedDivProps) {
    return (
        <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once, amount }}
            variants={defaultSectionVariants}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

type StaggeredListProps = React.ComponentProps<typeof motion.div> & {
    amount?: number;
    once?: boolean;
};

export function StaggeredList({
    children,
    className,
    amount = 0.1,
    once = true,
    ...props
}: StaggeredListProps) {
    return (
        <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once, amount }}
            variants={defaultStaggerContainer}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

export function StaggeredItem({
    children,
    className,
    ...props
}: React.ComponentProps<typeof motion.div>) {
    return (
        <motion.div variants={defaultStaggerItem} className={className} {...props}>
            {children}
        </motion.div>
    );
}

const heroVariants: Variants = {
    hidden: { opacity: 0, y: 20 },
    visible: (i: number) => ({
        opacity: 1,
        y: 0,
        transition: { delay: i * 0.1, duration: 0.5, ease: [0.25, 0.46, 0.45, 0.94] },
    }),
};

export function HeroTitle({ children, className, ...props }: React.ComponentProps<typeof motion.h1>) {
    return (
        <motion.h1
            custom={0}
            initial="hidden"
            animate="visible"
            variants={heroVariants}
            className={className}
            {...props}
        >
            {children}
        </motion.h1>
    );
}

export function HeroSubtext({ children, className, ...props }: React.ComponentProps<typeof motion.p>) {
    return (
        <motion.p
            custom={1}
            initial="hidden"
            animate="visible"
            variants={heroVariants}
            className={className}
            {...props}
        >
            {children}
        </motion.p>
    );
}

export function HeroActions({ children, className, ...props }: React.ComponentProps<typeof motion.div>) {
    return (
        <motion.div
            custom={2}
            initial="hidden"
            animate="visible"
            variants={heroVariants}
            className={className}
            {...props}
        >
            {children}
        </motion.div>
    );
}

const heroStaggerContainer: Variants = {
    hidden: { opacity: 0 },
    visible: {
        opacity: 1,
        transition: {
            staggerChildren: 0.08,
            delayChildren: 0.05,
        },
    },
};

const heroStaggerItem: Variants = {
    hidden: { opacity: 0, y: 16 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.4, ease: [0.25, 0.46, 0.45, 0.94] },
    },
};

type HeroStaggeredLinesProps = {
    lines: React.ReactNode[];
    className?: string;
};

export function HeroStaggeredLines({
    lines,
    className,
}: HeroStaggeredLinesProps) {
    return (
        <motion.h1
            initial="hidden"
            animate="visible"
            variants={heroStaggerContainer}
            className={className}
        >
            {lines.map((line, i) => (
                <motion.span key={i} variants={heroStaggerItem} className="block">
                    {line}
                </motion.span>
            ))}
        </motion.h1>
    );
}
