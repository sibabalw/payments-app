import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function About() {
    return (
        <>
            <Head title="About - Swift Pay" />
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
                        About Swift Pay
                    </h1>

                    <div className="mt-8 space-y-6 text-lg text-gray-600 dark:text-gray-300">
                        <p>
                            Swift Pay was born from the need to simplify payment automation for businesses of all sizes. 
                            We understand that managing recurring payments, payroll, and supplier payouts can be time-consuming and error-prone.
                        </p>
                        <p>
                            Our mission is to provide a powerful yet intuitive platform that automates your payment processes, 
                            giving you complete control and transparency while saving you time and reducing errors.
                        </p>
                        <p>
                            With Swift Pay, you can schedule payments with flexible timing, manage multiple businesses from one account, 
                            and maintain a complete audit trail of every transaction. We believe in making payment automation accessible 
                            to everyone, from small startups to large enterprises.
                        </p>
                        <p>
                            Built with modern technology and best practices, Swift Pay ensures your payment data is secure, 
                            your processes are reliable, and your business operations run smoothly.
                        </p>
                    </div>

                    <div className="mt-12">
                        <Link href={register()}>
                            <Button size="lg">Get Started Today</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
