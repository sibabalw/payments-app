import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ChevronLeft,
    Activity,
    TrendingUp,
    TrendingDown,
    Clock,
    AlertCircle,
    CheckCircle,
    XCircle,
    Database,
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
    { title: 'Performance', href: '/admin/performance' },
];

interface PerformanceProps {
    paymentMetrics: {
        total: number;
        succeeded: number;
        failed: number;
        success_rate: number;
        avg_processing_time_ms: number;
    };
    payrollMetrics: {
        total: number;
        succeeded: number;
        failed: number;
        success_rate: number;
        avg_processing_time_ms: number;
    };
    queueStats: {
        pending: number;
        failed: number;
    };
    errorRates: {
        balance: number;
        concurrency: number;
        validation: number;
        network: number;
        other: number;
    };
    responseTimeTrends: Array<{
        date: string;
        payment_avg_ms: number;
        payroll_avg_ms: number;
    }>;
    transactionTrends: Array<{
        date: string;
        payments: number;
        payroll: number;
    }>;
    slowOperations: Array<{
        id: number;
        type: string;
        processing_time_ms: number;
        status: string;
        created_at: string;
    }>;
}

export default function Performance({
    paymentMetrics,
    payrollMetrics,
    queueStats,
    errorRates,
    responseTimeTrends,
    transactionTrends,
    slowOperations,
}: PerformanceProps) {
    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('en-ZA', {
            month: 'short',
            day: 'numeric',
        });
    };

    const getSuccessRateColor = (rate: number) => {
        if (rate >= 95) {
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        }
        if (rate >= 80) {
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        }
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    };

    const errorRatesData = Object.entries(errorRates).map(([key, value]) => ({
        category: key.charAt(0).toUpperCase() + key.slice(1),
        rate: (value * 100).toFixed(2),
    }));

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Performance Monitoring" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Performance Monitoring</h1>
                        <p className="text-sm text-muted-foreground">System performance metrics and statistics</p>
                    </div>
                    <Link href="/admin">
                        <button className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </button>
                    </Link>
                </div>

                {/* Key Metrics */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Payment Success Rate</CardTitle>
                            <CheckCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{paymentMetrics.success_rate}%</div>
                            <div className="flex items-center gap-2 mt-2">
                                <Badge className={getSuccessRateColor(paymentMetrics.success_rate)}>
                                    {paymentMetrics.succeeded}/{paymentMetrics.total}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Payroll Success Rate</CardTitle>
                            <CheckCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{payrollMetrics.success_rate}%</div>
                            <div className="flex items-center gap-2 mt-2">
                                <Badge className={getSuccessRateColor(payrollMetrics.success_rate)}>
                                    {payrollMetrics.succeeded}/{payrollMetrics.total}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Avg Processing Time</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {paymentMetrics.avg_processing_time_ms > 0
                                    ? `${paymentMetrics.avg_processing_time_ms.toFixed(0)}ms`
                                    : 'N/A'}
                            </div>
                            <p className="text-xs text-muted-foreground">Payments (30 days)</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Queue Status</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{queueStats.pending}</div>
                            <p className="text-xs text-muted-foreground">
                                {queueStats.failed > 0 ? (
                                    <span className="text-red-600">{queueStats.failed} failed</span>
                                ) : (
                                    'No failed jobs'
                                )}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Response Time Trends (7 Days)</CardTitle>
                            <CardDescription>Average processing time in milliseconds</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={responseTimeTrends}>
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
                                        dataKey="payment_avg_ms"
                                        stroke="#22c55e"
                                        name="Payments"
                                        strokeWidth={2}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="payroll_avg_ms"
                                        stroke="#3b82f6"
                                        name="Payroll"
                                        strokeWidth={2}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Transaction Volume (7 Days)</CardTitle>
                            <CardDescription>Number of transactions processed</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={transactionTrends}>
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
                                    <Bar dataKey="payments" fill="#22c55e" name="Payments" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="payroll" fill="#3b82f6" name="Payroll" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                {/* Error Rates and Slow Operations */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertCircle className="h-5 w-5" />
                                Error Rates by Category
                            </CardTitle>
                            <CardDescription>Error rates as percentage</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {errorRatesData.map((item) => (
                                    <div key={item.category} className="flex items-center justify-between">
                                        <span className="text-sm font-medium">{item.category}</span>
                                        <Badge variant="secondary">{item.rate}%</Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Slow Operations (Last 24h)
                            </CardTitle>
                            <CardDescription>Top 10 slowest operations</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {slowOperations.length > 0 ? (
                                <div className="space-y-3">
                                    {slowOperations.slice(0, 10).map((op) => (
                                        <div
                                            key={`${op.type}-${op.id}`}
                                            className="flex items-center justify-between border-b pb-2 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {op.type.charAt(0).toUpperCase() + op.type.slice(1)} #{op.id}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {new Date(op.created_at).toLocaleString('en-ZA')}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <Badge
                                                    variant={op.status === 'succeeded' ? 'default' : 'destructive'}
                                                >
                                                    {op.processing_time_ms.toFixed(0)}ms
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-center text-muted-foreground py-4">No slow operations found</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
