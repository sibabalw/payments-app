import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Escrow Management', href: '/admin/escrow' },
    { title: 'Business Balances', href: '#' },
];

interface BalancesProps {
    businesses: Array<{
        id: number;
        name: string;
        total_deposited: number;
        total_fees: number;
        total_authorized: number;
        used: number;
        returned: number;
        available_balance: number;
    }>;
}

export default function Balances({ businesses }: BalancesProps) {
    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Business Balances" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Business Escrow Balances</h1>
                    <Link href="/admin/escrow">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                </div>

                <div className="grid gap-4">
                    {businesses.map((business) => (
                        <Card key={business.id}>
                            <CardHeader>
                                <CardTitle>{business.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Total Deposited</p>
                                        <p className="text-lg font-semibold">{formatCurrency(business.total_deposited)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Total Fees</p>
                                        <p className="text-lg font-semibold">{formatCurrency(business.total_fees)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Total Authorized</p>
                                        <p className="text-lg font-semibold">{formatCurrency(business.total_authorized)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Available Balance</p>
                                        <p className="text-2xl font-bold text-primary">{formatCurrency(business.available_balance)}</p>
                                    </div>
                                </div>
                                <div className="mt-4 pt-4 border-t grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Used</p>
                                        <p className="text-sm font-semibold">{formatCurrency(business.used)}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Returned</p>
                                        <p className="text-sm font-semibold">{formatCurrency(business.returned)}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}

                    {businesses.length === 0 && (
                        <Card>
                            <CardContent className="py-10 text-center">
                                <p className="text-muted-foreground">No businesses found.</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
