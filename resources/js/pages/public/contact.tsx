import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail, MessageSquare, Phone, Clock, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';
import { useForm } from '@inertiajs/react';
import { PublicNav } from '@/components/public-nav';

export default function Contact() {
    const { data, setData, post, processing } = useForm({
        name: '',
        email: '',
        message: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // In a real app, this would send to a contact form handler
        alert('Thank you for your message! We will get back to you soon.');
    };

    return (
        <>
            <Head title="Contact - Swift Pay" />
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
                                Get in Touch
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Have questions? We'd love to hear from you. Our team is here to help you get started
                                with Swift Pay.
                            </p>
                        </div>
                    </div>
                </section>

                {/* Contact Section */}
                <section className="py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-8 lg:grid-cols-2">
                            {/* Contact Information */}
                            <div className="space-y-6">
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Let's Start a Conversation
                                    </h2>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300">
                                        Whether you're looking to get started, have questions about our features, or need
                                        support, we're here to help. Reach out and we'll get back to you as soon as
                                        possible.
                                    </p>
                                </div>

                                <Card>
                                    <CardContent className="pt-6">
                                        <div className="space-y-6">
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <Mail className="h-6 w-6 text-primary" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-gray-900 dark:text-white">
                                                        Email Us
                                                    </h3>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                        For general inquiries and support
                                                    </p>
                                                    <a
                                                        href="mailto:support@swiftpay.co.za"
                                                        className="mt-2 block text-primary hover:underline"
                                                    >
                                                        support@swiftpay.co.za
                                                    </a>
                                                </div>
                                            </div>
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <Clock className="h-6 w-6 text-primary" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-gray-900 dark:text-white">
                                                        Support Hours
                                                    </h3>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                        Monday - Friday: 8:00 AM - 6:00 PM SAST
                                                    </p>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                        Saturday: 9:00 AM - 1:00 PM SAST
                                                    </p>
                                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        Closed on Sundays and public holidays
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <MessageSquare className="h-6 w-6 text-primary" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-gray-900 dark:text-white">
                                                        Response Time
                                                    </h3>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                        We typically respond within 24 hours during business days
                                                    </p>
                                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                        Priority support available for Enterprise customers
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-6 dark:bg-primary/10">
                                    <h3 className="font-semibold text-gray-900 dark:text-white">
                                        Ready to Get Started?
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Don't wait—start your free trial today. No credit card required, and you can
                                        explore all features risk-free.
                                    </p>
                                    <Link href={register()} className="mt-4 block">
                                        <Button className="w-full sm:w-auto">
                                            Start Free Trial
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            {/* Contact Form */}
                            <Card>
                                <CardContent className="pt-6">
                                    <h2 className="mb-6 text-xl font-semibold text-gray-900 dark:text-white">
                                        Send Us a Message
                                    </h2>
                                    <form onSubmit={submit} className="space-y-4">
                                        <div>
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                required
                                                placeholder="Your full name"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                required
                                                placeholder="your.email@example.com"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="message">Message</Label>
                                            <textarea
                                                id="message"
                                                className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                                value={data.message}
                                                onChange={(e) => setData('message', e.target.value)}
                                                required
                                                placeholder="Tell us how we can help..."
                                            />
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing ? 'Sending...' : 'Send Message'}
                                        </Button>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            By submitting this form, you agree to our Privacy Policy. We'll never share
                                            your information with third parties.
                                        </p>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>

                {/* FAQ Quick Links */}
                <section className="bg-gray-50 py-16 dark:bg-gray-900/50">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Common Questions
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                Find quick answers to frequently asked questions
                            </p>
                        </div>
                        <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardContent className="pt-6">
                                    <h3 className="font-semibold">Getting Started</h3>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Learn how to set up your account, create your first payment schedule, and start
                                        automating your payments.
                                    </p>
                                    <Link
                                        href="/features"
                                        className="mt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        View Features
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <h3 className="font-semibold">Pricing & Plans</h3>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Understand our transparent pricing model, escrow system, and find the plan that
                                        fits your business.
                                    </p>
                                    <Link
                                        href="/pricing"
                                        className="mt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        View Pricing
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="pt-6">
                                    <h3 className="font-semibold">Tax Compliance</h3>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Discover how Swift Pay handles PAYE, UIF, SDL, and generates SARS-compliant
                                        documents automatically.
                                    </p>
                                    <Link
                                        href="/features"
                                        className="mt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        Learn More
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
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
                                Join South African businesses that trust Swift Pay. Start your free trial today—no
                                credit card required.
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
                                14-day free trial • No credit card required • Cancel anytime
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
