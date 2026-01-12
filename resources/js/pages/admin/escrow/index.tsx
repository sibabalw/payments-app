import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle, XCircle, Plus, Wallet } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Escrow Management', href: '/admin/escrow' },
];

interface AdminEscrowProps {
    pendingDeposits: Array<{
        id: number;
        business_id: number;
        amount: string;
        fee_amount: string;
        authorized_amount: string;
        currency: string;
        status: string;
        entry_method: string;
        bank_reference: string | null;
        deposited_at: string;
        business: { id: number; name: string };
        entered_by: { id: number; name: string } | null;
    }>;
    confirmedDeposits: Array<{
        id: number;
        business_id: number;
        amount: string;
        fee_amount: string;
        authorized_amount: string;
        currency: string;
        status: string;
        entry_method: string;
        bank_reference: string | null;
        completed_at: string;
        business: { id: number; name: string };
        entered_by: { id: number; name: string } | null;
    }>;
    businesses: Array<{ id: number; name: string; balance: number }>;
    escrowAccountNumber: string;
    succeededPayments: Array<{
        id: number;
        amount: string;
        currency: string;
        status: string;
        processed_at: string;
        fee_released_manually_at: string | null;
        payment_schedule: { name: string; business: { name: string } };
        receiver: { name: string };
        escrow_deposit: { fee_amount: string } | null;
    }>;
    failedPayments: Array<{
        id: number;
        amount: string;
        currency: string;
        status: string;
        processed_at: string;
        funds_returned_manually_at: string | null;
        payment_schedule: { name: string; business: { name: string } };
        receiver: { name: string };
    }>;
}

