import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { WelcomeTourModal } from '@/components/welcome-tour-modal';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Calendar, DollarSign, Sparkles, TrendingDown, TrendingUp, Users, Wallet, ChevronRight, Plus, Activity, BarChart3 as BarChartIcon, PieChart as PieChartIcon, Target, LayoutDashboard, BarChart as BarChartIcon2 } from 'lucide-react';
import { useState, useEffect, useTransition } from 'react';
import { 
    LineChart, 
    Line, 
    AreaChart, 
    Area, 
    BarChart as RechartsBarChart, 
    Bar, 
    PieChart as RechartsPieChart, 
    Pie, 
    Cell, 
    XAxis, 
    YAxis, 
    CartesianGrid, 
    Tooltip, 
    Legend, 
    ResponsiveContainer 
} from 'recharts';

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
    financial?: {
        total_payments_this_month: number;
        total_payroll_this_month: number;
        total_fees_this_month: number;
        total_processed_this_month: number;
        success_rate: number;
        total_jobs_this_month: number;
    };
    monthlyTrends?: Array<{
        month: string;
        payments: number;
        payroll: number;
        total: number;
    }>;
    statusBreakdown?: {
        succeeded: number;
        failed: number;
        pending: number;
        processing: number;
    };
    jobTypeComparison?: {
        payments: number;
        payroll: number;
    };
    dailyTrends?: Array<{
        date: string;
        payments: number;
        payroll: number;
        total: number;
        jobs_count: number;
    }>;
    weeklyTrends?: Array<{
        week: string;
        payments: number;
        payroll: number;
        total: number;
    }>;
    successRateTrends?: Array<{
        week?: string;
        month?: string;
        quarter?: string;
        year?: string;
        success_rate: number;
        succeeded: number;
        failed: number;
        total: number;
    }>;
    topRecipients?: Array<{
        name: string;
        total_amount: number;
        jobs_count: number;
        average_amount: number;
    }>;
    topEmployees?: Array<{
        name: string;
        total_amount: number;
        jobs_count: number;
        average_amount: number;
    }>;
    monthOverMonthGrowth?: number;
    avgPaymentAmount?: number;
    avgPayrollAmount?: number;
    upcomingPayments: any[];
    recentJobs: any[];
    escrowBalance?: number;
    selectedBusiness?: { id: number; name: string } | null;
    businessInfo?: {
        id: number;
        name: string;
        logo: string | null;
        status: string;
        business_type: string | null;
        email: string;
        phone: string;
        escrow_balance: number;
        employees_count: number;
        payment_schedules_count: number;
        payroll_schedules_count: number;
        recipients_count: number;
    } | null;
    businessesCount?: number;
}

// Helper function to get business initials
const getBusinessInitials = (name: string): string => {
    if (!name) return '?';
    
    const words = name.trim().split(/\s+/);
    if (words.length === 1) {
        // Single word: take first 2 letters
        return name.substring(0, 2).toUpperCase();
    }
    // Multiple words: take first letter of first two words
    return (words[0][0] + words[1][0]).toUpperCase();
};

