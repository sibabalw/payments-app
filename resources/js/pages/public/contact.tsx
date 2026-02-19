import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { PublicSectionInner } from '@/components/public-section';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { trackEvent } from '@/lib/umami';
import { Mail, MessageSquare, Clock, ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { login, register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

export default function Contact() {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        name: '',
        email: '',
        message: '',
    });

    useEffect(() => {
        if (wasSuccessful) {
            trackEvent('Contact form submitted', { page: 'contact' });
            reset();
        }
    }, [wasSuccessful, reset]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/contact');
    };

    return (
        <>
            <Head title="Contact - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="Get in Touch"
                    description="Have questions? We'd love to hear from you. Our team is here to help you get started with SwiftPay. Whether you need help with onboarding, features, or support, we're here for you."
                />

                {/* Contact Section */}
                <AnimatedSection className="py-16">
                    <PublicSectionInner>
                        <div className="grid gap-x-8 gap-y-6 lg:grid-cols-2">
                            {/* Row 1: Headings - aligned */}
                            <div>
                                <h2 className="font-display text-2xl font-bold text-foreground">
                                    Let's Start a Conversation
                                </h2>
                                <p className="mt-2 text-muted-foreground">
                                    Whether you're looking to get started, have questions about our features, or need
                                    support, we're here to help. Reach out and we'll get back to you as soon as
                                    possible.
                                </p>
                            </div>
                            <div>
                                <h2 className="font-display text-2xl font-bold text-foreground">
                                    Send Us a Message
                                </h2>
                                <p className="mt-2 text-muted-foreground">
                                    Fill out the form below and we'll get back to you as soon as possible.
                                </p>
                            </div>
                            {/* Row 2: Cards - aligned */}
                            <PublicCard variant="elevated" className="p-6">
                                    <div className="pt-0">
                                        <div className="space-y-6">
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <Mail className="h-6 w-6 text-primary" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-foreground">
                                                        Email Us
                                                    </h3>
                                                    <p className="mt-1 text-sm text-muted-foreground">
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
                                                    <h3 className="font-semibold text-foreground">
                                                        Support Hours
                                                    </h3>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Monday - Friday: 8:00 AM - 6:00 PM SAST
                                                    </p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Saturday: 9:00 AM - 1:00 PM SAST
                                                    </p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Closed on Sundays and public holidays
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                    <MessageSquare className="h-6 w-6 text-primary" />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-foreground">
                                                        Response Time
                                                    </h3>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        We typically respond within 24 hours during business days
                                                    </p>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        Priority support available for Enterprise customers
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </PublicCard>

                            {/* Form card - same row as contact info card */}
                            <PublicCard variant="elevated" className="p-6">
                                    {flash?.success && (
                                        <div className="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">
                                            {flash.success}
                                        </div>
                                    )}
                                    <form onSubmit={submit} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="name">Name</Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                required
                                                placeholder="Your full name"
                                            />
                                            <InputError message={errors.name} className="mt-1" />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                required
                                                placeholder="your.email@example.com"
                                            />
                                            <InputError message={errors.email} className="mt-1" />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="message">Message</Label>
                                            <Textarea
                                                id="message"
                                                className="min-h-[120px] resize-none"
                                                value={data.message}
                                                onChange={(e) => setData('message', e.target.value)}
                                                required
                                                placeholder="Tell us how we can help..."
                                            />
                                            <InputError message={errors.message} className="mt-1" />
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing ? 'Sending...' : 'Send Message'}
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            By submitting this form, you agree to our Privacy Policy. We'll never share
                                            your information with third parties.
                                        </p>
                                    </form>
                            </PublicCard>

                            {/* CTA - spans full width */}
                            <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-6 dark:bg-primary/10 lg:col-span-2">
                                <h3 className="font-semibold text-foreground">
                                    Ready to Get Started?
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Don't wait—start your free trial today. No credit card required, and you can
                                    explore all features risk-free.
                                </p>
                                <Link
                                    href={register()}
                                    className="mt-4 block"
                                    onClick={() => trackEvent('Start Free Trial clicked', { page: 'contact' })}
                                >
                                    <Button className="w-full sm:w-auto">
                                        Start Free Trial
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* FAQ Quick Links */}
                <AnimatedSection className="bg-muted/50 py-16">
                    <PublicSectionInner>
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-foreground">
                                Common Questions
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                Find quick answers to frequently asked questions
                            </p>
                        </div>
                        <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            <Card className="flex flex-col">
                                <CardContent className="flex flex-1 flex-col pt-6">
                                    <h3 className="font-semibold">Getting Started</h3>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Learn how to set up your account, create your first payment schedule, and start
                                        automating your payments.
                                    </p>
                                    <Link
                                        href="/features"
                                        className="mt-auto pt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        View Features
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
                            <Card className="flex flex-col">
                                <CardContent className="flex flex-1 flex-col pt-6">
                                    <h3 className="font-semibold">Pricing & Plans</h3>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Understand our transparent pricing model, escrow system, and find the plan that
                                        fits your business.
                                    </p>
                                    <Link
                                        href="/pricing"
                                        className="mt-auto pt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        View Pricing
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
                            <Card className="flex flex-col">
                                <CardContent className="flex flex-1 flex-col pt-6">
                                    <h3 className="font-semibold">Tax Compliance</h3>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Discover how SwiftPay handles PAYE, UIF, SDL, and generates SARS-compliant
                                        documents automatically.
                                    </p>
                                    <Link
                                        href="/features"
                                        className="mt-auto pt-4 inline-flex items-center text-sm text-primary hover:underline"
                                    >
                                        Learn More
                                        <ArrowRight className="ml-1 h-4 w-4" />
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* CTA Section */}
                <AnimatedSection>
                    <PublicCtaBand
                        title="Ready to Transform Your Business?"
                        description="Join South African businesses that trust SwiftPay. Start your free trial today—no credit card required."
                    >
                        <Link
                            href={register()}
                            onClick={() => trackEvent('Start Free Trial clicked', { page: 'contact_cta_band' })}
                        >
                            <Button variant="gradient" size="lg">
                                Start Free Trial
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Button>
                        </Link>
                        <p className="text-sm text-neutral-400">
                            14-day free trial • No credit card required • Cancel anytime
                        </p>
                    </PublicCtaBand>
                </AnimatedSection>
                <PublicFooter />
            </div>
        </>
    );
}
