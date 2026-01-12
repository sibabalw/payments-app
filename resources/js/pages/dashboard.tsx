import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Calendar, DollarSign, Sparkles, TrendingDown, TrendingUp, Users, Wallet } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface DashboardProps {
    metrics: {
        total_schedules: number;
        active_schedules: number;
        pending_jobs: number;
        processing_jobs: number;
        succeeded_jobs: number;
        failed_jobs: number;
    };
    upcomingPayments: any[];
    recentJobs: any[];
    escrowBalance?: number;
    selectedBusiness?: { id: number; name: string } | null;
    businessesCount?: number;
}

export default function Dashboard({ metrics, upcomingPayments, recentJobs, escrowBalance = 0, selectedBusiness = null, businessesCount: propBusinessesCount }: DashboardProps) {
    const { businessesCount: sharedBusinessesCount = 0 } = usePage<SharedData>().props;
    const businessesCount = propBusinessesCount ?? sharedBusinessesCount;
    
    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };
    
    // Debug: Log the data
    console.log('Dashboard data:', { escrowBalance, selectedBusiness });
    
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Soft Gradient Banner */}
                {businessesCount === 0 && (
                    <div className="relative overflow-hidden rounded-lg border border-primary/20 bg-gradient-to-r from-primary/5 via-primary/10 to-primary/5 p-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Building2 className="h-5 w-5 text-primary" />
                            </div>
                            <div className="flex-1">
                                <p className="text-sm font-medium text-foreground">
                                    Get started by adding your first business
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Create a business to start managing payments and schedules
                                </p>
                            </div>
                            <Button asChild size="sm" variant="outline" className="border-primary/30 bg-background/50 hover:bg-primary/10">
                                <Link href="/businesses">
                                    Add Business
                                    <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}

                {/* Metrics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    {businessesCount === 0 ? (
                        <Card className="relative overflow-hidden border-primary/30 bg-gradient-to-br from-primary/5 via-primary/10/50 to-primary/5">
                            <div className="absolute right-0 top-0 -translate-y-1/2 translate-x-1/4 opacity-10">
                                <Sparkles className="h-24 w-24 text-primary" />
                            </div>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Get Started</CardTitle>
                                <Building2 className="h-4 w-4 text-primary" />
                            </CardHeader>
                            <CardContent className="relative">
                                <p className="text-sm font-medium text-foreground mb-2">
                                    Welcome! Add your first business to start managing payments
                                </p>
                                <Button asChild size="sm" variant="default" className="mt-2">
                                    <Link href="/businesses">
                                        Create Business
                                        <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="border-primary">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Escrow Balance</CardTitle>
                            <Wallet className="h-4 w-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-primary">{formatCurrency(escrowBalance || 0)}</div>
                            <p className="text-xs text-muted-foreground">
                                {selectedBusiness?.name || 'No business selected'}
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                Available (after 1.5% fee)
                            </p>
                        </CardContent>
                    </Card>
                    )}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Schedules</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.total_schedules}</div>
                            <p className="text-xs text-muted-foreground">
                                {metrics.active_schedules} active
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Jobs</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.pending_jobs}</div>
                            <p className="text-xs text-muted-foreground">
                                {metrics.processing_jobs} processing
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Succeeded</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.succeeded_jobs}</div>
                            <p className="text-xs text-muted-foreground">Completed payments</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Failed Jobs</CardTitle>
                            <TrendingDown className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{metrics.failed_jobs}</div>
                            <p className="text-xs text-muted-foreground">Requires attention</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Upcoming Payments */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Upcoming Payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {upcomingPayments.length > 0 ? (
                                <div className="space-y-4">
                                    {upcomingPayments.map((payment) => (
                                        <div key={payment.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="font-medium">{payment.name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {new Date(payment.next_run_at).toLocaleString()}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-medium">
                                                    {payment.currency} {payment.amount}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {payment.receivers?.length || 0} receivers
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">No upcoming payments</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recent Jobs */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recentJobs.length > 0 ? (
                                <div className="space-y-4">
                                    {recentJobs.map((job) => (
                                        <div key={job.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="font-medium">{job.receiver?.name}</p>
                                                <p className="text-sm text-muted-foreground">
                                                    {job.payment_schedule?.name}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                        job.status === 'succeeded'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                            : job.status === 'failed'
                                                              ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                              : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                    }`}
                                                >
                                                    {job.status}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">No recent jobs</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
