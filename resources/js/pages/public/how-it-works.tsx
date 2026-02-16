import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    Calendar,
    FileText,
    Lock,
    Users,
} from 'lucide-react';
import { register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

const steps = [
    {
        number: 1,
        title: 'Sign up and add your business',
        description:
            'Create your SwiftPay account and add your business details. Connect your bank and set up your escrow—funds stay in bank-controlled accounts, so we never hold your money.',
        icon: Building2,
    },
    {
        number: 2,
        title: 'Add recipients and employees',
        description:
            'For payments: add recipients and their bank details. For payroll: add employees with tax info, salaries, and benefits. You can run payments only, payroll only, or both.',
        icon: Users,
    },
    {
        number: 3,
        title: 'Set up schedules',
        description:
            'Define when and how much to pay. Payment schedules support daily, weekly, monthly, or custom cron-based timing. Payroll schedules run on your chosen cycle with automatic PAYE, UIF, and SDL.',
        icon: Calendar,
    },
    {
        number: 4,
        title: 'Fund escrow and let SwiftPay run',
        description:
            'Deposit into your escrow account. SwiftPay runs payments and payroll on schedule, handles tax and compliance (UI-19, EMP201, IRP5), and keeps a full audit trail.',
        icon: Lock,
    },
    {
        number: 5,
        title: 'Employees get payslips and use the portal',
        description:
            'Payslips are generated and can be emailed. Employees can sign in with OTP to view payslips, track time, and submit leave requests—no passwords required.',
        icon: FileText,
    },
];

export default function HowItWorks() {
    return (
        <>
            <Head title="How it works - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="How it works"
                    description="From signup to payments and payroll—see how SwiftPay automates your operations with bank-controlled escrow and full SA tax compliance."
                />

                {/* Steps */}
                <AnimatedSection className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="space-y-16">
                            {steps.map((step) => {
                                const Icon = step.icon;
                                return (
                                    <div
                                        key={step.number}
                                        className="grid gap-8 lg:grid-cols-2 lg:items-center"
                                    >
                                        <div
                                            className={
                                                step.number % 2 === 0
                                                    ? 'lg:order-2'
                                                    : ''
                                            }
                                        >
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full bg-primary text-lg font-bold text-primary-foreground">
                                                    {step.number}
                                                </div>
                                                <h2 className="text-2xl font-bold tracking-tight text-foreground">
                                                    {step.title}
                                                </h2>
                                            </div>
                                            <p className="mt-6 text-lg text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </div>
                                        <div
                                            className={
                                                step.number % 2 === 0
                                                    ? 'lg:order-1'
                                                    : ''
                                            }
                                        >
                                            <div className="rounded-lg border bg-card p-8">
                                                <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-primary/10">
                                                    <Icon className="h-8 w-8 text-primary" />
                                                </div>
                                                <p className="mt-4 font-medium text-foreground">
                                                    Step {step.number}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </AnimatedSection>

                <PublicCtaBand
                    title="Ready to get started?"
                    description="Join South African businesses using SwiftPay for automated payments and payroll. No credit card required."
                >
                    <Link href={register()}>
                        <Button variant="gradient" size="lg">
                            Start Free Trial
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </Link>
                </PublicCtaBand>

                <PublicFooter />
            </div>
        </>
    );
}