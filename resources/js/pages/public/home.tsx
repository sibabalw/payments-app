import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    CreditCard,
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
import { PublicNav } from '@/components/public-nav';

export default function Home() {
    return (
        <>
            <Head title="Swift Pay - Automate Payments & Payroll with Confidence" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                {/* Hero Section */}
                <section className="bg-gradient-to-b from-primary/5 via-background to-background py-20 sm:py-24 lg:py-32">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="text-5xl font-bold tracking-tight text-foreground sm:text-6xl lg:text-7xl">
                                Automate Payments & Payroll
                                <span className="text-primary"> with Confidence</span>
                            </h1>
                            <p className="mx-auto mt-6 max-w-3xl text-xl leading-8 text-muted-foreground">
                                The all-in-one platform for South African businesses. Streamline your payment scheduling,
                                automate payroll with full tax compliance, and eliminate manual calculations forever.
                            </p>
                            <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-x-6">
                                <Link href={register()}>
                                    <Button size="lg" className="group w-full sm:w-auto">
                                        Start Free Trial
                                        <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                                    </Button>
                                </Link>
                                <Link href="/features">
                                    <Button variant="outline" size="lg" className="w-full sm:w-auto">
                                        Explore Features
                                    </Button>
                                </Link>
                            </div>
                            <p className="mt-6 text-sm text-muted-foreground">
                                No credit card required • Set up in minutes • Bank-controlled escrow
                            </p>
                        </div>
                    </div>
                </section>

                {/* Key Differentiators */}
                <section className="bg-primary py-16 text-white">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                Why South African Businesses Choose Swift Pay
                            </h2>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-8 md:grid-cols-3">
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10">
                                    <Calculator className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">SA Tax Compliance Built-In</h3>
                                <p className="mt-4 text-primary-foreground/90">
                                    Automatic PAYE, UIF, and SDL calculations. Generate UI-19, EMP201, and IRP5
                                    certificates with zero manual work.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10">
                                    <Lock className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Bank-Controlled Escrow</h3>
                                <p className="mt-4 text-primary-foreground/90">
                                    Your funds are always secure. We never hold your money—everything flows through
                                    bank-controlled escrow accounts.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-white/10">
                                    <Zap className="h-8 w-8" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Zero Manual Calculations</h3>
                                <p className="mt-4 text-primary-foreground/90">
                                    Set it and forget it. Our platform handles all calculations, scheduling, and
                                    compliance automatically.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Service Highlights */}
                <section className="py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                Everything You Need to Run Your Business
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Powerful features designed specifically for South African businesses
                            </p>
                        </div>
                        <div className="mt-16 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Payment Automation</h3>
                                <p className="mt-2 text-muted-foreground">
                                    Schedule recurring payments with flexible cron-based timing. Daily, weekly,
                                    monthly, or custom intervals—set it once and let Swift Pay handle the rest.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <DollarSign className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Automated Payroll</h3>
                                <p className="mt-2 text-muted-foreground">
                                    Process employee salaries with full South African tax compliance. Automatic PAYE,
                                    UIF, and SDL deductions—no spreadsheets, no errors.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Tax Compliance</h3>
                                <p className="mt-2 text-muted-foreground">
                                    Generate UI-19 declarations, EMP201 submissions, and IRP5 certificates in minutes.
                                    SARS-ready exports and complete compliance tracking.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Clock className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Time & Attendance</h3>
                                <p className="mt-2 text-muted-foreground">
                                    Real-time employee time tracking with geolocation verification. Automatic overtime
                                    calculations and comprehensive leave management.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Bank-Controlled Escrow</h3>
                                <p className="mt-2 text-muted-foreground">
                                    Transparent, secure fund management. Your money stays in bank-controlled escrow
                                    accounts—we never touch your funds.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Users className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Employee Self-Service</h3>
                                <p className="mt-2 text-muted-foreground">
                                    OTP-based employee portal for time tracking, payslip access, and leave requests.
                                    Empower your team with self-service capabilities.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Benefits Section */}
                <section className="bg-muted/50 py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                            <div>
                                <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                    Built for South African Businesses
                                </h2>
                                <p className="mt-6 text-lg text-muted-foreground">
                                    We understand the unique challenges of running a business in South Africa. That's why
                                    Swift Pay is built with local tax compliance, banking regulations, and business
                                    practices in mind.
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
                                <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                    Complete Control & Transparency
                                </h2>
                                <p className="mt-6 text-lg text-muted-foreground">
                                    Every action is logged, every transaction is tracked, and every calculation is
                                    transparent. You're always in control with Swift Pay.
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
                    </div>
                </section>

                {/* CTA Section */}
                <section className="bg-primary py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                Ready to Streamline Your Business?
                            </h2>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                                Join South African businesses that trust Swift Pay for automated payments, payroll, and
                                tax compliance. Get started in minutes.
                            </p>
                            <div className="mt-8">
                                <Link href={register()}>
                                    <Button size="lg" variant="secondary">
                                        Start Free Trial
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                            <p className="mt-4 text-sm text-primary-foreground/80">
                                No credit card required • 14-day free trial • Cancel anytime
                            </p>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t bg-background py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                            <div>
                                <div className="flex items-center gap-2">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                        <Building2 className="h-5 w-5" />
                                    </div>
                                    <h3 className="text-lg font-bold">Swift Pay</h3>
                                </div>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Automated payment and payroll solutions for South African businesses.
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold">Product</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link
                                            href="/features"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            Features
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/pricing"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            Pricing
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-semibold">Company</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link
                                            href="/about"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            About
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/contact"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            Contact
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-semibold">Legal</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link
                                            href="/privacy"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            Privacy Policy
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href="/terms"
                                            className="text-muted-foreground hover:text-foreground"
                                        >
                                            Terms of Service
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="mt-8 border-t pt-8 text-center text-sm text-muted-foreground">
                            <p>&copy; {new Date().getFullYear()} Swift Pay. All rights reserved.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
