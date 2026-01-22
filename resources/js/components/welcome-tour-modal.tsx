import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Sparkles,
    Building2,
    Zap,
    BarChart3,
    ArrowRight,
    ArrowLeft,
    CheckCircle2,
    Rocket,
    Users,
    DollarSign,
    Calendar,
} from 'lucide-react';

interface WelcomeTourModalProps {
    isOpen: boolean;
    onClose: () => void;
    userName: string;
}

interface TourStep {
    title: string;
    description: string;
    icon: React.ElementType;
    content: React.ReactNode;
    highlight?: string;
}

export function WelcomeTourModal({ isOpen, onClose, userName }: WelcomeTourModalProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const firstName = userName.split(' ')[0];

    const steps: TourStep[] = [
        {
            title: `Welcome, ${firstName}!`,
            description: "We're excited to have you here. Let us show you around.",
            icon: Sparkles,
            content: (
                <div className="space-y-6">
                    <motion.div
                        className="relative mx-auto w-32 h-32"
                        initial={{ scale: 0.5, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ delay: 0.2, type: 'spring', stiffness: 200 }}
                    >
                        <div className="absolute inset-0 bg-primary/20 rounded-full blur-2xl" />
                        <div className="relative flex items-center justify-center w-full h-full rounded-full bg-gradient-to-br from-primary to-primary/80 shadow-2xl">
                            <Rocket className="w-16 h-16 text-white" />
                        </div>
                    </motion.div>
                    <motion.div
                        className="text-center space-y-2"
                        initial={{ y: 20, opacity: 0 }}
                        animate={{ y: 0, opacity: 1 }}
                        transition={{ delay: 0.3 }}
                    >
                        <p className="text-lg text-foreground">
                            Swift Pay makes managing your business payments effortless.
                        </p>
                        <p className="text-muted-foreground">
                            Let's take a quick tour to help you get started.
                        </p>
                    </motion.div>
                </div>
            ),
        },
        {
            title: 'Business Switcher',
            description: 'Manage multiple businesses from one account.',
            icon: Building2,
            highlight: 'sidebar',
            content: (
                <div className="space-y-6">
                    <motion.div
                        className="grid grid-cols-2 gap-4"
                        initial="hidden"
                        animate="visible"
                        variants={{
                            hidden: {},
                            visible: { transition: { staggerChildren: 0.1 } },
                        }}
                    >
                        {[
                            { name: 'Acme Corp', icon: Building2 },
                            { name: 'Tech Solutions', icon: Building2 },
                        ].map((business, index) => (
                            <motion.div
                                key={index}
                                className="flex items-center gap-3 p-4 rounded-xl bg-muted/50 border border-border"
                                variants={{
                                    hidden: { opacity: 0, y: 20 },
                                    visible: { opacity: 1, y: 0 },
                                }}
                            >
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <business.icon className="h-5 w-5" />
                                </div>
                                <span className="font-medium text-sm">{business.name}</span>
                            </motion.div>
                        ))}
                    </motion.div>
                    <motion.p
                        className="text-center text-muted-foreground"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.4 }}
                    >
                        Switch between your businesses instantly using the switcher in the sidebar.
                    </motion.p>
                </div>
            ),
        },
        {
            title: 'Quick Actions',
            description: 'Common tasks are just one click away.',
            icon: Zap,
            content: (
                <div className="space-y-6">
                    <motion.div
                        className="grid grid-cols-2 gap-3"
                        initial="hidden"
                        animate="visible"
                        variants={{
                            hidden: {},
                            visible: { transition: { staggerChildren: 0.08 } },
                        }}
                    >
                        {[
                            { label: 'Create Payment', icon: DollarSign, color: 'text-green-500' },
                            { label: 'Create Payroll', icon: Users, color: 'text-blue-500' },
                            { label: 'Add Recipient', icon: Users, color: 'text-purple-500' },
                            { label: 'Add Employee', icon: Users, color: 'text-orange-500' },
                        ].map((action, index) => (
                            <motion.div
                                key={index}
                                className="flex items-center gap-2 p-3 rounded-lg bg-muted/50 border border-border hover:bg-muted transition-colors"
                                variants={{
                                    hidden: { opacity: 0, scale: 0.9 },
                                    visible: { opacity: 1, scale: 1 },
                                }}
                                whileHover={{ scale: 1.02 }}
                            >
                                <action.icon className={`h-4 w-4 ${action.color}`} />
                                <span className="text-sm font-medium">{action.label}</span>
                            </motion.div>
                        ))}
                    </motion.div>
                    <motion.p
                        className="text-center text-muted-foreground text-sm"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.5 }}
                    >
                        Access quick actions from your dashboard to speed up your workflow.
                    </motion.p>
                </div>
            ),
        },
        {
            title: 'Dashboard Metrics',
            description: 'Track your financial performance at a glance.',
            icon: BarChart3,
            content: (
                <div className="space-y-6">
                    <motion.div
                        className="grid grid-cols-3 gap-3"
                        initial="hidden"
                        animate="visible"
                        variants={{
                            hidden: {},
                            visible: { transition: { staggerChildren: 0.1 } },
                        }}
                    >
                        {[
                            { label: 'Escrow Balance', value: 'R 45,231', icon: DollarSign },
                            { label: 'Active Schedules', value: '12', icon: Calendar },
                            { label: 'Succeeded Jobs', value: '156', icon: CheckCircle2 },
                        ].map((metric, index) => (
                            <motion.div
                                key={index}
                                className="p-4 rounded-xl bg-muted/50 border border-border text-center"
                                variants={{
                                    hidden: { opacity: 0, y: 20 },
                                    visible: { opacity: 1, y: 0 },
                                }}
                            >
                                <metric.icon className="h-5 w-5 mx-auto mb-2 text-primary" />
                                <p className="text-lg font-bold">{metric.value}</p>
                                <p className="text-xs text-muted-foreground">{metric.label}</p>
                            </motion.div>
                        ))}
                    </motion.div>
                    <motion.p
                        className="text-center text-muted-foreground text-sm"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.5 }}
                    >
                        Your dashboard shows real-time metrics for all your businesses.
                    </motion.p>
                </div>
            ),
        },
        {
            title: "You're All Set!",
            description: 'Start managing your payments like a pro.',
            icon: CheckCircle2,
            content: (
                <div className="space-y-6 text-center">
                    <motion.div
                        className="relative mx-auto w-24 h-24"
                        initial={{ scale: 0, rotate: -180 }}
                        animate={{ scale: 1, rotate: 0 }}
                        transition={{ type: 'spring', stiffness: 200, damping: 15 }}
                    >
                        <div className="absolute inset-0 bg-green-500/20 rounded-full blur-xl" />
                        <div className="relative flex items-center justify-center w-full h-full rounded-full bg-green-500 shadow-lg">
                            <CheckCircle2 className="w-12 h-12 text-white" />
                        </div>
                    </motion.div>
                    <motion.div
                        className="space-y-4"
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3 }}
                    >
                        <p className="text-lg text-foreground">
                            You're ready to start using Swift Pay!
                        </p>
                        <div className="flex flex-wrap justify-center gap-2">
                            {['Create Business', 'Add Employees', 'Schedule Payments', 'Track Progress'].map(
                                (item, index) => (
                                    <motion.span
                                        key={index}
                                        className="px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-medium"
                                        initial={{ opacity: 0, scale: 0.8 }}
                                        animate={{ opacity: 1, scale: 1 }}
                                        transition={{ delay: 0.5 + index * 0.1 }}
                                    >
                                        {item}
                                    </motion.span>
                                )
                            )}
                        </div>
                    </motion.div>
                </div>
            ),
        },
    ];

    const handleNext = () => {
        if (currentStep < steps.length - 1) {
            setCurrentStep(currentStep + 1);
        }
    };

    const handlePrev = () => {
        if (currentStep > 0) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleComplete = () => {
        setIsSubmitting(true);
        router.post('/dashboard/complete-tour', {}, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                onClose();
            },
            onError: () => {
                setIsSubmitting(false);
                onClose();
            },
        });
    };

    const currentStepData = steps[currentStep];
    const Icon = currentStepData.icon;
    const isLastStep = currentStep === steps.length - 1;

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg overflow-hidden">
                {/* Step indicator */}
                <div className="flex justify-center gap-2 pt-2">
                    {steps.map((_, index) => (
                        <motion.div
                            key={index}
                            className={`h-1.5 rounded-full transition-all duration-300 ${
                                index === currentStep
                                    ? 'w-8 bg-primary'
                                    : index < currentStep
                                      ? 'w-4 bg-primary/50'
                                      : 'w-4 bg-muted'
                            }`}
                            layoutId={`step-${index}`}
                        />
                    ))}
                </div>

                <DialogHeader className="pt-4">
                    <motion.div
                        key={`icon-${currentStep}`}
                        initial={{ scale: 0.5, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ type: 'spring', stiffness: 300, damping: 20 }}
                        className="mx-auto mb-4"
                    >
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10">
                            <Icon className="h-7 w-7 text-primary" />
                        </div>
                    </motion.div>
                    <AnimatePresence mode="wait">
                        <motion.div
                            key={`header-${currentStep}`}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -10 }}
                            transition={{ duration: 0.2 }}
                        >
                            <DialogTitle className="text-center text-2xl">
                                {currentStepData.title}
                            </DialogTitle>
                            <DialogDescription className="text-center mt-2">
                                {currentStepData.description}
                            </DialogDescription>
                        </motion.div>
                    </AnimatePresence>
                </DialogHeader>

                {/* Step content */}
                <div className="py-6 min-h-[200px]">
                    <AnimatePresence mode="wait">
                        <motion.div
                            key={`content-${currentStep}`}
                            initial={{ opacity: 0, x: 20 }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0, x: -20 }}
                            transition={{ duration: 0.3 }}
                        >
                            {currentStepData.content}
                        </motion.div>
                    </AnimatePresence>
                </div>

                <DialogFooter className="flex-row justify-between sm:justify-between gap-2">
                    <Button
                        variant="ghost"
                        onClick={handlePrev}
                        disabled={currentStep === 0}
                        className="gap-2"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Button>
                    <div className="flex gap-2">
                        {!isLastStep && (
                            <Button variant="ghost" onClick={handleComplete} disabled={isSubmitting}>
                                Skip Tour
                            </Button>
                        )}
                        {isLastStep ? (
                            <Button onClick={handleComplete} disabled={isSubmitting} className="gap-2">
                                {isSubmitting ? (
                                    <>
                                        <motion.div
                                            animate={{ rotate: 360 }}
                                            transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
                                        >
                                            <Sparkles className="h-4 w-4" />
                                        </motion.div>
                                        Getting Started...
                                    </>
                                ) : (
                                    <>
                                        Get Started
                                        <Rocket className="h-4 w-4" />
                                    </>
                                )}
                            </Button>
                        ) : (
                            <Button onClick={handleNext} className="gap-2">
                                Next
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
