import { motion, AnimatePresence } from 'framer-motion';
import { CheckCircle2, Building2 } from 'lucide-react';

interface Business {
    id: number;
    name: string;
    logo?: string | null;
    status: string;
}

interface BusinessSwitchOverlayProps {
    isVisible: boolean;
    fromBusiness: Business | null;
    toBusiness: Business | null;
    onComplete?: () => void;
}

// Helper function to get business initials
const getBusinessInitials = (name: string): string => {
    if (!name) return '?';
    const words = name.trim().split(/\s+/);
    if (words.length === 1) {
        return name.substring(0, 2).toUpperCase();
    }
    return (words[0][0] + words[1][0]).toUpperCase();
};

// Confetti particle component
const ConfettiParticle = ({ delay, x, color }: { delay: number; x: number; color: string }) => (
    <motion.div
        className="absolute w-2 h-2 rounded-full"
        style={{ backgroundColor: color, left: `${50 + x}%` }}
        initial={{ opacity: 0, y: 0, scale: 0 }}
        animate={{
            opacity: [0, 1, 1, 0],
            y: [0, -100, -150, -200],
            scale: [0, 1, 1, 0.5],
            x: [0, x * 2, x * 3, x * 4],
            rotate: [0, 180, 360, 540],
        }}
        transition={{
            duration: 1.5,
            delay: delay,
            ease: 'easeOut',
        }}
    />
);

// Sparkle component
const Sparkle = ({ delay, x, y }: { delay: number; x: number; y: number }) => (
    <motion.div
        className="absolute"
        style={{ left: `${50 + x}%`, top: `${50 + y}%` }}
        initial={{ opacity: 0, scale: 0 }}
        animate={{
            opacity: [0, 1, 0],
            scale: [0, 1.5, 0],
        }}
        transition={{
            duration: 0.6,
            delay: delay,
            ease: 'easeOut',
        }}
    >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path
                d="M10 0L12.5 7.5L20 10L12.5 12.5L10 20L7.5 12.5L0 10L7.5 7.5L10 0Z"
                fill="currentColor"
                className="text-primary"
            />
        </svg>
    </motion.div>
);

