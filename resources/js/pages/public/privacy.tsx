import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy - Swift Pay" />
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
                        Privacy Policy
                    </h1>
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Last updated: {new Date().toLocaleDateString()}
                    </p>

                    <div className="mt-8 space-y-6 text-gray-600 dark:text-gray-300">
                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Information We Collect</h2>
                            <p className="mt-2">
                                Swift Pay collects information that you provide directly to us, including account information, 
                                payment details, and business information necessary to provide our services.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">How We Use Your Information</h2>
                            <p className="mt-2">
                                We use the information we collect to provide, maintain, and improve our services, process payments, 
                                and communicate with you about your account and our services.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Data Security</h2>
                            <p className="mt-2">
                                We implement appropriate technical and organizational measures to protect your personal information 
                                against unauthorized access, alteration, disclosure, or destruction.
                            </p>
                        </section>

                        <section>
                            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white">Your Rights</h2>
                            <p className="mt-2">
                                You have the right to access, update, or delete your personal information at any time. 
                                You can also opt out of certain communications from us.
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </>
    );
}
