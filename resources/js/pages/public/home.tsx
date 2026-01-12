import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Check, CreditCard, DollarSign, Shield, Users, Zap } from 'lucide-react';
import { login, register } from '@/routes';

export default function Home() {
    return (
        <>
            <Head title="Swift Pay - Automated Payment Solutions" />
            <div className="flex min-h-screen flex-col">
                {/* Navigation */}
                <nav className="border-b bg-white dark:bg-gray-900">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 items-center justify-between">
                            <div className="flex items-center">
                                <span className="text-2xl font-bold text-primary">Swift Pay</span>
                            </div>
                            <div className="flex items-center gap-4">
                                <Link href="/features" className="text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                    Features
                                </Link>
                                <Link href="/pricing" className="text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                    Pricing
                                </Link>
                                <Link href="/about" className="text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                    About
                                </Link>
                                <Link href={login()}>
                                    <Button variant="ghost" size="sm">
                                        Log in
                                    </Button>
                                </Link>
                                <Link href={register()}>
                                    <Button size="sm">
                                        Get Started
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="bg-gradient-to-b from-primary/5 to-background py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="text-5xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                                Automate Your Payments
                                <span className="text-primary"> Swiftly</span>
                            </h1>
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600 dark:text-gray-300">
                                Schedule automated payments for payroll, suppliers, and more. Pause, resume, or cancel anytime. 
                                Complete audit trail for every transaction.
                            </p>
                            <div className="mt-10 flex items-center justify-center gap-x-6">
                                <Link href={register()}>
                                    <Button size="lg" className="group">
                                        Get Started Free
                                        <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                                    </Button>
                                </Link>
                                <Link href="/features">
                                    <Button variant="outline" size="lg">
                                        Learn More
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-20">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                                Everything you need to automate payments
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                Powerful features designed for modern businesses
                            </p>
                        </div>
                        <div className="mt-16 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Automated Scheduling</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Set up recurring payments with flexible cron-based scheduling. Daily, weekly, monthly, or custom intervals.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <DollarSign className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Payroll Management</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Dedicated payroll module for managing employee salaries. Separate from other payment types for better organization.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Users className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Receiver Management</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Manage all your payment recipients in one place. Employees, suppliers, contractors - all in a unified system.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Complete Audit Trail</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Every action is logged with full details. Track who did what, when, and why for complete transparency.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <CreditCard className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Flexible Control</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Pause, resume, or cancel payments anytime. Full control over your payment schedules with real-time status updates.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Check className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Multi-Business Support</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Manage multiple businesses from one account. Switch between businesses seamlessly.
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
                                Ready to streamline your payments?
                            </h2>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                                Join businesses that trust Swift Pay for their payment automation needs.
                            </p>
                            <div className="mt-8">
                                <Link href={register()}>
                                    <Button size="lg" variant="secondary">
                                        Start Free Trial
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t bg-background py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                            <div>
                                <h3 className="text-lg font-bold">Swift Pay</h3>
                                <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    Automated payment solutions for modern businesses.
                                </p>
                            </div>
                            <div>
                                <h4 className="font-semibold">Product</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link href="/features" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            Features
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/pricing" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            Pricing
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-semibold">Company</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link href="/about" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            About
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/contact" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            Contact
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="font-semibold">Legal</h4>
                                <ul className="mt-4 space-y-2 text-sm">
                                    <li>
                                        <Link href="/privacy" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            Privacy Policy
                                        </Link>
                                    </li>
                                    <li>
                                        <Link href="/terms" className="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                            Terms of Service
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="mt-8 border-t pt-8 text-center text-sm text-gray-600 dark:text-gray-300">
                            <p>&copy; {new Date().getFullYear()} Swift Pay. All rights reserved.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
