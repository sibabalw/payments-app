import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { login, register } from '@/routes';

export default function Pricing() {
    return (
        <>
            <Head title="Pricing - Swift Pay" />
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

                <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                    <Link href="/" className="mb-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to home
                    </Link>

                    <div className="text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Simple, Transparent Pricing
                        </h1>
                        <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                            Deposit-based escrow model - We never hold your money
                        </p>
                    </div>

                    <div className="mt-12">
                        <div className="mb-8 rounded-lg bg-blue-50 p-6 dark:bg-blue-900/20">
                            <h2 className="mb-4 text-xl font-semibold text-gray-900 dark:text-white">
                                How It Works
                            </h2>
                            <ul className="space-y-2 text-gray-700 dark:text-gray-300">
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>Deposit funds into a bank-controlled escrow account</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>1.5% fee per deposit (not per transaction)</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>Your deposit authorizes usage up to the deposited amount minus 1.5%</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>We never store, touch, or hold money in the app</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>If we deliver execution → bank releases our fee</span>
                                </li>
                                <li className="flex items-start gap-2">
                                    <Check className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                    <span>If we fail → bank returns money to you</span>
                                </li>
                            </ul>
                        </div>

                        <div className="grid gap-8 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Small Business</CardTitle>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R1,000</span>
                                        <span className="text-gray-600 dark:text-gray-300">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Fixed monthly subscription
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
                                            <span>Unlimited receivers</span>
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
                                            <span>Email support</span>
                                        </li>
                                    </ul>
                                    <Link href={register()} className="mt-6 block">
                                        <Button className="w-full">Get Started</Button>
                                    </Link>
                                </CardContent>
                            </Card>

                            <Card className="border-primary">
                                <CardHeader>
                                    <CardTitle>Other Businesses</CardTitle>
                                    <div className="mt-4">
                                        <span className="text-4xl font-bold">R2,500</span>
                                        <span className="text-gray-600 dark:text-gray-300">/month</span>
                                    </div>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                        Fixed monthly subscription
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
                                            <span>Unlimited receivers</span>
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
                                            <span>Priority support</span>
                                        </li>
                                    </ul>
                                    <Link href={register()} className="mt-6 block">
                                        <Button className="w-full">Get Started</Button>
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
