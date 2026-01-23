import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Lock, Shield, TrendingUp, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { login, register } from '@/routes';
import { PublicNav } from '@/components/public-nav';

export default function Pricing() {
    return (
        <>
            <Head title="Pricing - Swift Pay" />
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
                                Simple, Transparent Pricing
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-gray-300">
                                Deposit-based escrow model—we never hold your money. Transparent fees, no hidden
                                charges, complete control.
                            </p>
                        </div>
                    </div>
                </section>

                {/* How It Works Section */}
                <section className="py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="rounded-lg border-2 border-primary/20 bg-primary/5 p-8 dark:bg-primary/10">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                    <Shield className="h-6 w-6 text-primary" />
                                </div>
                                <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                    How Our Escrow Model Works
                                </h2>
                            </div>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div className="space-y-4">
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Bank-Controlled Security
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                Deposit funds into a bank-controlled escrow account. We never store,
                                                touch, or hold money in the app.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Transparent Fee Structure
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                1.5% fee per deposit (not per transaction). Your deposit authorizes
                                                usage up to the deposited amount minus 1.5%.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Risk-Free Execution
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
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
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Complete Transparency
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                View all transactions, fees, and balances in real-time. No hidden
                                                charges, no surprises.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Low Balance Alerts
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                Automatic notifications when your escrow balance is running low, so you
                                                never miss a payment.
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                        <div>
                                            <span className="font-semibold text-gray-900 dark:text-white">
                                                Full Control
                                            </span>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                Deposit when you need to, track everything in real-time, and maintain
                                                complete visibility over your funds.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Pricing Cards */}
                <section className="py-12">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-8 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Small Business</CardTitle>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R1,000</span>
                                        <span className="text-gray-600 dark:text-gray-300">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
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

                            <Card className="border-primary shadow-lg">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Enterprise</CardTitle>
                                        <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                                            Most Popular
                                        </span>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R2,500</span>
                                        <span className="text-gray-600 dark:text-gray-300">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
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
                    </div>
                </section>

                {/* Value Propositions */}
                <section className="bg-gray-50 py-16 dark:bg-gray-900/50">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                Why Choose Swift Pay?
                            </h2>
                            <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                                More than just pricing—we deliver value that transforms your business
                            </p>
                        </div>
                        <div className="mt-12 grid grid-cols-1 gap-8 md:grid-cols-3">
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <Zap className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Save Time & Money</h3>
                                <p className="mt-4 text-gray-600 dark:text-gray-300">
                                    Eliminate manual calculations, reduce errors, and free up your team to focus on
                                    what matters most.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <Lock className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Bank-Level Security</h3>
                                <p className="mt-4 text-gray-600 dark:text-gray-300">
                                    Your funds are protected in bank-controlled escrow. We never hold your money—you're
                                    always in control.
                                </p>
                            </div>
                            <div className="text-center">
                                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <TrendingUp className="h-8 w-8 text-primary" />
                                </div>
                                <h3 className="mt-6 text-xl font-semibold">Grow with Confidence</h3>
                                <p className="mt-4 text-gray-600 dark:text-gray-300">
                                    Scale your business without worrying about payment processing. We handle the
                                    complexity so you don't have to.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* FAQ Section */}
                <section className="py-16">
                    <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 className="text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Frequently Asked Questions
                        </h2>
                        <div className="mt-12 space-y-8">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    How does the escrow model work?
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    You deposit funds into a bank-controlled escrow account. We charge a 1.5% fee per
                                    deposit. Your deposit authorizes usage up to the deposited amount minus the fee. We
                                    never hold your money in the app—everything flows through regulated escrow accounts.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    What happens if a payment fails?
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    If we fail to execute a payment, the bank automatically returns the funds to you. You
                                    only pay our fee when we successfully deliver execution. This ensures you're never
                                    charged for failed transactions.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Are there any hidden fees?
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    No. Our pricing is completely transparent. You pay a fixed monthly subscription plus
                                    a 1.5% fee per deposit. There are no per-transaction fees, no setup fees, and no
                                    hidden charges.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Can I cancel anytime?
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Yes, you can cancel your subscription at any time. Your escrow balance remains yours,
                                    and you can withdraw it at any time. No long-term contracts or cancellation fees.
                                </p>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                    Do you offer a free trial?
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-300">
                                    Yes! Start with a 14-day free trial. No credit card required. Explore all features
                                    and see how Swift Pay can transform your business operations.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="bg-primary py-16">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                Ready to Get Started?
                            </h2>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-primary-foreground/90">
                                Join South African businesses that trust Swift Pay. Start your free trial today.
                            </p>
                            <div className="mt-8">
                                <Link href={register()}>
                                    <Button size="lg" variant="secondary">
                                        Start Free Trial
                                    </Button>
                                </Link>
                            </div>
                            <p className="mt-4 text-sm text-primary-foreground/80">
                                No credit card required • 14-day free trial • Cancel anytime
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
