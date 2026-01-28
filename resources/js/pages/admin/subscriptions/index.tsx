import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    DollarSign,
    TrendingUp,
    Building2,
    Clock,
    CheckCircle,
    XCircle,
    FileText,
} from 'lucide-react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Subscriptions', href: '/admin/subscriptions' },
];

interface Subscription {
    id: number;
    business_id: number;
    billing_month: string;
    business_type: string;
    subscription_fee: number;
    total_deposit_fees: number;
    status: 'pending' | 'paid' | 'waived';
    billed_at: string | null;
    paid_at: string | null;
    created_at: string;
    business: {
        id: number;
        name: string;
        status: string;
    } | null;
}

interface SubscriptionsProps {
    subscriptions: {
        data: Subscription[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    stats: {
        total: number;
        pending: number;
        paid: number;
        waived: number;
        total_revenue: number;
        total_billed: number;
    };
    revenueTrends: Array<{
        month: string;
        count: number;
        revenue: number;
        total_billed: number;
    }>;
    recentTransactions: Array<{
        id: number;
        business_id: number;
        business_name: string;
        type: string;
        amount: number;
        status: string;
        created_at: string;
    }>;
}

export default function Subscriptions({
    subscriptions,
    stats,
    revenueTrends,
    recentTransactions,
}: SubscriptionsProps) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const formatDate = (date: string | null) => {
        if (!date) {
            return 'N/A';
        }
        return new Date(date).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getStatusBadge = (status: string) => {
        const styles = {
            pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            waived: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
        return (
            <Badge className={styles[status as keyof typeof styles] || ''}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    const handleStatusChange = (billingId: number, newStatus: string) => {
        router.post(
            `/admin/subscriptions/${billingId}/status`,
            { status: newStatus },
            {
                onSuccess: () => {
                    router.reload();
                },
            }
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Subscriptions Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Subscriptions Management</h1>
                        <p className="text-sm text-muted-foreground">Manage business subscriptions and billing</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Subscriptions</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending</CardTitle>
                            <Clock className="h-4 w-4 text-yellow-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.pending}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Revenue</CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(stats.total_revenue)}</div>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(stats.total_billed)} billed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Paid</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.paid}</div>
                            <p className="text-xs text-muted-foreground">{stats.waived} waived</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Revenue Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle>Revenue Trends (Last 6 Months)</CardTitle>
                        <CardDescription>Monthly subscription revenue and billing</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <BarChart data={revenueTrends}>
                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                <XAxis
                                    dataKey="month"
                                    className="text-xs"
                                    tick={{ fontSize: 12 }}
                                />
                                <YAxis
                                    className="text-xs"
                                    tick={{ fontSize: 12 }}
                                    tickFormatter={(value) => formatCurrency(value)}
                                />
                                <Tooltip
                                    formatter={(value: number) => formatCurrency(value)}
                                    contentStyle={{
                                        backgroundColor: 'hsl(var(--background))',
                                        border: '1px solid hsl(var(--border))',
                                    }}
                                />
                                <Legend />
                                <Bar dataKey="revenue" fill="#22c55e" name="Revenue" radius={[4, 4, 0, 0]} />
                                <Bar dataKey="total_billed" fill="#3b82f6" name="Total Billed" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                {/* Subscriptions List */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            Subscriptions
                        </CardTitle>
                        <CardDescription>All business subscriptions and billing records</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {subscriptions.data.length > 0 ? (
                            <div className="space-y-4">
                                {subscriptions.data.map((subscription) => (
                                    <div
                                        key={subscription.id}
                                        className="border rounded-lg p-4 space-y-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="font-medium">
                                                    {subscription.business?.name || 'Unknown Business'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {subscription.billing_month} • {subscription.business_type}
                                                </p>
                                            </div>
                                            {getStatusBadge(subscription.status)}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span className="text-muted-foreground">Subscription Fee:</span>
                                                <span className="ml-2 font-medium">
                                                    {formatCurrency(subscription.subscription_fee)}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Deposit Fees:</span>
                                                <span className="ml-2 font-medium">
                                                    {formatCurrency(subscription.total_deposit_fees)}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Billed At:</span>
                                                <span className="ml-2">{formatDate(subscription.billed_at)}</span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Paid At:</span>
                                                <span className="ml-2">{formatDate(subscription.paid_at)}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-muted-foreground">Status:</span>
                                            <Select
                                                value={subscription.status}
                                                onValueChange={(value) =>
                                                    handleStatusChange(subscription.id, value)
                                                }
                                            >
                                                <SelectTrigger className="w-[180px]">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="pending">Pending</SelectItem>
                                                    <SelectItem value="paid">Paid</SelectItem>
                                                    <SelectItem value="waived">Waived</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-4">No subscriptions found</p>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Transactions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <DollarSign className="h-5 w-5" />
                            Recent Billing Transactions
                        </CardTitle>
                        <CardDescription>Latest billing transaction activity</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length > 0 ? (
                            <div className="space-y-3">
                                {recentTransactions.map((transaction) => (
                                    <div
                                        key={transaction.id}
                                        className="flex items-center justify-between border-b pb-3 last:border-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">{transaction.business_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {transaction.type} • {formatDate(transaction.created_at)}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium">
                                                {formatCurrency(transaction.amount)}
                                            </p>
                                            <Badge
                                                variant={
                                                    transaction.status === 'completed'
                                                        ? 'default'
                                                        : transaction.status === 'failed'
                                                          ? 'destructive'
                                                          : 'secondary'
                                                }
                                            >
                                                {transaction.status}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-4">No transactions found</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
