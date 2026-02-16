import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ChevronLeft,
    FileText,
    TrendingUp,
    Users,
    Building2,
    DollarSign,
    Wallet,
    Download,
} from 'lucide-react';
import {
    LineChart,
    Line,
    BarChart,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';
import { Bar } from 'recharts/es6/cartesian/Bar';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Reports', href: '/admin/system-reports' },
];

interface SystemReportsProps {
    businessActivity: {
        new_businesses: Array<{ date: string; count: number }>;
        status_changes: Array<{ status: string; count: number }>;
        by_status: Array<{ status: string; count: number }>;
    };
    transactionReport: {
        payment_trends: Array<{
            date: string;
            count: number;
            total_amount: number;
            succeeded: number;
            failed: number;
        }>;
        payroll_trends: Array<{
            date: string;
            count: number;
            total_amount: number;
            succeeded: number;
            failed: number;
        }>;
        payment_summary: {
            total: number;
            succeeded: number;
            failed: number;
            total_amount: number;
        };
        payroll_summary: {
            total: number;
            succeeded: number;
            failed: number;
            total_amount: number;
        };
    };
    financialReport: {
        escrow_balances: Array<{
            business_id: number;
            business_name: string;
            total_balance: number;
            deposit_count: number;
        }>;
        total_escrow_balance: number;
        fees_collected_30d: number;
        fee_trends: Array<{
            month: string;
            total_fees: number;
            transaction_count: number;
        }>;
    };
    userActivity: {
        new_registrations: Array<{ date: string; count: number }>;
        verifications: Array<{ date: string; count: number }>;
        statistics: {
            total: number;
            admins: number;
            verified: number;
        };
    };
    summary: {
        total_businesses: number;
        active_businesses: number;
        total_users: number;
        verified_users: number;
        total_payments: number;
        total_payroll: number;
        total_escrow_balance: number;
    };
}

export default function SystemReports({
    businessActivity,
    transactionReport,
    financialReport,
    userActivity,
    summary,
}: SystemReportsProps) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-ZA', {
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - System Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">System Reports</h1>
                        <p className="text-sm text-muted-foreground">Comprehensive system analytics and reports</p>
                    </div>
                    <div className="flex gap-2">
                        <button className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            <Download className="mr-2 h-4 w-4" />
                            Export Report
                        </button>
                        <Link href="/admin">
                            <button className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                                <ChevronLeft className="mr-2 h-4 w-4" />
                                Back to Dashboard
                            </button>
                        </Link>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Businesses</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_businesses}</div>
                            <p className="text-xs text-muted-foreground">{summary.active_businesses} active</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_users}</div>
                            <p className="text-xs text-muted-foreground">{summary.verified_users} verified</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Escrow</CardTitle>
                            <Wallet className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(summary.total_escrow_balance)}</div>
                            <p className="text-xs text-muted-foreground">Platform balance</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Transactions</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {summary.total_payments + summary.total_payroll}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {summary.total_payments} payments, {summary.total_payroll} payroll
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Transaction Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction Trends (Last 30 Days)</CardTitle>
                        <CardDescription>Payment and payroll transaction volume</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={transactionReport.payment_trends}>
                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                <XAxis
                                    dataKey="date"
                                    tickFormatter={formatDate}
                                    className="text-xs"
                                    tick={{ fontSize: 12 }}
                                />
                                <YAxis className="text-xs" tick={{ fontSize: 12 }} />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: 'hsl(var(--background))',
                                        border: '1px solid hsl(var(--border))',
                                    }}
                                />
                                <Legend />
                                <Line
                                    type="monotone"
                                    dataKey="succeeded"
                                    stroke="#22c55e"
                                    name="Payments Succeeded"
                                    strokeWidth={2}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="failed"
                                    stroke="#ef4444"
                                    name="Payments Failed"
                                    strokeWidth={2}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>

                {/* Transaction Summary */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Summary (30 Days)</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Total</span>
                                <span className="font-medium">{transactionReport.payment_summary.total}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Succeeded</span>
                                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    {transactionReport.payment_summary.succeeded}
                                </Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Failed</span>
                                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    {transactionReport.payment_summary.failed}
                                </Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Total Amount</span>
                                <span className="font-medium">
                                    {formatCurrency(transactionReport.payment_summary.total_amount)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Payroll Summary (30 Days)</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Total</span>
                                <span className="font-medium">{transactionReport.payroll_summary.total}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Succeeded</span>
                                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    {transactionReport.payroll_summary.succeeded}
                                </Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Failed</span>
                                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    {transactionReport.payroll_summary.failed}
                                </Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Total Amount</span>
                                <span className="font-medium">
                                    {formatCurrency(transactionReport.payroll_summary.total_amount)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Financial Report */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Wallet className="h-5 w-5" />
                            Financial Report
                        </CardTitle>
                        <CardDescription>Escrow balances and fee collection</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 mb-6">
                            <div>
                                <p className="text-sm text-muted-foreground">Total Escrow Balance</p>
                                <p className="text-2xl font-bold">{formatCurrency(financialReport.total_escrow_balance)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Fees Collected (30 Days)</p>
                                <p className="text-2xl font-bold">{formatCurrency(financialReport.fees_collected_30d)}</p>
                            </div>
                        </div>

                        <div className="space-y-3">
                            <h4 className="font-medium">Top Escrow Balances by Business</h4>
                            {financialReport.escrow_balances.length > 0 ? (
                                <div className="space-y-2">
                                    {financialReport.escrow_balances.map((balance) => (
                                        <div
                                            key={balance.business_id}
                                            className="flex items-center justify-between border-b pb-2 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">{balance.business_name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {balance.deposit_count} deposits
                                                </p>
                                            </div>
                                            <span className="font-medium">{formatCurrency(balance.total_balance)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-muted-foreground py-4">No escrow balances found</p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Business Activity */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            Business Activity
                        </CardTitle>
                        <CardDescription>New businesses and status changes</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <h4 className="font-medium mb-3">Businesses by Status</h4>
                                <div className="space-y-2">
                                    {businessActivity.by_status.map((item) => (
                                        <div key={item.status} className="flex justify-between text-sm">
                                            <span className="text-muted-foreground capitalize">{item.status}</span>
                                            <Badge variant="secondary">{item.count}</Badge>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <h4 className="font-medium mb-3">Status Changes (30 Days)</h4>
                                <div className="space-y-2">
                                    {businessActivity.status_changes.map((item) => (
                                        <div key={item.status} className="flex justify-between text-sm">
                                            <span className="text-muted-foreground capitalize">{item.status}</span>
                                            <Badge variant="secondary">{item.count}</Badge>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* User Activity */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            User Activity
                        </CardTitle>
                        <CardDescription>User registrations and verifications</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 mb-4">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Total Users</span>
                                    <span className="font-medium">{userActivity.statistics.total}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Admins</span>
                                    <Badge variant="secondary">{userActivity.statistics.admins}</Badge>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Verified</span>
                                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        {userActivity.statistics.verified}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
