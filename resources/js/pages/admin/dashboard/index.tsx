import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    Users,
    DollarSign,
    Wallet,
    TrendingUp,
    Shield,
    AlertTriangle,
    Ban,
    CheckCircle,
    Clock,
    XCircle,
    ArrowRight,
} from 'lucide-react';
import {
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
    { title: 'Dashboard', href: '/admin' },
];

interface BusinessMetrics {
    total: number;
    active: number;
    suspended: number;
    banned: number;
}

interface UserMetrics {
    total: number;
    admins: number;
    verified: number;
}

interface JobMetric {
    count: number;
    total_amount: number | string;
}

interface JobMetrics {
    succeeded: JobMetric;
    failed: JobMetric;
    pending: JobMetric;
    processing: JobMetric;
}

interface RecentBusiness {
    id: number;
    name: string;
    status: 'active' | 'suspended' | 'banned';
    status_reason: string | null;
    status_changed_at: string | null;
    created_at: string;
    owner: { id: number; name: string; email: string } | null;
}

interface MonthlyData {
    month: string;
    count: number;
    total: number | string;
}

interface AdminDashboardProps {
    businessMetrics: BusinessMetrics;
    userMetrics: UserMetrics;
    paymentJobMetrics: JobMetrics;
    payrollJobMetrics: JobMetrics;
    totalEscrowBalance: number | string;
    recentBusinesses: RecentBusiness[];
    recentStatusChanges: RecentBusiness[];
    monthlyPayments: MonthlyData[];
    monthlyPayroll: MonthlyData[];
}