export default function Dashboard({ 
    metrics, 
    financial, 
    monthlyTrends = [], 
    statusBreakdown, 
    jobTypeComparison,
    dailyTrends = [],
    weeklyTrends = [],
    successRateTrends = [],
    topRecipients = [],
    topEmployees = [],
    monthOverMonthGrowth = 0,
    avgPaymentAmount = 0,
    avgPayrollAmount = 0,
    upcomingPayments, 
    recentJobs, 
    escrowBalance = 0, 
    selectedBusiness = null, 
    businessInfo, 
    businessesCount: propBusinessesCount 
}: DashboardProps) {
    const { businessesCount: sharedBusinessesCount = 0, hasCompletedDashboardTour, auth } = usePage<SharedData>().props;
    const businessesCount = propBusinessesCount ?? sharedBusinessesCount;
    const [isPending, startTransition] = useTransition();
    const [viewMode, setViewMode] = useState<'simple' | 'advanced'>('simple');
    
    const handleViewModeChange = (mode: 'simple' | 'advanced') => {
        startTransition(() => {
            setViewMode(mode);
        });
    };
    const [globalFrequency, setGlobalFrequency] = useState<'weekly' | 'monthly' | 'quarterly' | 'yearly'>('monthly');
    const [chartFrequencies, setChartFrequencies] = useState<{
        trends?: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
        successRate?: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
        daily?: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
        weekly?: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
    }>({});
    const [showWelcomeTour, setShowWelcomeTour] = useState(!hasCompletedDashboardTour);
    
    // Get frequency from URL params if present
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const freq = urlParams.get('frequency') as 'weekly' | 'monthly' | 'quarterly' | 'yearly' | null;
        if (freq && ['weekly', 'monthly', 'quarterly', 'yearly'].includes(freq)) {
            setGlobalFrequency(freq);
        }
    }, []);
    
    const getEffectiveFrequency = (chartKey: 'trends' | 'successRate' | 'daily' | 'weekly') => {
        return chartFrequencies[chartKey] || globalFrequency;
    };
    
    const handleGlobalFrequencyChange = (newFrequency: 'weekly' | 'monthly' | 'quarterly' | 'yearly') => {
        setGlobalFrequency(newFrequency);
        // Clear individual chart frequencies to use global
        setChartFrequencies({});
        router.get('/dashboard', { frequency: newFrequency }, {
            preserveState: true,
            preserveScroll: true,
            only: ['dailyTrends', 'weeklyTrends', 'monthlyTrends', 'successRateTrends', 'topRecipients', 'topEmployees'],
        });
    };
    
    const handleChartFrequencyChange = (
        chartKey: 'trends' | 'successRate' | 'daily' | 'weekly',
        newFrequency: 'weekly' | 'monthly' | 'quarterly' | 'yearly'
    ) => {
        setChartFrequencies(prev => ({ ...prev, [chartKey]: newFrequency }));
        const params: Record<string, string> = { frequency: globalFrequency };
        params[`${chartKey}_frequency`] = newFrequency;
        
        router.get('/dashboard', params, {
            preserveState: true,
            preserveScroll: true,
            only: ['dailyTrends', 'weeklyTrends', 'monthlyTrends', 'successRateTrends', 'topRecipients', 'topEmployees'],
        });
    };
    
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
            
            {/* Welcome Tour Modal for first-time visitors */}
            <WelcomeTourModal
                isOpen={showWelcomeTour}
                onClose={() => setShowWelcomeTour(false)}
                userName={auth.user?.name || 'there'}
            />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* View Mode Toggle */}
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    <div className="flex items-center gap-2">
                        <Button
                            variant={viewMode === 'simple' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleViewModeChange('simple')}
                            className="gap-2 transition-all"
                        >
                            <LayoutDashboard className="h-4 w-4" />
                            Simple View
                        </Button>
                        <Button
                            variant={viewMode === 'advanced' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleViewModeChange('advanced')}
                            className="gap-2 transition-all"
                        >
                            <BarChartIcon2 className={`h-4 w-4 ${isPending && viewMode === 'simple' ? 'animate-spin' : ''}`} />
                            Advanced View
                        </Button>
                    </div>
                </div>

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

                {/* Simple View */}
                {viewMode === 'simple' && (
                    <>
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
                            ) : businessInfo ? (
                        <Card className="border-primary col-span-full md:col-span-1">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Business Overview</CardTitle>
                                <Wallet className="h-4 w-4 text-primary" />
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Business Logo/Name */}
                                <div className="flex items-center gap-3 pb-3 border-b">
                                    {businessInfo.logo ? (
                                        <div className="flex aspect-square size-10 items-center justify-center rounded-md overflow-hidden flex-shrink-0 border border-border bg-muted">
                                            <img 
                                                src={businessInfo.logo} 
                                                alt={businessInfo.name}
                                                className="w-full h-full object-cover"
                                                onError={(e) => {
                                                    const target = e.target as HTMLImageElement;
                                                    const parent = target.parentElement;
                                                    if (parent) {
                                                        target.style.display = 'none';
                                                        const initialsSpan = document.createElement('span');
                                                        initialsSpan.className = 'text-xs font-semibold text-foreground';
                                                        initialsSpan.textContent = getBusinessInitials(businessInfo.name);
                                                        parent.appendChild(initialsSpan);
                                                    }
                                                }}
                                            />
                                        </div>
                                    ) : (
                                        <div className="flex aspect-square size-10 items-center justify-center rounded-md bg-primary text-primary-foreground flex-shrink-0">
                                            <span className="text-xs font-semibold">
                                                {getBusinessInitials(businessInfo.name)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex-1 min-w-0">
                                        <p className="font-semibold truncate" title={businessInfo.name}>
                                            {businessInfo.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground truncate">
                                            {businessInfo.email}
                                        </p>
                                    </div>
                                </div>

                                {/* Escrow Balance */}
                                <div>
                                    <div className="text-2xl font-bold text-primary">{formatCurrency(businessInfo.escrow_balance || 0)}</div>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Available (after 1.5% fee)
                                    </p>
                                </div>

                                {/* Statistics Grid */}
                                <div className="grid grid-cols-2 gap-2 pt-2 border-t">
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <Users className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span className="text-muted-foreground">Employees:</span>
                                        <span className="font-semibold">{businessInfo.employees_count || 0}</span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span className="text-muted-foreground">Payments:</span>
                                        <span className="font-semibold">{businessInfo.payment_schedules_count || 0}</span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <DollarSign className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span className="text-muted-foreground">Payroll:</span>
                                        <span className="font-semibold">{businessInfo.payroll_schedules_count || 0}</span>
                                    </div>
                                    <div className="flex items-center gap-1.5 text-xs">
                                        <Users className="h-3.5 w-3.5 text-muted-foreground" />
                                        <span className="text-muted-foreground">Recipients:</span>
                                        <span className="font-semibold">{businessInfo.recipients_count || 0}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
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

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Activity className="h-5 w-5" />
                                    Quick Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 md:grid-cols-4">
                                    <Link href="/payments/create">
                                        <Button variant="outline" className="w-full justify-start">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Payment
                                        </Button>
                                    </Link>
                                    <Link href="/payroll/create">
                                        <Button variant="outline" className="w-full justify-start">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Payroll
                                        </Button>
                                    </Link>
                                    <Link href="/recipients/create">
                                        <Button variant="outline" className="w-full justify-start">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Recipient
                                        </Button>
                                    </Link>
                                    <Link href="/employees/create">
                                        <Button variant="outline" className="w-full justify-start">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Employee
                                        </Button>
                                    </Link>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 md:grid-cols-2">
                            {/* Upcoming Payments & Payroll */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Upcoming Payments & Payroll</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {upcomingPayments.length > 0 ? (
                                        <>
                                            <div className="space-y-4">
                                                {upcomingPayments.map((schedule) => (
                                                    <div key={`${schedule.type}-${schedule.id}`} className="flex items-center justify-between">
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <p className="font-medium">{schedule.name}</p>
                                                                <span className={`text-xs px-2 py-0.5 rounded-full ${
                                                                    schedule.type === 'payroll' 
                                                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                                        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                }`}>
                                                                    {schedule.type === 'payroll' ? 'Payroll' : 'Payment'}
                                                                </span>
                                                            </div>
                                                            <p className="text-sm text-muted-foreground">
                                                                {new Date(schedule.next_run_at).toLocaleString()}
                                                            </p>
                                                        </div>
                                                        <div className="text-right">
                                                            {schedule.amount && (
                                                                <p className="font-medium">
                                                                    {schedule.currency} {schedule.amount}
                                                                </p>
                                                            )}
                                                            <p className="text-sm text-muted-foreground">
                                                                {schedule.type === 'payroll' 
                                                                    ? `${schedule.employees_count || 0} employees`
                                                                    : `${schedule.recipients_count || 0} recipients`
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="pt-4 mt-4 border-t">
                                                <Link 
                                                    href="/payments" 
                                                    className="flex items-center justify-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    See more schedules
                                                    <ChevronRight className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No upcoming payments or payroll</p>
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
                                        <>
                                            <div className="space-y-4">
                                                {recentJobs.map((job) => (
                                                    <div key={`${job.type}-${job.id}`} className="flex items-center justify-between">
                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                <p className="font-medium truncate">{job.name}</p>
                                                                <span className={`text-xs px-2 py-0.5 rounded-full flex-shrink-0 ${
                                                                    job.type === 'payroll' 
                                                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                                        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                                }`}>
                                                                    {job.type === 'payroll' ? 'Payroll' : 'Payment'}
                                                                </span>
                                                            </div>
                                                            <p className="text-sm text-muted-foreground truncate">
                                                                {job.schedule_name}
                                                            </p>
                                                            {job.processed_at && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    {new Date(job.processed_at).toLocaleString()}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="text-right ml-4 flex-shrink-0">
                                                            {job.amount && (
                                                                <p className="text-sm font-medium mb-1">
                                                                    {job.currency} {job.amount}
                                                                </p>
                                                            )}
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
                                            <div className="pt-4 mt-4 border-t">
                                                <Link 
                                                    href="/payments/jobs" 
                                                    className="flex items-center justify-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    See more jobs
                                                    <ChevronRight className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No recent jobs</p>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}

                {/* Advanced View */}
                {viewMode === 'advanced' && (
                    <>
                        {/* Global Frequency Selector */}
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-medium">Global Time Frequency</h3>
                                        <p className="text-xs text-muted-foreground mt-1">Default time period for all charts (can be overridden per chart)</p>
                                    </div>
                                    <Select value={globalFrequency} onValueChange={handleGlobalFrequencyChange}>
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue placeholder="Select frequency" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="weekly">Weekly</SelectItem>
                                            <SelectItem value="monthly">Monthly</SelectItem>
                                            <SelectItem value="quarterly">Quarterly</SelectItem>
                                            <SelectItem value="yearly">Yearly</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Financial Overview Cards */}
                        {financial && (
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">This Month - Payments</CardTitle>
                                        <DollarSign className="h-4 w-4 text-green-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-green-600">{formatCurrency(financial.total_payments_this_month)}</div>
                                        <p className="text-xs text-muted-foreground">Total processed</p>
                                        {avgPaymentAmount > 0 && (
                                            <p className="text-xs text-muted-foreground mt-1">Avg: {formatCurrency(avgPaymentAmount)}</p>
                                        )}
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">This Month - Payroll</CardTitle>
                                        <Users className="h-4 w-4 text-blue-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-blue-600">{formatCurrency(financial.total_payroll_this_month)}</div>
                                        <p className="text-xs text-muted-foreground">Total processed</p>
                                        {avgPayrollAmount > 0 && (
                                            <p className="text-xs text-muted-foreground mt-1">Avg: {formatCurrency(avgPayrollAmount)}</p>
                                        )}
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                                        <Target className="h-4 w-4 text-primary" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{financial.success_rate}%</div>
                                        <p className="text-xs text-muted-foreground">{financial.total_jobs_this_month} jobs this month</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">Total Fees</CardTitle>
                                        <Wallet className="h-4 w-4 text-amber-600" />
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-amber-600">{formatCurrency(financial.total_fees_this_month)}</div>
                                        <p className="text-xs text-muted-foreground">Collected this month</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">MoM Growth</CardTitle>
                                        {monthOverMonthGrowth >= 0 ? (
                                            <TrendingUp className="h-4 w-4 text-green-600" />
                                        ) : (
                                            <TrendingDown className="h-4 w-4 text-red-600" />
                                        )}
                                    </CardHeader>
                                    <CardContent>
                                        <div className={`text-2xl font-bold ${monthOverMonthGrowth >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {monthOverMonthGrowth >= 0 ? '+' : ''}{monthOverMonthGrowth}%
                                        </div>
                                        <p className="text-xs text-muted-foreground">vs last month</p>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {/* Daily Trends Chart - Last 30 Days */}
                        {getEffectiveFrequency('daily') === 'weekly' && dailyTrends.length > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-5 w-5" />
                                        Daily Transaction Trends (Last 30 Days)
                                    </CardTitle>
                                    <Select 
                                        value={getEffectiveFrequency('daily')} 
                                        onValueChange={(val) => handleChartFrequencyChange('daily', val as typeof globalFrequency)}
                                    >
                                        <SelectTrigger className="w-[140px] h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="weekly">Weekly</SelectItem>
                                            <SelectItem value="monthly">Monthly</SelectItem>
                                            <SelectItem value="quarterly">Quarterly</SelectItem>
                                            <SelectItem value="yearly">Yearly</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={350}>
                                        <AreaChart data={dailyTrends}>
                                            <defs>
                                                <linearGradient id="colorPayments" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#22c55e" stopOpacity={0.8}/>
                                                    <stop offset="95%" stopColor="#22c55e" stopOpacity={0}/>
                                                </linearGradient>
                                                <linearGradient id="colorPayroll" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8}/>
                                                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis 
                                                dataKey="date" 
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                angle={-45}
                                                textAnchor="end"
                                                height={60}
                                            />
                                            <YAxis 
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(value) => formatCurrency(value)}
                                            />
                                            <Tooltip 
                                                formatter={(value: number | undefined) => value !== undefined ? formatCurrency(value) : ''}
                                                contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                            />
                                            <Legend />
                                            <Area 
                                                type="monotone" 
                                                dataKey="payments" 
                                                stackId="1"
                                                stroke="#22c55e" 
                                                fill="url(#colorPayments)" 
                                                name="Payments"
                                            />
                                            <Area 
                                                type="monotone" 
                                                dataKey="payroll" 
                                                stackId="1"
                                                stroke="#3b82f6" 
                                                fill="url(#colorPayroll)" 
                                                name="Payroll"
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        )}

                        {/* Weekly Trends Chart - Line Chart */}
                        {weeklyTrends.length > 0 && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <BarChartIcon className="h-5 w-5" />
                                        Weekly Trends (Last 12 Weeks)
                                    </CardTitle>
                                    <Select 
                                        value={getEffectiveFrequency('weekly')} 
                                        onValueChange={(val) => handleChartFrequencyChange('weekly', val as typeof globalFrequency)}
                                    >
                                        <SelectTrigger className="w-[140px] h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="weekly">Weekly</SelectItem>
                                            <SelectItem value="monthly">Monthly</SelectItem>
                                            <SelectItem value="quarterly">Quarterly</SelectItem>
                                            <SelectItem value="yearly">Yearly</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={350}>
                                        <LineChart data={weeklyTrends}>
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis 
                                                dataKey="week" 
                                                className="text-xs"
                                                tick={{ fontSize: 11 }}
                                                angle={-45}
                                                textAnchor="end"
                                                height={80}
                                            />
                                            <YAxis 
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(value) => formatCurrency(value)}
                                            />
                                            <Tooltip 
                                                formatter={(value: number | undefined) => value !== undefined ? formatCurrency(value) : ''}
                                                contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                            />
                                            <Legend />
                                            <Line 
                                                type="monotone" 
                                                dataKey="payments" 
                                                stroke="#22c55e" 
                                                strokeWidth={3}
                                                dot={{ fill: '#22c55e', r: 5 }}
                                                activeDot={{ r: 7 }}
                                                name="Payments"
                                            />
                                            <Line 
                                                type="monotone" 
                                                dataKey="payroll" 
                                                stroke="#3b82f6" 
                                                strokeWidth={3}
                                                dot={{ fill: '#3b82f6', r: 5 }}
                                                activeDot={{ r: 7 }}
                                                name="Payroll"
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        )}

                        {/* Success Rate Trends */}
                        {successRateTrends.length > 0 && (() => {
                            const chartFreq = getEffectiveFrequency('successRate');
                            return (
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Target className="h-5 w-5" />
                                            {chartFreq === 'weekly' && 'Success Rate Trends (Last 12 Weeks)'}
                                            {chartFreq === 'monthly' && 'Success Rate Trends (Last 6 Months)'}
                                            {chartFreq === 'quarterly' && 'Success Rate Trends (Last 8 Quarters)'}
                                            {chartFreq === 'yearly' && 'Success Rate Trends (Last 5 Years)'}
                                        </CardTitle>
                                        <Select 
                                            value={chartFreq} 
                                            onValueChange={(val) => handleChartFrequencyChange('successRate', val as typeof globalFrequency)}
                                        >
                                            <SelectTrigger className="w-[140px] h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weekly">Weekly</SelectItem>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="quarterly">Quarterly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={350}>
                                            <LineChart data={successRateTrends}>
                                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                                <XAxis 
                                                    dataKey={chartFreq === 'weekly' ? 'week' : chartFreq === 'monthly' ? 'month' : chartFreq === 'quarterly' ? 'quarter' : 'year'} 
                                                    className="text-xs"
                                                    tick={{ fontSize: 12 }}
                                                    angle={chartFreq === 'weekly' ? -45 : 0}
                                                    textAnchor={chartFreq === 'weekly' ? 'end' : 'middle'}
                                                    height={chartFreq === 'weekly' ? 80 : 30}
                                                />
                                            <YAxis 
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                domain={[0, 100]}
                                                tickFormatter={(value) => `${value}%`}
                                            />
                                            <Tooltip 
                                                formatter={(value: number | undefined) => value !== undefined ? `${value}%` : ''}
                                                contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                            />
                                            <Legend />
                                            <Line 
                                                type="monotone" 
                                                dataKey="success_rate" 
                                                stroke="#22c55e" 
                                                strokeWidth={3}
                                                dot={{ fill: '#22c55e', r: 5 }}
                                                activeDot={{ r: 7 }}
                                                name="Success Rate (%)"
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                            );
                        })()}

                        {/* Main Trends Chart */}
                        {monthlyTrends.length > 0 && (() => {
                            const chartFreq = getEffectiveFrequency('trends');
                            return (
                                <Card>
                                    <CardHeader className="flex flex-row items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <BarChartIcon className="h-5 w-5" />
                                            {chartFreq === 'weekly' && 'Weekly Trends (Last 12 Weeks)'}
                                            {chartFreq === 'monthly' && 'Monthly Trends (Last 6 Months)'}
                                            {chartFreq === 'quarterly' && 'Quarterly Trends (Last 8 Quarters)'}
                                            {chartFreq === 'yearly' && 'Yearly Trends (Last 5 Years)'}
                                        </CardTitle>
                                        <Select 
                                            value={chartFreq} 
                                            onValueChange={(val) => handleChartFrequencyChange('trends', val as typeof globalFrequency)}
                                        >
                                            <SelectTrigger className="w-[140px] h-8 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weekly">Weekly</SelectItem>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="quarterly">Quarterly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={350}>
                                            <RechartsBarChart data={monthlyTrends}>
                                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                                <XAxis 
                                                    dataKey={chartFreq === 'weekly' ? 'week' : chartFreq === 'monthly' ? 'month' : chartFreq === 'quarterly' ? 'quarter' : 'year'} 
                                                    className="text-xs"
                                                    tick={{ fontSize: 12 }}
                                                    angle={chartFreq === 'weekly' ? -45 : 0}
                                                    textAnchor={chartFreq === 'weekly' ? 'end' : 'middle'}
                                                    height={chartFreq === 'weekly' ? 80 : 30}
                                                />
                                                <YAxis 
                                                    className="text-xs"
                                                    tick={{ fontSize: 12 }}
                                                    tickFormatter={(value) => formatCurrency(value)}
                                                />
                                                <Tooltip 
                                                    formatter={(value: number | undefined) => value !== undefined ? formatCurrency(value) : ''}
                                                    contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                                />
                                                <Legend />
                                                <Bar dataKey="payments" fill="#22c55e" name="Payments" radius={[4, 4, 0, 0]} />
                                                <Bar dataKey="payroll" fill="#3b82f6" name="Payroll" radius={[4, 4, 0, 0]} />
                                            </RechartsBarChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                </Card>
                            );
                        })()}

                        {/* Top Recipients */}
                        {topRecipients.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Users className="h-5 w-5" />
                                        Top Recipients (Last 30 Days)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={400}>
                                        <RechartsBarChart 
                                            data={topRecipients} 
                                            layout="vertical"
                                            margin={{ top: 5, right: 30, left: 100, bottom: 5 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis 
                                                type="number"
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(value) => formatCurrency(value)}
                                            />
                                            <YAxis 
                                                type="category" 
                                                dataKey="name" 
                                                className="text-xs"
                                                tick={{ fontSize: 11 }}
                                                width={90}
                                            />
                                            <Tooltip 
                                                formatter={(value: number | undefined) => value !== undefined ? formatCurrency(value) : ''}
                                                contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                            />
                                            <Bar dataKey="total_amount" fill="#22c55e" radius={[0, 4, 4, 0]} name="Total Amount">
                                                {topRecipients.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill="#22c55e" />
                                                ))}
                                            </Bar>
                                        </RechartsBarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        )}

                        {/* Top Employees */}
                        {topEmployees.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Users className="h-5 w-5" />
                                        Top Employees (Last 30 Days)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ResponsiveContainer width="100%" height={400}>
                                        <RechartsBarChart 
                                            data={topEmployees} 
                                            layout="vertical"
                                            margin={{ top: 5, right: 30, left: 100, bottom: 5 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                            <XAxis 
                                                type="number"
                                                className="text-xs"
                                                tick={{ fontSize: 12 }}
                                                tickFormatter={(value) => formatCurrency(value)}
                                            />
                                            <YAxis 
                                                type="category" 
                                                dataKey="name" 
                                                className="text-xs"
                                                tick={{ fontSize: 11 }}
                                                width={90}
                                            />
                                            <Tooltip 
                                                formatter={(value: number | undefined) => value !== undefined ? formatCurrency(value) : ''}
                                                contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                            />
                                            <Bar dataKey="total_amount" fill="#3b82f6" radius={[0, 4, 4, 0]} name="Total Amount">
                                                {topEmployees.map((entry, index) => (
                                                    <Cell key={`cell-${index}`} fill="#3b82f6" />
                                                ))}
                                            </Bar>
                                        </RechartsBarChart>
                                    </ResponsiveContainer>
                                </CardContent>
                            </Card>
                        )}


                        {/* Status Breakdown & Job Type Comparison */}
                        <div className="grid gap-4 md:grid-cols-2">
                            {/* Status Breakdown Chart */}
                            {statusBreakdown && (
                                <Card>
                                    <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <PieChartIcon className="h-5 w-5" />
                                        Job Status Breakdown
                                    </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={350}>
                                            <RechartsPieChart>
                                                <Pie
                                                    data={Object.entries(statusBreakdown).map(([status, count]) => {
                                                        const statusLabels: Record<string, string> = {
                                                            succeeded: 'Succeeded',
                                                            failed: 'Failed',
                                                            pending: 'Pending',
                                                            processing: 'Processing',
                                                        };
                                                        return {
                                                            name: statusLabels[status] || status,
                                                            value: count,
                                                            status
                                                        };
                                                    })}
                                                    cx="50%"
                                                    cy="50%"
                                                    labelLine={false}
                                                    outerRadius={100}
                                                    fill="#8884d8"
                                                    dataKey="value"
                                                >
                                                    {Object.entries(statusBreakdown).map(([status], index) => {
                                                        const COLORS = ['#22c55e', '#ef4444', '#eab308', '#3b82f6'];
                                                        return <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />;
                                                    })}
                                                </Pie>
                                                <Tooltip 
                                                    contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                                />
                                                <Legend />
                                            </RechartsPieChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Payment vs Payroll Comparison */}
                            {jobTypeComparison && (
                                <Card>
                                    <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <BarChartIcon2 className="h-5 w-5" />
                                        Payment vs Payroll Comparison
                                    </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ResponsiveContainer width="100%" height={350}>
                                            <RechartsBarChart 
                                                data={[
                                                    { name: 'Payment Jobs', value: jobTypeComparison.payments, type: 'Payment' },
                                                    { name: 'Payroll Jobs', value: jobTypeComparison.payroll, type: 'Payroll' }
                                                ]}
                                            >
                                                <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                                <XAxis 
                                                    dataKey="name" 
                                                    className="text-xs"
                                                    tick={{ fontSize: 12 }}
                                                />
                                                <YAxis 
                                                    className="text-xs"
                                                    tick={{ fontSize: 12 }}
                                                />
                                                <Tooltip 
                                                    contentStyle={{ backgroundColor: 'hsl(var(--background))', border: '1px solid hsl(var(--border))' }}
                                                />
                                                <Bar dataKey="value" radius={[4, 4, 0, 0]}>
                                                    <Cell fill="#22c55e" />
                                                    <Cell fill="#3b82f6" />
                                                </Bar>
                                            </RechartsBarChart>
                                        </ResponsiveContainer>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}