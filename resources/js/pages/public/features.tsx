import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
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
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { PublicNav } from '@/components/public-nav';

export default function Features() {
    return (
        <>
            <Head title="Features - Swift Pay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                {/* Hero Section */}
                <section className="bg-gradient-to-b from-primary/5 to-background py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <Link
                            href="/"
                            className="mb-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to home
                        </Link>
                        <div className="text-center">
                            <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                                Powerful Features for Modern Businesses
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Everything you need to automate payments, process payroll, and maintain tax compliance
                                in one comprehensive platform.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Main Features */}
                <section className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="space-y-20">
                            {/* Payment Automation */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div>
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Zap className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Payment Automation
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Set it, forget it, and let Swift Pay handle the rest. Schedule recurring
                                        payments with flexible cron-based timing that fits your business needs.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Flexible scheduling: daily, weekly, monthly, or custom cron expressions
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Multiple recipients per schedule for efficient batch processing
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                One-time or recurring payments with full control
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Real-time status tracking and instant notifications
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div className="rounded-lg border bg-card p-8">
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
                                </div>
                            </div>

                            {/* Payroll Excellence */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div className="order-2 lg:order-1 rounded-lg border bg-card p-8">
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
                                </div>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <DollarSign className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Payroll Excellence
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Automated payroll with full SA tax compliance—no calculations needed. Process
                                        employee salaries with automatic PAYE, UIF, and SDL deductions.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Automatic PAYE, UIF, and SDL calculations based on current tax brackets
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Custom deductions and adjustments support
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Automated PDF payslip generation and email delivery
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
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
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Tax Compliance Made Simple
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Generate UI-19, EMP201, and IRP5 certificates in minutes. SARS-ready exports
                                        and complete compliance tracking—no more manual paperwork.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                UI-19 declarations with automatic UIF contribution calculations
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                EMP201 monthly tax reconciliation reports
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                IRP5 annual tax certificates for all employees
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                SARS-compatible CSV exports for easy submission
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div className="rounded-lg border bg-card p-8">
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
                                </div>
                            </div>

                            {/* Time Tracking */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div className="order-2 lg:order-1 rounded-lg border bg-card p-8">
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
                                </div>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Clock className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Time & Attendance Tracking
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Real-time employee attendance with geolocation verification. Automatic overtime
                                        calculations and comprehensive leave management.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Sign-in/sign-out tracking with optional geolocation verification
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Automatic overtime detection and calculations
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Manual time entry for managers with approval workflows
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
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
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Escrow Security
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Your funds are always secure in bank-controlled escrow. We never hold your money—everything flows through transparent, regulated escrow accounts.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Bank-controlled escrow accounts—we never touch your funds
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Transparent 1.5% deposit fee—no hidden charges
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Automatic fund reservation for scheduled payments
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Low balance alerts and comprehensive transaction history
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div className="rounded-lg border bg-card p-8">
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
                                </div>
                            </div>

                            {/* Employee Portal */}
                            <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
                                <div className="order-2 lg:order-1 rounded-lg border bg-card p-8">
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
                                </div>
                                <div className="order-1 lg:order-2">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                        <Users className="h-7 w-7 text-primary" />
                                    </div>
                                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                        Employee Self-Service Portal
                                    </h2>
                                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                        Empower your team with self-service access. OTP-based portal for time tracking,
                                        payslip access, and leave requests—no passwords needed.
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Secure OTP-based login via SMS or email
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Access payslips and payment history anytime
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Self-service time tracking and attendance
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <CheckCircle2 className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Submit leave requests and track approval status
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Additional Features Grid */}
                <section className="bg-gray-50 py-16 dark:bg-gray-900/50">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                And So Much More
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                Additional features that make Swift Pay the complete solution for your business
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-lg border bg-card p-6">
                                <Building2 className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Multi-Business Support</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Manage multiple businesses from one account with seamless switching
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <Shield className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Complete Audit Trail</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Every action logged with full details for complete transparency
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <CreditCard className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Flexible Control</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Pause, resume, or cancel payments anytime with one click
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <Mail className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Automated Notifications</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Email notifications for payments, payroll, and important updates
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <FileText className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Custom Email Templates</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Brand your communications with custom email templates
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <TrendingUp className="h-6 w-6 text-primary" />
                                <h3 className="mt-4 font-semibold">Comprehensive Reporting</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Export reports in CSV, Excel, or PDF formats
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="bg-primary py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                Ready to Transform Your Business?
                            </h2>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                                Experience the power of automated payments and payroll. Get started today.
                            </p>
                            <div className="mt-8">
                                <Link href={register()}>
                                    <Button size="lg" variant="secondary">
                                        Get Started Free
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