export function BusinessSwitchOverlay({
    isVisible,
    fromBusiness,
    toBusiness,
    onComplete,
}: BusinessSwitchOverlayProps) {
    const confettiColors = ['#22c55e', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'];

    return (
        <AnimatePresence onExitComplete={onComplete}>
            {isVisible && (
                <motion.div
                    className="fixed inset-0 z-[100] flex items-center justify-center"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.3 }}
                >
                    {/* Backdrop with blur */}
                    <motion.div
                        className="absolute inset-0 bg-background/80 backdrop-blur-md"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                    />

                    {/* Content container */}
                    <div className="relative z-10 flex flex-col items-center gap-8">
                        {/* Business logos transition */}
                        <div className="relative h-32 w-32">
                            {/* From business (fading out and scaling down) */}
                            {fromBusiness && (
                                <motion.div
                                    className="absolute inset-0 flex items-center justify-center"
                                    initial={{ opacity: 1, scale: 1 }}
                                    animate={{ opacity: 0, scale: 0.5, y: -20 }}
                                    transition={{ duration: 0.4, ease: 'easeInOut' }}
                                >
                                    {fromBusiness.logo ? (
                                        <div className="flex h-24 w-24 items-center justify-center rounded-2xl overflow-hidden border-2 border-muted bg-background shadow-lg">
                                            <img
                                                src={fromBusiness.logo}
                                                alt={fromBusiness.name}
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                    ) : (
                                        <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-muted text-muted-foreground shadow-lg">
                                            <span className="text-2xl font-bold">
                                                {getBusinessInitials(fromBusiness.name)}
                                            </span>
                                        </div>
                                    )}
                                </motion.div>
                            )}

                            {/* To business (scaling up and fading in) */}
                            {toBusiness && (
                                <motion.div
                                    className="absolute inset-0 flex items-center justify-center"
                                    initial={{ opacity: 0, scale: 0.5, y: 20 }}
                                    animate={{ opacity: 1, scale: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: 0.3, ease: 'easeOut' }}
                                >
                                    {toBusiness.logo ? (
                                        <motion.div
                                            className="flex h-28 w-28 items-center justify-center rounded-2xl overflow-hidden border-4 border-primary bg-background shadow-2xl"
                                            animate={{
                                                boxShadow: [
                                                    '0 0 0 0 rgba(var(--primary), 0)',
                                                    '0 0 0 20px rgba(var(--primary), 0.1)',
                                                    '0 0 0 0 rgba(var(--primary), 0)',
                                                ],
                                            }}
                                            transition={{ duration: 1.5, repeat: 1 }}
                                        >
                                            <img
                                                src={toBusiness.logo}
                                                alt={toBusiness.name}
                                                className="h-full w-full object-cover"
                                            />
                                        </motion.div>
                                    ) : (
                                        <motion.div
                                            className="flex h-28 w-28 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-2xl"
                                            animate={{
                                                boxShadow: [
                                                    '0 0 0 0 hsl(var(--primary) / 0)',
                                                    '0 0 0 20px hsl(var(--primary) / 0.2)',
                                                    '0 0 0 0 hsl(var(--primary) / 0)',
                                                ],
                                            }}
                                            transition={{ duration: 1.5, repeat: 1 }}
                                        >
                                            <span className="text-3xl font-bold">
                                                {getBusinessInitials(toBusiness.name)}
                                            </span>
                                        </motion.div>
                                    )}
                                </motion.div>
                            )}

                            {/* Sparkles around the logo */}
                            <Sparkle delay={0.5} x={-25} y={-25} />
                            <Sparkle delay={0.6} x={25} y={-20} />
                            <Sparkle delay={0.7} x={-20} y={20} />
                            <Sparkle delay={0.8} x={30} y={25} />
                            <Sparkle delay={0.55} x={0} y={-35} />
                            <Sparkle delay={0.75} x={-35} y={0} />
                        </div>

                        {/* Business name with staggered character animation */}
                        <div className="text-center">
                            <motion.p
                                className="text-sm text-muted-foreground mb-2"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.4 }}
                            >
                                Switching to
                            </motion.p>
                            {toBusiness && (
                                <motion.h2
                                    className="text-3xl font-bold text-foreground"
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    transition={{ delay: 0.5 }}
                                >
                                    {toBusiness.name.split('').map((char, index) => (
                                        <motion.span
                                            key={index}
                                            initial={{ opacity: 0, y: 20 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            transition={{
                                                delay: 0.5 + index * 0.03,
                                                duration: 0.3,
                                                ease: 'easeOut',
                                            }}
                                        >
                                            {char}
                                        </motion.span>
                                    ))}
                                </motion.h2>
                            )}
                        </div>

                        {/* Success indicator */}
                        <motion.div
                            className="flex items-center gap-2 rounded-full bg-green-100 dark:bg-green-900/30 px-4 py-2 text-green-700 dark:text-green-400"
                            initial={{ opacity: 0, scale: 0.8, y: 20 }}
                            animate={{ opacity: 1, scale: 1, y: 0 }}
                            transition={{ delay: 1, duration: 0.4, ease: 'easeOut' }}
                        >
                            <motion.div
                                initial={{ scale: 0, rotate: -180 }}
                                animate={{ scale: 1, rotate: 0 }}
                                transition={{
                                    delay: 1.1,
                                    type: 'spring',
                                    stiffness: 200,
                                    damping: 10,
                                }}
                            >
                                <CheckCircle2 className="h-5 w-5" />
                            </motion.div>
                            <span className="text-sm font-medium">Business switched successfully</span>
                        </motion.div>

                        {/* Confetti burst */}
                        <div className="absolute inset-0 pointer-events-none overflow-hidden">
                            {confettiColors.map((color, colorIndex) =>
                                Array.from({ length: 6 }).map((_, i) => (
                                    <ConfettiParticle
                                        key={`${colorIndex}-${i}`}
                                        delay={1 + (colorIndex * 0.1) + (i * 0.05)}
                                        x={(i - 2.5) * 15 + (Math.random() - 0.5) * 20}
                                        color={color}
                                    />
                                ))
                            )}
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
