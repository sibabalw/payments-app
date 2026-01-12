import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Escrow Deposits', href: '/escrow/deposit' },
];

interface EscrowDepositProps {
    businesses: Array<{ id: number; name: string }>;
    selectedBusinessId?: number;
    escrowAccountNumber?: string;
    availableBalance: number;
    deposits: Array<{
        id: number;
        amount: string;
        fee_amount: string;
        authorized_amount: string;
        currency: string;
        status: string;
        entry_method: string;
        deposited_at: string;
        completed_at: string | null;
        bank_reference: string | null;
    }>;
}

export default function EscrowDeposit({ businesses, selectedBusinessId, escrowAccountNumber, availableBalance, deposits }: EscrowDepositProps) {
    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businesses[0]?.id || '',
        amount: '',
        currency: 'ZAR',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/escrow/deposit');
    };

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Escrow Deposits" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Escrow Deposits</h1>

                {businesses.length > 0 && (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Wallet className="h-5 w-5" />
                                        Escrow Account Balance
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div>
                                            <p className="text-sm text-muted-foreground">Available Balance</p>
                                            <p className="text-3xl font-bold">{formatCurrency(availableBalance)}</p>
                                        </div>
                                        {escrowAccountNumber && (
                                            <div className="pt-2 border-t">
                                                <p className="text-sm text-muted-foreground">Platform Escrow Account</p>
                                                <p className="text-sm font-mono">{escrowAccountNumber}</p>
                                                <p className="text-xs text-muted-foreground mt-1">Deposit funds into this account</p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Make Deposit</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submit} className="space-y-4">
                                        <div>
                                            <Label htmlFor="business_id">Business</Label>
                                            <Select
                                                value={String(data.business_id)}
                                                onValueChange={(value) => setData('business_id', Number(value))}
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
                                            {data.amount && (
                                                <p className="mt-2 text-sm text-muted-foreground">
                                                    Fee (1.5%): {formatCurrency(Number(data.amount) * 0.015)}
                                                    <br />
                                                    Authorized Amount: {formatCurrency(Number(data.amount) * 0.985)}
                                                </p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="currency">Currency</Label>
                                            <Select
                                                value={data.currency}
                                                onValueChange={(value) => setData('currency', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="ZAR">ZAR (South African Rand)</SelectItem>
                                                    <SelectItem value="USD">USD (US Dollar)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.currency} />
                                        </div>

                                        <div className="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20 mb-2">
                                            <p className="text-sm text-blue-900 dark:text-blue-200">
                                                Note: This will record your deposit request. The deposit will be pending until confirmed by bank. Make sure to deposit funds into the platform escrow account.
                                            </p>
                                        </div>
                                        <Button type="submit" disabled={processing} className="w-full">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Record Deposit
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Deposit History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {deposits.length > 0 ? (
                                    <div className="space-y-4">
                                        {deposits.map((deposit) => (
                                            <div key={deposit.id} className="flex items-center justify-between border-b pb-4 last:border-0">
                                                <div>
                                                    <p className="font-semibold">{formatCurrency(deposit.amount)}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Fee: {formatCurrency(deposit.fee_amount)} • 
                                                        Authorized: {formatCurrency(deposit.authorized_amount)}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {new Date(deposit.deposited_at).toLocaleString()}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <span
                                                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                            deposit.status === 'confirmed'
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                : deposit.status === 'pending'
                                                                  ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                                  : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                        }`}
                                                    >
                                                        {deposit.status === 'pending' ? 'Pending Bank Confirmation' : deposit.status}
                                                    </span>
                                                    {deposit.entry_method && (
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            {deposit.entry_method === 'app' ? 'Via App' : 'Manual Entry'}
                                                        </p>
                                                    )}
                                                    {deposit.bank_reference && (
                                                        <p className="text-xs text-muted-foreground mt-1">
                                                            Ref: {deposit.bank_reference}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-center text-muted-foreground py-8">No deposits yet</p>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}

                {businesses.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No businesses found. Create a business first.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
