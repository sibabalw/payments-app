import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CreditCard, Receipt } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing', href: '/billing' },
];

interface BillingIndexProps {
    businesses: Array<{ id: number; name: string }>;
    selectedBusinessId?: number;
    business?: {
        id: number;
        name: string;
        business_type: string;
    };
    currentMonthBilling?: {
        id: number;
        billing_month: string;
        business_type: string;
        subscription_fee: string;
        total_deposit_fees: string;
        status: string;
    };
    currentMonthDepositFees: number;
    subscriptionFee: number;
    billingHistory: Array<{
        id: number;
        billing_month: string;
        subscription_fee: string;
        total_deposit_fees: string;
        status: string;
        billed_at: string;
        paid_at: string | null;
    }>;
}

export default function BillingIndex({
    businesses,
    selectedBusinessId,
    business,
    currentMonthBilling,
    currentMonthDepositFees,
    subscriptionFee,
    billingHistory,
}: BillingIndexProps) {
    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    const formatMonth = (month: string) => {
        const [year, monthNum] = month.split('-');
        const date = new Date(Number(year), Number(monthNum) - 1);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Billing Dashboard</h1>

                {business ? (
                    <>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Monthly Subscription
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold">{formatCurrency(subscriptionFee)}</p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {business.business_type === 'small_business' ? 'Small Business' : 'Other Business'}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Deposit Fees (This Month)</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold">{formatCurrency(currentMonthDepositFees)}</p>
                                    <p className="text-sm text-muted-foreground mt-1">1.5% per deposit</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Total This Month</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-3xl font-bold">
                                        {formatCurrency(subscriptionFee + currentMonthDepositFees)}
                                    </p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        Subscription + Deposit Fees
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {currentMonthBilling && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Receipt className="h-5 w-5" />
                                        Current Month Billing ({formatMonth(currentMonthBilling.billing_month)})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Subscription Fee:</span>
                                            <span className="font-semibold">{formatCurrency(currentMonthBilling.subscription_fee)}</span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Deposit Fees:</span>
                                            <span className="font-semibold">{formatCurrency(currentMonthBilling.total_deposit_fees)}</span>
                                        </div>
                                        <div className="border-t pt-4 flex items-center justify-between">
                                            <span className="font-semibold">Total:</span>
                                            <span className="text-2xl font-bold">
                                                {formatCurrency(
                                                    Number(currentMonthBilling.subscription_fee) +
                                                    Number(currentMonthBilling.total_deposit_fees)
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-muted-foreground">Status:</span>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                    currentMonthBilling.status === 'paid'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : currentMonthBilling.status === 'pending'
                                                          ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                          : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                                }`}
                                            >
                                                {currentMonthBilling.status}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Billing History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {billingHistory.length > 0 ? (
                                    <div className="space-y-4">
                                        {billingHistory.map((billing) => (
                                            <div key={billing.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                                <div>
                                                    <p className="font-semibold">{formatMonth(billing.billing_month)}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Subscription: {formatCurrency(billing.subscription_fee)} • 
                                                        Deposit Fees: {formatCurrency(billing.total_deposit_fees)}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        Billed: {new Date(billing.billed_at).toLocaleDateString()}
                                                        {billing.paid_at && ` • Paid: ${new Date(billing.paid_at).toLocaleDateString()}`}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-lg font-bold">
                                                        {formatCurrency(
                                                            Number(billing.subscription_fee) + Number(billing.total_deposit_fees)
                                                        )}
                                                    </p>
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium mt-2 ${
                                                            billing.status === 'paid'
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                : billing.status === 'pending'
                                                                  ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                                  : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                                        }`}
                                                    >
                                                        {billing.status}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-center text-muted-foreground py-8">No billing history yet</p>
                                )}
                            </CardContent>
                        </Card>
                    </>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No business selected. Please select a business first.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