export default function AdminDashboard({
    businessMetrics,
    userMetrics,
    paymentJobMetrics,
    payrollJobMetrics,
    totalEscrowBalance,
    recentBusinesses,
    recentStatusChanges,
    monthlyPayments,
    monthlyPayroll,
}: AdminDashboardProps) {
    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getStatusBadge = (status: string) => {
        const styles = {
            active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            banned: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
        return (
            <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${styles[status as keyof typeof styles] || ''}`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    // Combine monthly data for chart
    const chartData = monthlyPayments.map((payment, index) => ({
        month: payment.month,
        payments: Number(payment.total),
        payroll: Number(monthlyPayroll[index]?.total || 0),
    }));

    const totalPaymentSucceeded = Number(paymentJobMetrics.succeeded.total_amount) + Number(payrollJobMetrics.succeeded.total_amount);

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Admin Dashboard</h1>
                        <p className="text-sm text-muted-foreground">Platform-wide metrics and management</p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/businesses">
                            <Button variant="outline">
                                <Building2 className="mr-2 h-4 w-4" />
                                Manage Businesses
                            </Button>
                        </Link>
                        <Link href="/admin/escrow">
                            <Button variant="outline">
                                <Wallet className="mr-2 h-4 w-4" />
                                Escrow Management
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Businesses</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{businessMetrics.total}</div>
                            <div className="flex gap-2 mt-2 text-xs">
                                <span className="flex items-center gap-1 text-green-600">
                                    <CheckCircle className="h-3 w-3" />
                                    {businessMetrics.active} active
                                </span>
                                <span className="flex items-center gap-1 text-yellow-600">
                                    <AlertTriangle className="h-3 w-3" />
                                    {businessMetrics.suspended} suspended
                                </span>
                                <span className="flex items-center gap-1 text-red-600">
                                    <Ban className="h-3 w-3" />
                                    {businessMetrics.banned} banned
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{userMetrics.total}</div>
                            <div className="flex gap-2 mt-2 text-xs">
                                <span className="flex items-center gap-1 text-primary">
                                    <Shield className="h-3 w-3" />
                                    {userMetrics.admins} admins
                                </span>
                                <span className="flex items-center gap-1 text-green-600">
                                    <CheckCircle className="h-3 w-3" />
                                    {userMetrics.verified} verified
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Escrow Balance</CardTitle>
                            <Wallet className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-primary">{formatCurrency(totalEscrowBalance)}</div>
                            <p className="text-xs text-muted-foreground">Across all businesses</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Processed</CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{formatCurrency(totalPaymentSucceeded)}</div>
                            <p className="text-xs text-muted-foreground">Successful payments & payroll</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Payment & Payroll Job Stats */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-green-600" />
                                Payment Jobs
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex items-center gap-2">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <div>
                                        <p className="text-sm font-medium">Succeeded</p>
                                        <p className="text-lg font-bold">{paymentJobMetrics.succeeded.count}</p>
                                        <p className="text-xs text-muted-foreground">{formatCurrency(paymentJobMetrics.succeeded.total_amount)}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <XCircle className="h-4 w-4 text-red-600" />
                                    <div>
                                        <p className="text-sm font-medium">Failed</p>
                                        <p className="text-lg font-bold">{paymentJobMetrics.failed.count}</p>
                                        <p className="text-xs text-muted-foreground">{formatCurrency(paymentJobMetrics.failed.total_amount)}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-yellow-600" />
                                    <div>
                                        <p className="text-sm font-medium">Pending</p>
                                        <p className="text-lg font-bold">{paymentJobMetrics.pending.count}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-blue-600" />
                                    <div>
                                        <p className="text-sm font-medium">Processing</p>
                                        <p className="text-lg font-bold">{paymentJobMetrics.processing.count}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5 text-blue-600" />
                                Payroll Jobs
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="flex items-center gap-2">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <div>
                                        <p className="text-sm font-medium">Succeeded</p>
                                        <p className="text-lg font-bold">{payrollJobMetrics.succeeded.count}</p>
                                        <p className="text-xs text-muted-foreground">{formatCurrency(payrollJobMetrics.succeeded.total_amount)}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <XCircle className="h-4 w-4 text-red-600" />
                                    <div>
                                        <p className="text-sm font-medium">Failed</p>
                                        <p className="text-lg font-bold">{payrollJobMetrics.failed.count}</p>
                                        <p className="text-xs text-muted-foreground">{formatCurrency(payrollJobMetrics.failed.total_amount)}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-yellow-600" />
                                    <div>
                                        <p className="text-sm font-medium">Pending</p>
                                        <p className="text-lg font-bold">{payrollJobMetrics.pending.count}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-blue-600" />
                                    <div>
                                        <p className="text-sm font-medium">Processing</p>
                                        <p className="text-lg font-bold">{payrollJobMetrics.processing.count}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Monthly Trends Chart */}
                {chartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly Trends (Last 6 Months)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                    <XAxis dataKey="month" className="text-xs" tick={{ fontSize: 12 }} />
                                    <YAxis
                                        className="text-xs"
                                        tick={{ fontSize: 12 }}
                                        tickFormatter={(value) => formatCurrency(value)}
                                    />
                                    <Tooltip
                                        formatter={(value: number) => formatCurrency(value)}
                                        contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                    />
                                    <Legend />
                                    <Bar dataKey="payments" fill="#22c55e" name="Payments" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="payroll" fill="#3b82f6" name="Payroll" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* Recent Activity */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Businesses</CardTitle>
                            <Link href="/admin/businesses">
                                <Button variant="ghost" size="sm">
                                    View All <ArrowRight className="ml-1 h-4 w-4" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentBusinesses.length > 0 ? (
                                <div className="space-y-4">
                                    {recentBusinesses.slice(0, 5).map((business) => (
                                        <div key={business.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="font-medium">{business.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {business.owner?.name || 'Unknown'} - {formatDate(business.created_at)}
                                                </p>
                                            </div>
                                            {getStatusBadge(business.status)}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-muted-foreground py-4">No businesses yet</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Recent Status Changes</CardTitle>
                            <Link href="/admin/businesses?sort=status_changed_at&direction=desc">
                                <Button variant="ghost" size="sm">
                                    View All <ArrowRight className="ml-1 h-4 w-4" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentStatusChanges.length > 0 ? (
                                <div className="space-y-4">
                                    {recentStatusChanges.slice(0, 5).map((business) => (
                                        <div key={business.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="font-medium">{business.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {business.status_changed_at && formatDate(business.status_changed_at)}
                                                </p>
                                                {business.status_reason && (
                                                    <p className="text-xs text-muted-foreground truncate max-w-[200px]">
                                                        Reason: {business.status_reason}
                                                    </p>
                                                )}
                                            </div>
                                            {getStatusBadge(business.status)}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-muted-foreground py-4">No status changes yet</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
