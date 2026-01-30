import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicFooter } from '@/components/public-footer';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { PublicSectionInner } from '@/components/public-section';
import { PublicNav } from '@/components/public-nav';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    Clock,
    DollarSign,
    FileText,
    Shield,
    Users,
    Zap,
} from 'lucide-react';
import { register } from '@/routes';

export default function Overview() {
    return (
        <>
            <Head title="Overview - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="What is SwiftPay?"
                    description="SwiftPay is the all-in-one platform for South African businesses to automate payments and payroll. Your funds stay secure in bank-controlled escrow while we handle full tax compliance (PAYE, UIF, SDL), time tracking, and employee self-service."
                >
                    <div className="mt-8">
                        <Link href={register()}>
                            <Button variant="gradient" size="lg" className="group">
                                Get Started Free
                                <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                            </Button>
                        </Link>
                    </div>
                </PublicInnerHero>

                {/* Who it's for / What you get / What to expect */}
                <AnimatedSection className="py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-8 md:grid-cols-3">
                            <div className="rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <h2 className="text-xl font-semibold text-foreground">Who it's for</h2>
                                <p className="mt-3 text-muted-foreground">
                                    South African businesses—from startups to enterprises—that want to automate
                                    payments and payroll without spreadsheets or manual tax work.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <h2 className="text-xl font-semibold text-foreground">What you get</h2>
                                <p className="mt-3 text-muted-foreground">
                                    One platform for payment scheduling, payroll with SA tax (PAYE, UIF, SDL),
                                    compliance (UI-19, EMP201, IRP5), time tracking, and employee self-service.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <h2 className="text-xl font-semibold text-foreground">What to expect</h2>
                                <p className="mt-3 text-muted-foreground">
                                    Less manual work, fewer errors, a full audit trail, and bank-controlled escrow so
                                    your funds stay secure.
                                </p>
                            </div>
                        </div>
                    </div>
                </AnimatedSection>

                {/* Key features at a glance */}
                <AnimatedSection className="bg-muted/50 py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                Key Features at a Glance
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Everything you need to run payments and payroll with confidence
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Payment Automation</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Schedule recurring payments with flexible timing. Set it once and let SwiftPay
                                        run.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <DollarSign className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Automated Payroll</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Full SA tax compliance: PAYE, UIF, SDL. Digital payslips and compliance
                                        certificates.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Tax Compliance</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        UI-19, EMP201, IRP5 generation. SARS-ready exports and full audit trail.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Clock className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Time & Attendance</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Employee time tracking, overtime, and leave management.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Bank-Controlled Escrow</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Your money stays in bank-controlled accounts. We never hold your funds.
                                    </p>
                                </div>
                            </div>
                            <div className="flex gap-4 rounded-lg border bg-card p-6 transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Users className="h-6 w-6 text-primary" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Employee Self-Service</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        OTP-based portal for payslips, time tracking, and leave requests.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </AnimatedSection>

                {/* Explore more */}
                <AnimatedSection className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                Explore More
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Dive deeper into features, see how it works, and find the right plan for you.
                            </p>
                        </div>
                        <ul className="mt-10 flex flex-wrap justify-center gap-4">
                            <li>
                                <Link
                                    href="/features"
                                    className="inline-flex items-center gap-2 rounded-lg border bg-card px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                                >
                                    Features
                                    <Check className="h-4 w-4 text-primary" />
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/how-it-works"
                                    className="inline-flex items-center gap-2 rounded-lg border bg-card px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                                >
                                    How it works
                                    <Check className="h-4 w-4 text-primary" />
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/pricing"
                                    className="inline-flex items-center gap-2 rounded-lg border bg-card px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                                >
                                    Pricing
                                    <Check className="h-4 w-4 text-primary" />
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/about"
                                    className="inline-flex items-center gap-2 rounded-lg border bg-card px-6 py-3 text-sm font-medium text-foreground hover:bg-muted"
                                >
                                    About
                                    <Check className="h-4 w-4 text-primary" />
                                </Link>
                            </li>
                        </ul>
                    </div>
                </AnimatedSection>

                <PublicCtaBand
                    title="Ready to Get Started?"
                    description="Join South African businesses that trust SwiftPay. No credit card required."
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
