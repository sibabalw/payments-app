import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, DollarSign, FileText, Pause, Shield, Users, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Features() {
    return (
        <>
            <Head title="Features - Swift Pay" />
            <div className="flex min-h-screen flex-col">
                <nav className="border-b bg-white dark:bg-gray-900">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 items-center justify-between">
                            <Link href="/" className="text-2xl font-bold text-primary">Swift Pay</Link>
                            <div className="flex items-center gap-4">
                                <Link href={login()}>
                                    <Button variant="ghost" size="sm">Log in</Button>
                                </Link>
                                <Link href={register()}>
                                    <Button size="sm">Get Started</Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <Link href="/" className="mb-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to home
                    </Link>

                    <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Powerful Features for Payment Automation
                    </h1>
                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                        Everything you need to automate and manage your business payments efficiently.
                    </p>

                    <div className="mt-12 space-y-12">
                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Calendar className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Flexible Scheduling</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Use cron expressions to schedule payments exactly when you need them. Support for daily, weekly, monthly, 
                                    or any custom schedule. Set it once and forget it - we'll handle the rest.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <DollarSign className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Dedicated Payroll Module</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Separate payroll management from other payments. Keep employee salaries organized and easily accessible. 
                                    Track payroll history and manage schedules independently.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Users className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Unified Receiver Management</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Manage all payment recipients in one place. Whether they're employees, suppliers, or contractors, 
                                    store their information securely and assign them to multiple payment schedules.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Pause className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Full Control</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Pause, resume, or cancel any payment schedule instantly. No need to delete and recreate - 
                                    simply toggle the status and we'll handle the rest. Perfect for temporary holds or permanent cancellations.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Complete Audit Trail</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Every action is logged with full details: who performed it, when, and what changed. 
                                    Track payment executions, schedule modifications, and system events for complete transparency and compliance.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Real-Time Processing</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Payments are processed asynchronously through our queue system. Monitor job status in real-time, 
                                    see success and failure rates, and get instant notifications when issues occur.
                                </p>
                            </div>
                        </div>

                        <div className="flex gap-6">
                            <div className="flex-shrink-0">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <FileText className="h-6 w-6 text-primary" />
                                </div>
                            </div>
                            <div>
                                <h2 className="text-2xl font-semibold">Multi-Business Support</h2>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Manage multiple businesses from a single account. Switch between businesses seamlessly, 
                                    and keep all payment data organized and separated by business entity.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-12 text-center">
                        <Link href={register()}>
                            <Button size="lg">
                                Get Started Free
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
