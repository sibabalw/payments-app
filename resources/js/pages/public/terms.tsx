import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Service - Swift Pay" />
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
                        Terms of Service
                    </h1>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Last updated: {new Date().toLocaleDateString()}
                    </p>

                    <div className="mt-8 space-y-6 text-gray-600 dark:text-gray-300">
                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Acceptance of Terms</h2>
                            <p className="mt-2">
                                By accessing and using Swift Pay, you accept and agree to be bound by the terms and provision of this agreement.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Use License</h2>
                            <p className="mt-2">
                                Permission is granted to use Swift Pay for business purposes. This license does not include any resale or 
                                commercial use of the service without express written consent.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Account Responsibility</h2>
                            <p className="mt-2">
                                You are responsible for maintaining the confidentiality of your account and password and for restricting 
                                access to your computer. You agree to accept responsibility for all activities that occur under your account.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Service Availability</h2>
                            <p className="mt-2">
                                We strive to provide reliable service but do not guarantee uninterrupted access. We reserve the right to 
                                modify or discontinue the service at any time with or without notice.
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
