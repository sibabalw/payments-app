import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { PublicSectionInner } from '@/components/public-section';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/react';
import { Check, Lock, Shield, TrendingUp, Zap } from 'lucide-react';
import { login, register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

export default function Pricing() {
    return (
        <>
            <Head title="Pricing - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="Simple, Transparent Pricing"
                    description="SwiftPay uses a simple, deposit-based escrow model—no per-transaction fees, no hidden costs. We never hold your money; transparent fees, complete control."
                />

                {/* What is SwiftPay? */}
                <AnimatedSection className="py-8">
                    <PublicSectionInner>
                        <div className="mx-auto max-w-3xl text-center">
                            <h2 className="text-2xl font-bold tracking-tight text-foreground">
                                What is SwiftPay?
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                SwiftPay is a payment and payroll automation platform for South African businesses.
                                Your funds stay secure in bank-controlled escrow while we handle full tax compliance
                                (PAYE, UIF, SDL), time tracking, and employee self-service—so you can focus on
                                running your business.
                            </p>
                            <ul className="mt-6 space-y-2 text-left text-muted-foreground sm:mx-auto sm:max-w-md sm:text-center">
                                <li className="flex items-start gap-2 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>Built for South African businesses, from startups to enterprises</span>
                                </li>
                                <li className="flex items-start gap-2 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>One platform for payment scheduling, payroll, and SARS-ready compliance</span>
                                </li>
                                <li className="flex items-start gap-2 sm:justify-center">
                                    <Check className="mt-1 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>Bank-controlled escrow—we never hold your money</span>
                                </li>
                            </ul>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* How It Works Section */}
                <AnimatedSection className="py-12">
                    <PublicSectionInner>
                        <PublicCard variant="glass" className="border-2 border-primary/20 bg-primary/5 p-8 dark:bg-primary/10">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <h2 className="font-display text-2xl font-bold text-foreground">
                                    How Our Escrow Model Works
                                </h2>
                            </div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Bank-Controlled Security
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Deposit funds into a bank-controlled escrow account. We never store,
                                                touch, or hold money in the app.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Transparent Fee Structure
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                1.5% fee per deposit (not per transaction). Your deposit authorizes
                                                usage up to the deposited amount minus 1.5%.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Risk-Free Execution
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                If we deliver execution → bank releases our fee. If we fail → bank
                                                returns money to you.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Complete Transparency
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                View all transactions, fees, and balances in real-time. No hidden
                                                charges, no surprises.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Low Balance Alerts
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Automatic notifications when your escrow balance is running low, so you
                                                never miss a payment.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-foreground">
                                                Full Control
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Deposit when you need to, track everything in real-time, and maintain
                                                complete visibility over your funds.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </PublicCard>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Pricing Cards */}
                <AnimatedSection className="py-12">
                    <PublicSectionInner>
                        <div className="grid gap-8 lg:grid-cols-2">
                            <Card className="transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <CardHeader>
                                    <CardTitle>Small Business</CardTitle>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R1,000</span>
                                        <span className="text-muted-foreground">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Perfect for startups and small teams
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-4">
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Unlimited payment schedules</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Unlimited receivers and employees</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Full payroll automation with SA tax compliance</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>1.5% deposit processing fee</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Bank-controlled escrow</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Time & attendance tracking</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Tax compliance (UI-19, EMP201, IRP5)</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Email support</span>
                                        </li>
                                    </ul>
                                    <Link href={register()} className="mt-6 block">
                                        <Button className="w-full">Get Started</Button>
                                    </Link>
                                </CardContent>
                            </Card>

                            <Card className="border-primary shadow-lg transition-[box-shadow,transform] duration-200 hover:-translate-y-1 hover:shadow-md">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Enterprise</CardTitle>
                                        <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                            Most Popular
                                        </span>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R2,500</span>
                                        <span className="text-muted-foreground">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        For growing businesses and larger teams
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    <ul className="space-y-4">
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Everything in Small Business</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Multi-business management</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Advanced reporting and analytics</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Custom email templates</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Priority support</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Dedicated account manager</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>API access (coming soon)</span>
                                        </li>
                                        <li className="flex items-center gap-2">
                                            <Check className="h-5 w-5 text-primary" />
                                            <span>Custom integrations</span>
                                        </li>
                                    </ul>
                                    <Link href={register()} className="mt-6 block">
                                        <Button className="w-full">Get Started</Button>
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* Value Propositions */}
                <AnimatedSection className="bg-muted/50 py-16">
                    <PublicSectionInner>
                        <div className="text-center">
                            <h2 className="font-display text-3xl font-bold tracking-tight text-foreground">
                                Why Choose SwiftPay?
                            </h2>
                            <p className="mt-4 text-lg text-muted-foreground">
                                More than just pricing—we deliver value that transforms your business
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-8 md:grid-cols-3">
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <Zap className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Save Time & Money</h3>
                                <p className="mt-4 text-muted-foreground">
                                    Eliminate manual calculations, reduce errors, and free up your team to focus on
                                    what matters most.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <Lock className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Bank-Level Security</h3>
                                <p className="mt-4 text-muted-foreground">
                                    Your funds are protected in bank-controlled escrow. We never hold your money—you're
                                    always in control.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <TrendingUp className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Grow with Confidence</h3>
                                <p className="mt-4 text-muted-foreground">
                                    Scale your business without worrying about payment processing. We handle the
                                    complexity so you don't have to.
                                </p>
                            </div>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* FAQ Section */}
                <AnimatedSection className="py-16">
                    <PublicSectionInner narrow>
                        <h2 className="text-center font-display text-3xl font-bold tracking-tight text-foreground">
                            Frequently Asked Questions
                        </h2>
                        <div className="mt-12 space-y-8">
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    How does the escrow model work?
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    You deposit funds into a bank-controlled escrow account. We charge a 1.5% fee per
                                    deposit. Your deposit authorizes usage up to the deposited amount minus the fee. We
                                    never hold your money in the app—everything flows through regulated escrow accounts.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    What happens if a payment fails?
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    If we fail to execute a payment, the bank automatically returns the funds to you. You
                                    only pay our fee when we successfully deliver execution. This ensures you're never
                                    charged for failed transactions.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    Are there any hidden fees?
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    No. Our pricing is completely transparent. You pay a fixed monthly subscription plus
                                    a 1.5% fee per deposit. There are no per-transaction fees, no setup fees, and no
                                    hidden charges.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    Can I cancel anytime?
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    Yes, you can cancel your subscription at any time. Your escrow balance remains yours,
                                    and you can withdraw it at any time. No long-term contracts or cancellation fees.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">
                                    Do you offer a free trial?
                                </h3>
                                <p className="mt-2 text-muted-foreground">
                                    Yes! Start with a 14-day free trial. No credit card required. Explore all features
                                    and see how SwiftPay can transform your business operations.
                                </p>
                            </div>
                        </div>
                    </PublicSectionInner>
                </AnimatedSection>

                {/* CTA Section */}
                <AnimatedSection>
                    <PublicCtaBand
                        title="Ready to Get Started?"
                        description="Join South African businesses that trust SwiftPay. Start your free trial today."
                    >
                        <Link href={register()}>
                            <Button variant="gradient" size="lg">
                                Start Free Trial
                            </Button>
                        </Link>
                        <p className="text-sm text-neutral-400">
                            No credit card required • 14-day free trial • Cancel anytime
                        </p>
                    </PublicCtaBand>
                </AnimatedSection>
                <PublicFooter />
            </div>
        </>
    );
}
