import { Button } from '@/components/ui/button';
import {
    AnimatedSection,
    HeroActions,
    HeroStaggeredLines,
    HeroSubtext,
    StaggeredItem,
    StaggeredList,
} from '@/components/public-motion';
import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicSectionInner } from '@/components/public-section';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    DollarSign,
    FileText,
    Shield,
    Users,
    Zap,
    Clock,
    Building2,
    Lock,
    Calculator,
} from 'lucide-react';
import { login, register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

const featureCards = [
    {
        icon: Zap,
        title: 'Payment Automation',
        description:
            'Schedule recurring payments with flexible cron-based timing. Daily, weekly, monthly, or custom intervals—set it once and let SwiftPay handle the rest.',
        large: true,
    },
    {
        icon: DollarSign,
        title: 'Automated Payroll',
        description:
            'Process employee salaries with full South African tax compliance. Automatic PAYE, UIF, and SDL deductions—no spreadsheets, no errors.',
        large: false,
    },
    {
        icon: FileText,
        title: 'Tax Compliance',
        description:
            'Generate UI-19 declarations, EMP201 submissions, and IRP5 certificates in minutes. SARS-ready exports and complete compliance tracking.',
        large: false,
    },
    {
        icon: Clock,
        title: 'Time & Attendance',
        description:
            'Real-time employee time tracking with geolocation verification. Automatic overtime calculations and comprehensive leave management.',
        large: false,
    },
    {
        icon: Shield,
        title: 'Bank-Controlled Escrow',
        description:
            'Transparent, secure fund management. Your money stays in bank-controlled escrow accounts—we never touch your funds.',
        large: false,
    },
    {
        icon: Users,
        title: 'Employee Self-Service',
        description:
            'OTP-based employee portal for time tracking, payslip access, and leave requests. Empower your team with self-service capabilities.',
        large: false,
    },
];

function FeatureCard({
    icon: Icon,
    title,
    description,
    large,
}: {
    icon: typeof Zap;
    title: string;
    description: string;
    large: boolean;
}) {
    return (
        <PublicCard
            variant="elevated"
            className={large ? 'lg:col-span-2' : ''}
        >
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                <Icon className="h-6 w-6 text-primary" />
            </div>
            <h3 className="font-display text-xl font-semibold tracking-tight">{title}</h3>
            <p className="mt-2 text-muted-foreground">{description}</p>
        </PublicCard>
    );
}

export default function Home() {
    return (
        <>
            <Head title="SwiftPay - Automate Payments & Payroll with Confidence" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                {/* Hero Section */}
                <section className="relative overflow-hidden py-20 sm:py-24 lg:py-32">
                    <div
                        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,var(--hero-gradient-start-value),transparent)] opacity-60 dark:opacity-50"
                        aria-hidden
                    />
                    <div
                        className="pointer-events-none absolute inset-0 bg-gradient-to-b from-primary/10 from-20% via-primary/5 to-background"
                        aria-hidden
                    />
                    <div
                        className="pointer-events-none absolute inset-0 opacity-[0.02] dark:opacity-[0.04]"
                        style={{
                            backgroundImage: `linear-gradient(to right, currentColor 1px, transparent 1px),
                                linear-gradient(to bottom, currentColor 1px, transparent 1px)`,
                            backgroundSize: '48px 48px',
                        }}
                    />
                    <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <HeroStaggeredLines
                                lines={[
                                    <span key="line1">
                                        <span className="bg-gradient-to-r from-primary to-primary/80 bg-clip-text font-display text-5xl font-bold tracking-tight text-transparent sm:text-6xl lg:text-7xl">
                                            SwiftPay:
                                        </span>
                                    </span>,
                                    <span key="line2">
                                        Automate Payments & Payroll with Confidence
                                    </span>,
                                ]}
                                className="font-display text-5xl font-bold tracking-tight text-foreground sm:text-6xl lg:text-7xl"
                            />
                            <HeroSubtext className="mx-auto mt-6 max-w-3xl text-xl leading-8 text-muted-foreground">
                                The all-in-one platform for South African businesses. Streamline your payment
                                scheduling, automate payroll with full tax compliance, and eliminate manual
                                calculations forever.
                            </HeroSubtext>
                            <HeroActions className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-x-6">
                                <Link href={register()}>
                                    <Button
                                        size="lg"
                                        className="group w-full shadow-lg ring-2 ring-primary/20 sm:w-auto"
                                    >
                                        Start Free Trial
                                        <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                                    </Button>
                                </Link>
                                <Link href="/features">
                                    <Button variant="outline" size="lg" className="w-full sm:w-auto">
                                        Explore Features
                                    </Button>
                                </Link>
                            </HeroActions>
                            <div className="mt-6 flex flex-wrap items-center justify-center gap-2">
                                <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                    No credit card required
                                </span>
                                <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                    Set up in minutes
                                </span>
                                <span className="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">
                                    Bank-controlled escrow
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Key Differentiators */}
                <AnimatedSection className="relative overflow-hidden border-y border-white/10 bg-gradient-to-br from-[var(--cta-band-gradient-start-value)] via-neutral-800 to-[var(--cta-band-gradient-end-value)] py-16 text-white dark:border-white/5 dark:via-neutral-800">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">
                                Why South African Businesses Choose SwiftPay
                            </h2>
                        </div>
                        <StaggeredList className="mt-12 grid grid-cols-1 gap-8 md:grid-cols-3">
                            <StaggeredItem className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10 dark:bg-white/15 dark:ring-1 dark:ring-white/10">
                                    <Calculator className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">SA Tax Compliance Built-In</h3>
                                <p className="mt-4 text-neutral-300 dark:text-neutral-200">
                                    Automatic PAYE, UIF, and SDL calculations. Generate UI-19, EMP201, and IRP5
                                    certificates with zero manual work.
                                </p>
                            </StaggeredItem>
                            <StaggeredItem className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10 dark:bg-white/15 dark:ring-1 dark:ring-white/10">
                                    <Lock className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Bank-Controlled Escrow</h3>
                                <p className="mt-4 text-neutral-300 dark:text-neutral-200">
                                    Your funds are always secure. We never hold your money—everything flows through
                                    bank-controlled escrow accounts.
                                </p>
                            </StaggeredItem>
                            <StaggeredItem className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10 dark:bg-white/15 dark:ring-1 dark:ring-white/10">
                                    <Zap className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Zero Manual Calculations</h3>
                                <p className="mt-4 text-neutral-300 dark:text-neutral-200">
                                    Set it and forget it. Our platform handles all calculations, scheduling, and
                                    compliance automatically.
                                </p>
                            </StaggeredItem>
                        </StaggeredList>
                    </div>
                </AnimatedSection>

                {/* What is SwiftPay? */}
                <AnimatedSection className="py-20">
                    <PublicSectionInner narrow>
                        <div className="mx-auto max-w-3xl text-center">
                            <h2 className="font-display text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                What is SwiftPay?
                            </h2>
                            <p className="mt-6 text-lg leading-8 text-muted-foreground">
                                SwiftPay is a South African platform for automated payments and payroll. Your funds
                                stay secure in bank-controlled escrow, while we handle full tax compliance (PAYE,
                                UIF, SDL), time tracking, and employee self-service—so you can focus on running your
                                business.
                            </p>
                            <ul className="mt-8 space-y-3 text-left sm:mx-auto sm:max-w-md sm:text-center">
                                <li className="flex items-start gap-3 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span className="text-foreground">
                                        Built for South African businesses, from startups to enterprises
                                    </span>
                                </li>
                                <li className="flex items-start gap-3 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span className="text-foreground">
                                        One platform for payment scheduling, payroll, and SARS-ready compliance
                                    </span>
                                </li>
                                <li className="flex items-start gap-3 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span className="text-foreground">Bank-controlled escrow—we never hold your money</span>
                                </li>
                                <li className="flex items-start gap-3 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span className="text-foreground">Less manual work, fewer errors, full audit trail</span>
                                </li>
                            </ul>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Service Highlights - Bento grid */}
                <AnimatedSection className="py-20">
                    <PublicSectionInner>
                        <div className="text-center">
                            <h2 className="font-display text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                Everything You Need to Run Your Business
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Powerful features designed specifically for South African businesses
                            </p>
                        </div>
                        <StaggeredList className="mt-16 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {featureCards.map((card) => (
                                <StaggeredItem key={card.title}>
                                    <FeatureCard {...card} />
                                </StaggeredItem>
                            ))}
                        </StaggeredList>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Benefits Section */}
                <AnimatedSection className="bg-muted/50 py-20 dark:bg-muted/25">
                    <PublicSectionInner>
                        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                            <div>
                                <h2 className="font-display text-3xl font-bold tracking-tight text-foreground">
                                    Built for South African Businesses
                                </h2>
                                <p className="mt-6 text-lg text-muted-foreground">
                                    We understand the unique challenges of running a business in South Africa. That's
                                    why SwiftPay is built with local tax compliance, banking regulations, and
                                    business practices in mind.
                                </p>
                                <ul className="mt-8 space-y-4">
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Full SARS compliance with automatic tax bracket calculations
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            South African banking integration and escrow support
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Local currency support and SA-specific reporting formats
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Compliance with South African labor and tax regulations
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h2 className="font-display text-3xl font-bold tracking-tight text-foreground">
                                    Complete Control & Transparency
                                </h2>
                                <p className="mt-6 text-lg text-muted-foreground">
                                    Every action is logged, every transaction is tracked, and every calculation is
                                    transparent. You're always in control with SwiftPay.
                                </p>
                                <ul className="mt-8 space-y-4">
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Pause, resume, or cancel payments anytime with one click
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Complete audit trail for every transaction and action
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Real-time status updates and instant notifications
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                        <span className="text-foreground">
                                            Multi-business support—manage everything from one account
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* CTA Section */}
                <AnimatedSection>
                    <PublicCtaBand
                        title="Ready to Streamline Your Business?"
                        description="Join South African businesses that trust SwiftPay for automated payments, payroll, and tax compliance. Get started in minutes."
                    >
                        <div className="flex flex-col items-center gap-4">
                            <Link href={register()}>
                                <Button size="lg" variant="secondary">
                                    Start Free Trial
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            </Link>
                            <div className="flex flex-wrap items-center justify-center gap-2">
                            <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-neutral-300">
                                No credit card required
                            </span>
                            <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-neutral-300">
                                14-day free trial
                            </span>
                            <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-neutral-300">
                                Cancel anytime
                            </span>
                            </div>
                        </div>
                    </PublicCtaBand>
                </AnimatedSection>

                <PublicFooter />
            </div>
        </>
    );
}
