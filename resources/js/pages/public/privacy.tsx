import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Shield, Lock, Eye, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { PublicNav } from '@/components/public-nav';

export default function Privacy() {
    return (
        <>
            <Head title="Privacy Policy - Swift Pay" />
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
                                Privacy Policy
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Your privacy is important to us. This policy explains how we collect, use, and protect
                                your personal information.
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

                {/* Privacy Content */}
                <section className="py-16">
                    <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                        <div className="space-y-12">
                            {/* Introduction */}
                            <div>
                                <p className="text-lg text-gray-700 dark:text-gray-300">
                                    Swift Pay ("we," "our," or "us") is committed to protecting your privacy. This
                                    Privacy Policy explains how we collect, use, disclose, and safeguard your information
                                    when you use our payment and payroll automation platform. By using Swift Pay, you
                                    consent to the data practices described in this policy.
                                </p>
                            </div>

                            {/* Information We Collect */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <FileText className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Information We Collect
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-4 text-gray-700 dark:text-gray-300">
                                    <div>
                                        <h3 className="font-semibold text-gray-900 dark:text-white">
                                            Personal Information
                                        </h3>
                                        <p className="mt-2">
                                            We collect information that you provide directly to us, including:
                                        </p>
                                        <ul className="mt-2 ml-6 list-disc space-y-1">
                                            <li>Name, email address, and contact information</li>
                                            <li>Business name, registration number, and tax information</li>
                                            <li>Bank account details for escrow deposits</li>
                                            <li>Payment recipient and employee information</li>
                                            <li>Account credentials and authentication information</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-gray-900 dark:text-white">
                                            Usage Information
                                        </h3>
                                        <p className="mt-2">
                                            We automatically collect certain information when you use our services:
                                        </p>
                                        <ul className="mt-2 ml-6 list-disc space-y-1">
                                            <li>Device information and IP address</li>
                                            <li>Browser type and version</li>
                                            <li>Pages visited and time spent on our platform</li>
                                            <li>Transaction history and payment records</li>
                                            <li>Log files and error reports</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {/* How We Use Your Information */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Eye className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        How We Use Your Information
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>We use the information we collect to:</p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>
                                            Provide, maintain, and improve our payment and payroll automation services
                                        </li>
                                        <li>Process payments and manage escrow accounts</li>
                                        <li>Calculate taxes and generate compliance documents (UI-19, EMP201, IRP5)</li>
                                        <li>Send you transaction confirmations, receipts, and important updates</li>
                                        <li>Respond to your inquiries and provide customer support</li>
                                        <li>Detect, prevent, and address technical issues and security threats</li>
                                        <li>Comply with legal obligations and South African regulations</li>
                                        <li>Analyze usage patterns to improve our services</li>
                                    </ul>
                                </div>
                            </div>

                            {/* Data Security */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Shield className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Data Security</h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        We implement industry-standard security measures to protect your personal
                                        information:
                                    </p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>
                                            Encryption of data in transit and at rest using SSL/TLS protocols
                                        </li>
                                        <li>Secure authentication and access controls</li>
                                        <li>Regular security audits and vulnerability assessments</li>
                                        <li>Bank-controlled escrow accounts for financial transactions</li>
                                        <li>Compliance with South African data protection regulations</li>
                                        <li>Regular backups and disaster recovery procedures</li>
                                    </ul>
                                    <p className="mt-4">
                                        However, no method of transmission over the Internet or electronic storage is 100%
                                        secure. While we strive to use commercially acceptable means to protect your
                                        information, we cannot guarantee absolute security.
                                    </p>
                                </div>
                            </div>

                            {/* Data Sharing */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Lock className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Information Sharing and Disclosure
                                    </h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>We do not sell, trade, or rent your personal information to third parties.</p>
                                    <p>We may share your information only in the following circumstances:</p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>
                                            <strong>Service Providers:</strong> With trusted third-party service
                                            providers who assist us in operating our platform (e.g., payment processors,
                                            cloud hosting, email services)
                                        </li>
                                        <li>
                                            <strong>Legal Requirements:</strong> When required by law, court order, or
                                            government regulation, including compliance with SARS and other South
                                            African authorities
                                        </li>
                                        <li>
                                            <strong>Business Transfers:</strong> In connection with a merger,
                                            acquisition, or sale of assets, where your information may be transferred
                                            as part of the transaction
                                        </li>
                                        <li>
                                            <strong>With Your Consent:</strong> When you have explicitly authorized us
                                            to share your information
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            {/* Your Rights */}
                            <div>
                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Shield className="h-5 w-5 text-primary" />
                                    </div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Your Rights</h2>
                                </div>
                                <div className="mt-4 space-y-3 text-gray-700 dark:text-gray-300">
                                    <p>
                                        Under South African data protection laws, you have the following rights regarding
                                        your personal information:
                                    </p>
                                    <ul className="ml-6 list-disc space-y-2">
                                        <li>
                                            <strong>Access:</strong> Request access to your personal information we hold
                                        </li>
                                        <li>
                                            <strong>Correction:</strong> Request correction of inaccurate or incomplete
                                            information
                                        </li>
                                        <li>
                                            <strong>Deletion:</strong> Request deletion of your personal information,
                                            subject to legal and contractual obligations
                                        </li>
                                        <li>
                                            <strong>Objection:</strong> Object to processing of your personal information
                                            for certain purposes
                                        </li>
                                        <li>
                                            <strong>Data Portability:</strong> Request transfer of your data to another
                                            service provider
                                        </li>
                                        <li>
                                            <strong>Withdraw Consent:</strong> Withdraw consent for processing where
                                            consent is the legal basis
                                        </li>
                                    </ul>
                                    <p className="mt-4">
                                        To exercise these rights, please contact us at{' '}
                                        <a
                                            href="mailto:privacy@swiftpay.co.za"
                                            className="text-primary hover:underline"
                                        >
                                            privacy@swiftpay.co.za
                                        </a>
                                    </p>
                                </div>
                            </div>

                            {/* Data Retention */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Data Retention</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    We retain your personal information for as long as necessary to provide our services
                                    and comply with legal obligations. Financial and tax-related records are retained in
                                    accordance with South African tax and accounting regulations, typically for a
                                    minimum of 5 years. When you close your account, we will delete or anonymize your
                                    personal information, except where we are required to retain it for legal or
                                    regulatory purposes.
                                </p>
                            </div>

                            {/* Cookies */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Cookies and Tracking</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    We use cookies and similar tracking technologies to enhance your experience, analyze
                                    usage patterns, and improve our services. You can control cookie preferences through
                                    your browser settings, though disabling cookies may affect certain features of our
                                    platform.
                                </p>
                            </div>

                            {/* Children's Privacy */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Children's Privacy</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    Swift Pay is not intended for individuals under the age of 18. We do not knowingly
                                    collect personal information from children. If you believe we have inadvertently
                                    collected information from a child, please contact us immediately.
                                </p>
                            </div>

                            {/* Changes to Policy */}
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Changes to This Privacy Policy
                                </h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    We may update this Privacy Policy from time to time to reflect changes in our
                                    practices or legal requirements. We will notify you of any material changes by
                                    posting the new policy on this page and updating the "Last updated" date. Your
                                    continued use of Swift Pay after such changes constitutes acceptance of the updated
                                    policy.
                                </p>
                            </div>

                            {/* Contact */}
                            <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-6 dark:bg-primary/10">
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Contact Us</h2>
                                <p className="mt-4 text-gray-700 dark:text-gray-300">
                                    If you have questions or concerns about this Privacy Policy or our data practices,
                                    please contact us:
                                </p>
                                <div className="mt-4 space-y-2">
                                    <p className="text-gray-700 dark:text-gray-300">
                                        <strong>Email:</strong>{' '}
                                        <a
                                            href="mailto:privacy@swiftpay.co.za"
                                            className="text-primary hover:underline"
                                        >
                                            privacy@swiftpay.co.za
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
            </div>
        </>
    );
}
