import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, CreditCard, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Businesses', href: '/businesses' },
    { title: 'Bank Account', href: '#' },
];

interface BankAccountProps {
    business: {
        id: number;
        name: string;
        bank_account_details: {
            account_number?: string;
            bank_name?: string;
            account_holder_name?: string;
            account_type?: string;
            branch_code?: string;
        } | null;
    };
}

export default function BankAccount({ business }: BankAccountProps) {
    const [showAccountNumber, setShowAccountNumber] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        bank_account_details: {
            account_number: business.bank_account_details?.account_number || '',
            bank_name: business.bank_account_details?.bank_name || '',
            account_holder_name: business.bank_account_details?.account_holder_name || '',
            account_type: business.bank_account_details?.account_type || 'business',
            branch_code: business.bank_account_details?.branch_code || '',
        },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/businesses/${business.id}/bank-account`);
    };

    const maskAccountNumber = (accountNumber: string) => {
        if (!accountNumber || accountNumber.length < 4) {
            return accountNumber;
        }
        return '****' + accountNumber.slice(-4);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bank Account Details" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            Bank Account Details - {business.name}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 rounded-lg bg-muted p-4">
                            <p className="text-sm text-muted-foreground">
                                Your bank account details are used to process monthly subscription charges. 
                                Please ensure all information is accurate.
                            </p>
                        </div>

                        {business.bank_account_details && (
                            <div className="mb-6 rounded-lg border border-border p-4">
                                <h3 className="mb-2 text-sm font-semibold">Current Bank Account</h3>
                                <div className="space-y-1 text-sm">
                                    <div className="flex items-center gap-2">
                                        <CreditCard className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">Account Number:</span>
                                        <span className="font-mono">
                                            {showAccountNumber 
                                                ? business.bank_account_details.account_number 
                                                : maskAccountNumber(business.bank_account_details.account_number || '')}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 w-6 p-0"
                                            onClick={() => setShowAccountNumber(!showAccountNumber)}
                                        >
                                            {showAccountNumber ? (
                                                <EyeOff className="h-3 w-3" />
                                            ) : (
                                                <Eye className="h-3 w-3" />
                                            )}
                                        </Button>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Bank:</span>{' '}
                                        <span>{business.bank_account_details.bank_name}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Account Holder:</span>{' '}
                                        <span>{business.bank_account_details.account_holder_name}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Account Type:</span>{' '}
                                        <span className="capitalize">{business.bank_account_details.account_type}</span>
                                    </div>
                                    {business.bank_account_details.branch_code && (
                                        <div>
                                            <span className="text-muted-foreground">Branch Code:</span>{' '}
                                            <span>{business.bank_account_details.branch_code}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="account_number">Account Number</Label>
                                <Input
                                    id="account_number"
                                    value={data.bank_account_details.account_number}
                                    onChange={(e) => setData('bank_account_details', {
                                        ...data.bank_account_details,
                                        account_number: e.target.value,
                                    })}
                                    required
                                    placeholder="Enter account number"
                                />
                                <InputError message={errors['bank_account_details.account_number']} />
                            </div>

                            <div>
                                <Label htmlFor="bank_name">Bank Name</Label>
                                <Input
                                    id="bank_name"
                                    value={data.bank_account_details.bank_name}
                                    onChange={(e) => setData('bank_account_details', {
                                        ...data.bank_account_details,
                                        bank_name: e.target.value,
                                    })}
                                    required
                                    placeholder="e.g., First National Bank, Standard Bank"
                                />
                                <InputError message={errors['bank_account_details.bank_name']} />
                            </div>

                            <div>
                                <Label htmlFor="account_holder_name">Account Holder Name</Label>
                                <Input
                                    id="account_holder_name"
                                    value={data.bank_account_details.account_holder_name}
                                    onChange={(e) => setData('bank_account_details', {
                                        ...data.bank_account_details,
                                        account_holder_name: e.target.value,
                                    })}
                                    required
                                    placeholder="Name as it appears on the account"
                                />
                                <InputError message={errors['bank_account_details.account_holder_name']} />
                            </div>

                            <div>
                                <Label htmlFor="account_type">Account Type</Label>
                                <Select
                                    value={data.bank_account_details.account_type}
                                    onValueChange={(value) => setData('bank_account_details', {
                                        ...data.bank_account_details,
                                        account_type: value,
                                    })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="checking">Checking</SelectItem>
                                        <SelectItem value="savings">Savings</SelectItem>
                                        <SelectItem value="business">Business</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors['bank_account_details.account_type']} />
                            </div>

                            <div>
                                <Label htmlFor="branch_code">Branch Code (Optional)</Label>
                                <Input
                                    id="branch_code"
                                    value={data.bank_account_details.branch_code}
                                    onChange={(e) => setData('bank_account_details', {
                                        ...data.bank_account_details,
                                        branch_code: e.target.value,
                                    })}
                                    placeholder="Enter branch code if required"
                                />
                                <InputError message={errors['bank_account_details.branch_code']} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Bank Account Details'}
                                </Button>
                                <Link href="/billing">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
