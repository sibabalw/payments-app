import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, Shield, AlertCircle, CheckCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Service - SwiftPay" />
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
                                Terms of Service
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Please read these terms carefully before using SwiftPay. By using our services, you
                                agree to be bound by these terms.
                            </p>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Last updated: {new Date().toLocaleDateString('en-ZA', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                })}
                            </p>
                        </div>
                    </div>
                </section>

                {/* Terms Content */}
                <section className="py-16">
                    <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                        <div className="space-y-12">
                            {/* Introduction */}
                            <div>
                                <p className="text-lg text-gray-700 dark:text-gray-300">
                                    These Terms of Service ("Terms") govern your access to and use of SwiftPay's
                                    payment and payroll automation platform ("Service"). By accessing or using SwiftPay,
                                    you agree to be bound by these Terms. If you disagree with any part of these Terms,
                                    you may not access the Service.
                                </p>
                            </div>

                            {/* Acceptance of Terms */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <CheckCircle className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Acceptance of Terms
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        By creating an account, accessing, or using SwiftPay, you acknowledge that you
                                        have read, understood, and agree to be bound by these Terms and our Privacy
                                        Policy. If you are using SwiftPay on behalf of a business, you represent that
                                        you have the authority to bind that business to these Terms.
                                    </p>
                                    <p>
                                        You must be at least 18 years old and have the legal capacity to enter into
                                        binding contracts to use our Service. By using SwiftPay, you represent and
                                        warrant that you meet these requirements.
                                    </p>
                                </div>
                            </div>

                            {/* Description of Service */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <FileText className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Description of Service
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        SwiftPay provides a cloud-based platform for automating payment scheduling,
                                        payroll processing, tax compliance, and related financial services for South
                                        African businesses. Our services include:
                                    </p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>Automated payment scheduling and processing</li>
                                        <li>Payroll management with automatic tax calculations</li>
                                        <li>Tax compliance document generation (UI-19, EMP201, IRP5)</li>
                                        <li>Time and attendance tracking</li>
                                        <li>Escrow account management</li>
                                        <li>Employee self-service portal</li>
                                    </ul>
                                    <p>
                                        We reserve the right to modify, suspend, or discontinue any aspect of the Service
                                        at any time, with or without notice.
                                    </p>
                                </div>
                            </div>

                            {/* Account Registration */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Account Registration and Security
                                </h2>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>To use SwiftPay, you must:</p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>Create an account with accurate, current, and complete information</li>
                                        <li>Maintain and promptly update your account information</li>
                                        <li>Maintain the security of your account credentials</li>
                                        <li>Accept responsibility for all activities under your account</li>
                                        <li>Notify us immediately of any unauthorized access or security breach</li>
                                    </ul>
                                    <p>
                                        You are responsible for maintaining the confidentiality of your account password
                                        and for all activities that occur under your account. SwiftPay is not liable for
                                        any loss or damage arising from your failure to maintain account security.
                                    </p>
                                </div>
                            </div>

                            {/* Use License */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Shield className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Use License</h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        Subject to your compliance with these Terms, SwiftPay grants you a limited,
                                        non-exclusive, non-transferable, revocable license to access and use the Service
                                        for your business purposes.
                                    </p>
                                    <p>You agree not to:</p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>Use the Service for any illegal or unauthorized purpose</li>
                                        <li>Violate any laws, regulations, or third-party rights</li>
                                        <li>Interfere with or disrupt the Service or servers</li>
                                        <li>Attempt to gain unauthorized access to any part of the Service</li>
                                        <li>Reverse engineer, decompile, or disassemble any part of the Service</li>
                                        <li>Use automated systems to access the Service without permission</li>
                                        <li>Resell, sublicense, or commercialize the Service without authorization</li>
                                    </ul>
                                </div>
                            </div>

                            {/* Payment Terms */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Payment Terms</h2>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        <strong>Subscription Fees:</strong> SwiftPay operates on a subscription model
                                        with monthly fees as outlined on our Pricing page. Fees are billed in advance
                                        and are non-refundable except as required by law.
                                    </p>
                                    <p>
                                        <strong>Escrow Deposits:</strong> To process payments, you must deposit funds
                                        into a bank-controlled escrow account. A 1.5% processing fee applies per
                                        deposit. Funds remain in escrow until payments are executed or returned.
                                    </p>
                                    <p>
                                        <strong>Payment Processing:</strong> We process payments through bank-controlled
                                        escrow accounts. We never hold your funds in our own accounts. If payment
                                        execution fails, funds are automatically returned to you.
                                    </p>
                                    <p>
                                        <strong>Refunds:</strong> Subscription fees are non-refundable. Escrow deposits
                                        are returned if payment execution fails, minus any applicable fees for completed
                                        transactions.
                                    </p>
                                </div>
                            </div>

                            {/* Service Availability */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <AlertCircle className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Service Availability
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        While we strive to provide reliable and uninterrupted service, we do not
                                        guarantee that the Service will be available at all times. The Service may be
                                        unavailable due to:
                                    </p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>Scheduled maintenance and updates</li>
                                        <li>Technical issues or system failures</li>
                                        <li>Force majeure events beyond our control</li>
                                        <li>Third-party service disruptions</li>
                                    </ul>
                                    <p>
                                        We reserve the right to modify, suspend, or discontinue the Service at any time
                                        with or without notice. We are not liable for any loss or damage resulting from
                                        Service unavailability.
                                    </p>
                                </div>
                            </div>

                            {/* Intellectual Property */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Intellectual Property Rights
                                </h2>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        The Service, including all content, features, functionality, and software, is
                                        owned by SwiftPay and protected by South African and international copyright,
                                        trademark, and other intellectual property laws.
                                    </p>
                                    <p>
                                        You retain ownership of all data and content you upload to the Service. By using
                                        SwiftPay, you grant us a license to use, store, and process your data solely for
                                        the purpose of providing the Service.
                                    </p>
                                </div>
                            </div>

                            {/* Limitation of Liability */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Limitation of Liability
                                </h2>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        To the maximum extent permitted by law, SwiftPay shall not be liable for any
                                        indirect, incidental, special, consequential, or punitive damages, including loss
                                        of profits, data, or business opportunities, arising from your use of the
                                        Service.
                                    </p>
                                    <p>
                                        Our total liability for any claims arising from the Service shall not exceed the
                                        amount you paid to us in the 12 months preceding the claim.
                                    </p>
                                    <p>
                                        We are not responsible for errors in tax calculations or compliance documents
                                        resulting from incorrect data you provide. You are responsible for verifying the
                                        accuracy of all information and consulting with tax professionals as needed.
                                    </p>
                                </div>
                            </div>

                            {/* Indemnification */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Indemnification</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    You agree to indemnify, defend, and hold harmless SwiftPay, its officers,
                                    directors, employees, and agents from any claims, damages, losses, liabilities, and
                                    expenses (including legal fees) arising from your use of the Service, violation of
                                    these Terms, or infringement of any rights of another party.
                                </p>
                            </div>

                            {/* Termination */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Termination</h2>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        You may terminate your account at any time by contacting us or using the account
                                        deletion feature in your settings.
                                    </p>
                                    <p>We may terminate or suspend your account immediately if:</p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>You breach these Terms</li>
                                        <li>You engage in fraudulent or illegal activity</li>
                                        <li>You fail to pay subscription fees</li>
                                        <li>We are required to do so by law</li>
                                    </ul>
                                    <p>
                                        Upon termination, your right to use the Service will cease immediately. We will
                                        retain your data for the period required by law, after which it will be deleted
                                        or anonymized.
                                    </p>
                                </div>
                            </div>

                            {/* Governing Law */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Governing Law</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    These Terms shall be governed by and construed in accordance with the laws of the
                                    Republic of South Africa, without regard to its conflict of law provisions. Any
                                    disputes arising from these Terms shall be subject to the exclusive jurisdiction of
                                    the courts of South Africa.
                                </p>
                            </div>

                            {/* Changes to Terms */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Changes to These Terms
                                </h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    We reserve the right to modify these Terms at any time. We will notify you of
                                    material changes by posting the updated Terms on this page and updating the "Last
                                    updated" date. Your continued use of the Service after such changes constitutes
                                    acceptance of the modified Terms. If you do not agree to the changes, you must stop
                                    using the Service and may terminate your account.
                                </p>
                            </div>

                            {/* Contact */}
                            <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-6 dark:bg-primary/10">
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Contact Us</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    If you have questions about these Terms of Service, please contact us:
                                </p>
                                <div className="mt-4 space-y-2">
                                    <p className="text-gray-700 dark:text-gray-300">
                                        <strong>Email:</strong>{' '}
                                        <a
                                            href="mailto:legal@swiftpay.co.za"
                                            className="text-primary hover:underline"
                                        >
                                            legal@swiftpay.co.za
                                        </a>
                                    </p>
                                    <p className="text-gray-700 dark:text-gray-300">
                                        <strong>Support:</strong>{' '}
                                        <a
                                            href="mailto:support@swiftpay.co.za"
                                            className="text-primary hover:underline"
                                        >
                                            support@swiftpay.co.za
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <PublicFooter />
            </div>
        </>
    );
}