export default function AdminEscrow({ pendingDeposits, confirmedDeposits, businesses, escrowAccountNumber, succeededPayments, failedPayments }: AdminEscrowProps) {
    const { data, setData, post, processing, errors } = useForm({
        business_id: '',
        amount: '',
        currency: 'ZAR',
        bank_reference: '',
    });

    const submitDeposit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/escrow/deposits');
    };

    const confirmDeposit = (depositId: number, bankRef?: string) => {
        router.post(`/admin/escrow/deposits/${depositId}/confirm`, {
            bank_reference: bankRef || '',
        });
    };

    const recordFeeRelease = (paymentJobId: number) => {
        if (confirm('Record that bank has released the fee for this payment?')) {
            router.post(`/admin/escrow/payments/${paymentJobId}/fee-release`);
        }
    };

    const recordFundReturn = (paymentJobId: number) => {
        if (confirm('Record that bank has returned funds for this failed payment?')) {
            router.post(`/admin/escrow/payments/${paymentJobId}/fund-return`);
        }
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Escrow Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Escrow Management</h1>
                    <Link href="/admin/escrow/balances">
                        <Button variant="outline">View All Balances</Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Wallet className="h-5 w-5" />
                                Platform Escrow Account
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm font-mono">{escrowAccountNumber || 'Not configured'}</p>
                            <p className="text-xs text-muted-foreground mt-2">All businesses deposit into this account</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Pending Deposits</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{pendingDeposits.length}</p>
                            <p className="text-sm text-muted-foreground">Awaiting bank confirmation</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total Businesses</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{businesses.length}</p>
                            <p className="text-sm text-muted-foreground">With escrow deposits</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Manually Record Deposit</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitDeposit} className="space-y-4">
                                <div>
                                    <Label htmlFor="business_id">Business</Label>
                                    <Select
                                        value={String(data.business_id)}
                                        onValueChange={(value) => setData('business_id', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select business" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {businesses.map((business) => (
                                                <SelectItem key={business.id} value={String(business.id)}>
                                                    {business.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.business_id} />
                                </div>

                                <div>
                                    <Label htmlFor="amount">Deposit Amount</Label>
                                    <Input
                                        id="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.amount} />
                                </div>

                                <div>
                                    <Label htmlFor="bank_reference">Bank Reference (Optional)</Label>
                                    <Input
                                        id="bank_reference"
                                        value={data.bank_reference}
                                        onChange={(e) => setData('bank_reference', e.target.value)}
                                    />
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Record Deposit
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Business Balances</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2 max-h-64 overflow-y-auto">
                                {businesses.map((business) => (
                                    <div key={business.id} className="flex items-center justify-between border-b pb-2">
                                        <span className="text-sm">{business.name}</span>
                                        <span className="font-semibold">{formatCurrency(business.balance)}</span>
                                    </div>
                                ))}
                                {businesses.length === 0 && (
                                    <p className="text-sm text-muted-foreground text-center py-4">No businesses yet</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pending Deposits (Awaiting Confirmation)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pendingDeposits.length > 0 ? (
                            <div className="space-y-4">
                                {pendingDeposits.map((deposit) => (
                                    <div key={deposit.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                        <div className="flex-1">
                                            <p className="font-semibold">{deposit.business.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatCurrency(deposit.amount)} • Fee: {formatCurrency(deposit.fee_amount)} • 
                                                Authorized: {formatCurrency(deposit.authorized_amount)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Recorded: {new Date(deposit.deposited_at).toLocaleString()}
                                                {deposit.entered_by && ` by ${deposit.entered_by.name}`}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() => confirmDeposit(deposit.id)}
                                                variant="outline"
                                            >
                                                <CheckCircle className="mr-2 h-4 w-4" />
                                                Confirm
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No pending deposits</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Confirmed Deposits</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {confirmedDeposits.length > 0 ? (
                            <div className="space-y-4">
                                {confirmedDeposits.map((deposit) => (
                                    <div key={deposit.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                        <div>
                                            <p className="font-semibold">{deposit.business.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatCurrency(deposit.amount)} • Authorized: {formatCurrency(deposit.authorized_amount)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Confirmed: {new Date(deposit.completed_at).toLocaleString()}
                                                {deposit.bank_reference && ` • Ref: ${deposit.bank_reference}`}
                                            </p>
                                        </div>
                                        <span className="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Confirmed
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No confirmed deposits yet</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment Operations - Fee Releases</CardTitle>
                        <p className="text-sm text-muted-foreground mt-2">Record fee releases for succeeded payments</p>
                    </CardHeader>
                    <CardContent>
                        {succeededPayments.length > 0 ? (
                            <div className="space-y-4">
                                {succeededPayments.map((payment) => (
                                    <div key={payment.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                        <div>
                                            <p className="font-semibold">{payment.payment_schedule.business.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {payment.payment_schedule.name} • {payment.receiver.name}
                                            </p>
                                            <p className="text-sm font-medium">
                                                {formatCurrency(payment.amount)} • Fee: {payment.escrow_deposit ? formatCurrency(payment.escrow_deposit.fee_amount) : 'N/A'}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Processed: {new Date(payment.processed_at).toLocaleString()}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            onClick={() => recordFeeRelease(payment.id)}
                                            variant="outline"
                                        >
                                            Record Fee Release
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No payments awaiting fee release recording</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Payment Operations - Fund Returns</CardTitle>
                        <p className="text-sm text-muted-foreground mt-2">Record fund returns for failed payments</p>
                    </CardHeader>
                    <CardContent>
                        {failedPayments.length > 0 ? (
                            <div className="space-y-4">
                                {failedPayments.map((payment) => (
                                    <div key={payment.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                        <div>
                                            <p className="font-semibold">{payment.payment_schedule.business.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                {payment.payment_schedule.name} • {payment.receiver.name}
                                            </p>
                                            <p className="text-sm font-medium">
                                                {formatCurrency(payment.amount)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Failed: {new Date(payment.processed_at).toLocaleString()}
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            onClick={() => recordFundReturn(payment.id)}
                                            variant="outline"
                                        >
                                            Record Fund Return
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No payments awaiting fund return recording</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
