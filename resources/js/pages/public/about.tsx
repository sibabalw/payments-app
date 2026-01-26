import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, Check, Heart, Target, Users, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { PublicNav } from '@/components/public-nav';

export default function About() {
    return (
        <>
            <Head title="About - Swift Pay" />
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
                                About Swift Pay
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Empowering South African businesses with automated payment and payroll solutions built
                                for local needs.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Mission & Vision */}
                <section className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-12 lg:grid-cols-2">
                            <div>
                                <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                    <Target className="h-7 w-7 text-primary" />
                                </div>
                                <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                    Our Mission
                                </h2>
                                <p className="mt-6 text-lg text-gray-600 dark:text-gray-300">
                                    To simplify payment automation and payroll management for South African businesses of
                                    all sizes. We believe that every business, from startups to enterprises, deserves
                                    access to powerful, compliant, and affordable payment solutions.
                                </p>
                                <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                    Our mission is to eliminate the complexity, errors, and time-consuming manual work
                                    associated with payment processing and payroll management, allowing business owners
                                    to focus on what they do best—growing their business.
                                </p>
                            </div>
                            <div>
                                <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-7 w-7 text-primary" />
                                </div>
                                <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                    Our Vision
                                </h2>
                                <p className="mt-6 text-lg text-gray-600 dark:text-gray-300">
                                    To become the leading payment and payroll automation platform in South Africa, trusted
                                    by thousands of businesses for their critical financial operations.
                                </p>
                                <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                    We envision a future where every South African business can automate their payments
                                    and payroll with confidence, knowing that their operations are compliant, secure, and
                                    efficient. We're building the infrastructure that enables businesses to scale without
                                    the burden of manual financial management.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Why Swift Pay */}
                <section className="bg-gray-50 py-16 dark:bg-gray-900/50">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Why Swift Pay?
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                We understand the unique challenges facing South African businesses
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Building2 className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Built for South Africa</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Every feature is designed with South African tax regulations, banking systems, and
                                    business practices in mind. No compromises, no workarounds.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Check className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Full Tax Compliance</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Automatic PAYE, UIF, and SDL calculations. Generate UI-19, EMP201, and IRP5
                                    certificates without manual work. Stay compliant effortlessly.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Heart className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Business-First Approach</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    We're not just a software company—we're your partner in growth. Every decision we
                                    make prioritizes your business success.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Users className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Transparent & Trustworthy</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Bank-controlled escrow means we never hold your money. Complete transparency in
                                    pricing, fees, and operations. Trust built on transparency.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Zap className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Zero Manual Work</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Set it and forget it. Our platform handles all calculations, scheduling, and
                                    compliance automatically. Free your team from repetitive tasks.
                                </p>
                            </div>
                            <div className="rounded-lg border bg-card p-6">
                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Target className="h-6 w-6 text-primary" />
                                </div>
                                <h3 className="text-xl font-semibold">Scalable Solutions</h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Whether you're a startup or an enterprise, our platform scales with you. Multi-business
                                    support and flexible pricing for every stage of growth.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Built for South African Businesses */}
                <section className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-8 dark:bg-primary/10">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Building2 className="h-6 w-6 text-primary" />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    Built for South African Businesses
                                </h2>
                            </div>
                            <p className="text-lg text-gray-700 dark:text-gray-300">
                                We understand the unique challenges of running a business in South Africa. That's why
                                Swift Pay is built from the ground up with local needs in mind:
                            </p>
                            <div className="mt-6 grid gap-4 md:grid-cols-2">
                                <div className="flex items-start gap-3">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white">
                                            Full SARS Compliance
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            Automatic tax bracket calculations, UI-19, EMP201, and IRP5 generation
                                            compliant with SARS requirements
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white">
                                            South African Banking
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            Integration with South African banking systems and escrow account support
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white">
                                            Local Currency Support
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            ZAR currency support and South African-specific reporting formats
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <div>
                                        <span className="font-semibold text-gray-900 dark:text-white">
                                            Labor Law Compliance
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                            Compliance with South African labor laws, UIF regulations, and employment
                                            standards
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Our Story */}
                <section className="bg-gray-50 py-16 dark:bg-gray-900/50">
                    <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                        <h2 className="text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Our Story
                        </h2>
                        <div className="mt-8 space-y-6 text-lg text-gray-600 dark:text-gray-300">
                            <p>
                                Swift Pay was born from a simple observation: South African businesses were spending too
                                much time and money on manual payment processing and payroll management. Spreadsheets,
                                manual calculations, and compliance headaches were holding businesses back.
                            </p>
                            <p>
                                We set out to build a platform that would eliminate these pain points while maintaining
                                the highest standards of security, compliance, and transparency. The result is Swift Pay
                                —a comprehensive solution that automates payments, processes payroll, and handles tax
                                compliance, all while keeping your funds secure in bank-controlled escrow.
                            </p>
                            <p>
                                Today, Swift Pay serves businesses across South Africa, from small startups to growing
                                enterprises. We're committed to continuous improvement, listening to our customers, and
                                building features that truly matter to South African businesses.
                            </p>
                            <p>
                                Our team combines deep expertise in financial technology, South African tax regulations,
                                and business operations. We're not just developers—we're business people who understand
                                your challenges because we've faced them too.
                            </p>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="bg-primary py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                Join Us on This Journey
                            </h2>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                                Experience the difference that a platform built specifically for South African businesses
                                can make. Start your free trial today.
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
