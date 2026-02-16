import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { PublicSectionInner } from '@/components/public-section';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    DollarSign,
    FileText,
    Pause,
    Shield,
    Users,
    Zap,
    Clock,
    Building2,
    Lock,
    Calculator,
    CheckCircle2,
    CreditCard,
    Mail,
    TrendingUp,
} from 'lucide-react';
import { login, register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

export default function Features() {
    return (
        <>
            <Head title="Features - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="Powerful Features for Modern Businesses"
                    description="Everything you need to automate payments, process payroll, and maintain tax compliance in one comprehensive platform. Here's what to expect from SwiftPay."
                />

                {/* What to expect */}
                <AnimatedSection className="py-12">
                    <PublicSectionInner>
                        <PublicCard variant="glass" className="border-2 border-primary/20 bg-primary/5 p-8 dark:bg-primary/10">
                            <p className="text-lg text-muted-foreground">
                                SwiftPay is the all-in-one platform for South African businesses to automate
                                payments and payroll with full tax compliance and bank-controlled escrow.
                            </p>
                            <h2 className="mt-6 font-display text-2xl font-bold tracking-tight text-foreground">
                                What to Expect
                            </h2>
                            <div className="mt-6 grid gap-6 md:grid-cols-3">
                                <div>
                                    <h3 className="font-semibold text-foreground">Who it's for</h3>
                                    <p className="mt-2 text-muted-foreground">
                                        South African businesses—from startups to enterprises—that want to automate
                                        payments and payroll without spreadsheets or manual tax work.
                                    </p>
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">What you get</h3>
                                    <p className="mt-2 text-muted-foreground">
                                        One platform for scheduling payments, running payroll with SA tax (PAYE, UIF,
                                        SDL), compliance (UI-19, EMP201, IRP5), time tracking, and employee self-service.
                                    </p>
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Outcome</h3>
                                    <p className="mt-2 text-muted-foreground">
                                        Less manual work, fewer errors, a full audit trail, and bank-controlled escrow
                                        so your funds stay secure.
                                    </p>
                                </div>
                            </div>
                        </PublicCard>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Main Features */}
                <AnimatedSection className="py-16">
                    <PublicSectionInner>
                        <div className="space-y-20">
                            {/* Payment Automation */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div>
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Zap className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Payment Automation
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Set it, forget it, and let SwiftPay handle the rest. Schedule recurring
                                        payments with flexible cron-based timing that fits your business needs.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Flexible scheduling: daily, weekly, monthly, or custom cron expressions
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Multiple recipients per schedule for efficient batch processing
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                One-time or recurring payments with full control
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Real-time status tracking and instant notifications
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <PublicCard variant="elevated" className="p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Flexible Scheduling</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Multi-Recipient Support</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Pause className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Pause/Resume Anytime</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Shield className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Complete Audit Trail</span>
                                        </div>
                                    </div>
                                </PublicCard>
                            </div>

                            {/* Payroll Excellence */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <PublicCard variant="elevated" className="order-2 lg:order-1 p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Calculator className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Automatic Tax Calculations</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Digital Payslips</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <TrendingUp className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Overtime Calculations</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Mail className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Automated Email Delivery</span>
                                        </div>
                                    </div>
                                </PublicCard>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <DollarSign className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Payroll Excellence
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Automated payroll with full SA tax compliance—no calculations needed. Process
                                        employee salaries with automatic PAYE, UIF, and SDL deductions.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Automatic PAYE, UIF, and SDL calculations based on current tax brackets
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Custom deductions and adjustments support
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Automated PDF payslip generation and email delivery
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Overtime calculations and leave management integration
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            {/* Tax Compliance */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div>
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <FileText className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Tax Compliance Made Simple
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Generate UI-19, EMP201, and IRP5 certificates in minutes. SARS-ready exports
                                        and complete compliance tracking—no more manual paperwork.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                UI-19 declarations with automatic UIF contribution calculations
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                EMP201 monthly tax reconciliation reports
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                IRP5 annual tax certificates for all employees
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                SARS-compatible CSV exports for easy submission
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <PublicCard variant="elevated" className="p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">UI-19 Declarations</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">EMP201 Submissions</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">IRP5 Certificates</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">SARS Export Formats</span>
                                        </div>
                                    </div>
                                </PublicCard>
                            </div>

                            {/* Time Tracking */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <PublicCard variant="elevated" className="order-2 lg:order-1 p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Clock className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Real-Time Tracking</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Shield className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Geolocation Verification</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <TrendingUp className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Overtime Detection</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Employee Self-Service</span>
                                        </div>
                                    </div>
                                </PublicCard>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Clock className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Time & Attendance Tracking
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Real-time employee attendance with geolocation verification. Automatic overtime
                                        calculations and comprehensive leave management.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Sign-in/sign-out tracking with optional geolocation verification
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Automatic overtime detection and calculations
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Manual time entry for managers with approval workflows
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Leave management with request and approval tracking
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            {/* Escrow Security */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div>
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Lock className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Escrow Security
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Your funds are always secure in bank-controlled escrow. We never hold your money—everything flows through transparent, regulated escrow accounts.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Bank-controlled escrow accounts—we never touch your funds
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Transparent 1.5% deposit fee—no hidden charges
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Automatic fund reservation for scheduled payments
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Low balance alerts and comprehensive transaction history
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <PublicCard variant="elevated" className="p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Lock className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Bank-Controlled Security</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <CreditCard className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Transparent Pricing</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Shield className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Fund Protection</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <TrendingUp className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Real-Time Balance Tracking</span>
                                        </div>
                                    </div>
                                </PublicCard>
                            </div>

                            {/* Employee Portal */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <PublicCard variant="elevated" className="order-2 lg:order-1 p-8">
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3">
                                            <Users className="h-5 w-5 text-primary" />
                                            <span className="font-medium">OTP-Based Access</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <FileText className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Payslip Access</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Clock className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Time Tracking</span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Mail className="h-5 w-5 text-primary" />
                                            <span className="font-medium">Leave Requests</span>
                                        </div>
                                    </div>
                                </PublicCard>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Users className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                        Employee Self-Service Portal
                                    </h2>
                                    <p className="mt-4 text-lg text-muted-foreground">
                                        Empower your team with self-service access. OTP-based portal for time tracking,
                                        payslip access, and leave requests—no passwords needed.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Secure OTP-based login via SMS or email
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Access payslips and payment history anytime
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Self-service time tracking and attendance
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-muted-foreground">
                                                Submit leave requests and track approval status
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Additional Features Grid */}
                <AnimatedSection className="bg-muted/50 py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="font-display text-3xl font-bold tracking-tight text-foreground">
                                And So Much More
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Additional features that make SwiftPay the complete solution for your business
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <PublicCard variant="elevated" className="p-6">
                                <Building2 className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Multi-Business Support</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Manage multiple businesses from one account with seamless switching
                                </p>
                            </PublicCard>
                            <PublicCard variant="elevated" className="p-6">
                                <Shield className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Complete Audit Trail</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Every action logged with full details for complete transparency
                                </p>
                            </PublicCard>
                            <PublicCard variant="elevated" className="p-6">
                                <CreditCard className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Flexible Control</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Pause, resume, or cancel payments anytime with one click
                                </p>
                            </PublicCard>
                            <PublicCard variant="elevated" className="p-6">
                                <Mail className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Automated Notifications</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Email notifications for payments, payroll, and important updates
                                </p>
                            </PublicCard>
                            <PublicCard variant="elevated" className="p-6">
                                <FileText className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Custom Email Templates</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Brand your communications with custom email templates
                                </p>
                            </PublicCard>
                            <PublicCard variant="elevated" className="p-6">
                                <TrendingUp className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-display font-semibold">Comprehensive Reporting</h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Export reports in CSV, Excel, or PDF formats
                                </p>
                            </PublicCard>
                        </div>
                    </div>
                </AnimatedSection>

                {/* CTA Section */}
                <AnimatedSection>
                    <PublicCtaBand
                        title="Ready to Transform Your Business?"
                        description="Experience the power of automated payments and payroll. Get started today."
                    >
                        <Link href={register()}>
                            <Button variant="gradient" size="lg">
                                Get Started Free
                            </Button>
                        </Link>
                    </PublicCtaBand>
                </AnimatedSection>
                <PublicFooter />
            </div>
        </>
    );
}
