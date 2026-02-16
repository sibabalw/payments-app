import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    FileCheck,
    FileSpreadsheet,
    FileText,
    Shield,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Compliance', href: '/compliance' },
];

interface Submission {
    id: number;
    type: string;
    type_display: string;
    period: string;
    status: string;
    submitted_at: string | null;
    created_at: string;
}

interface PendingItem {
    type: string;
    title: string;
    count: number;
    periods: string[];
}

interface Deadline {
    title: string;
    description: string;
    deadline: string;
    type: string;
}

interface ComplianceIndexProps {
    business: {
        id: number;
        name: string;
        tax_id: string | null;
        registration_number: string | null;
    } | null;
    submissions: Submission[];
    pendingItems: PendingItem[];
    deadlines: Deadline[];
    currentTaxYear: string;
    currentMonth: string;
}

export default function ComplianceIndex({
    business,
    submissions,
    pendingItems,
    deadlines,
    currentTaxYear,
    currentMonth,
}: ComplianceIndexProps) {
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getDaysUntil = (deadline: string) => {
        const days = Math.ceil(
            (new Date(deadline).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24)
        );
        return days;
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'submitted':
                return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Submitted</Badge>;
            case 'generated':
                return <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Generated</Badge>;
            case 'draft':
                return <Badge className="bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">Draft</Badge>;
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    if (!business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Compliance" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                            <h2 className="text-xl font-semibold mb-2">No Business Selected</h2>
                            <p className="text-muted-foreground text-center mb-4">
                                Please select a business to view compliance information.
                            </p>
                            <Button asChild>
                                <Link href="/businesses">Go to Businesses</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Compliance Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Compliance Dashboard</h1>
                        <p className="text-muted-foreground">
                            Manage tax submissions and compliance for {business.name}
                        </p>
                    </div>
                </div>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Current Tax Year</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{currentTaxYear}</div>
                            <p className="text-xs text-muted-foreground">March to February</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Items</CardTitle>
                            <AlertCircle className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {pendingItems.reduce((sum, item) => sum + item.count, 0)}
                            </div>
                            <p className="text-xs text-muted-foreground">Awaiting submission</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Submissions</CardTitle>
                            <FileCheck className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{submissions.length}</div>
                            <p className="text-xs text-muted-foreground">All time</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">PAYE Reference</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold truncate">{business.tax_id || 'Not set'}</div>
                            <p className="text-xs text-muted-foreground">SARS registration</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Upcoming Deadlines */}
                {deadlines.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Upcoming Deadlines
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                {deadlines.map((deadline, index) => {
                                    const daysUntil = getDaysUntil(deadline.deadline);
                                    const isUrgent = daysUntil <= 7;
                                    return (
                                        <div
                                            key={index}
                                            className={`p-4 rounded-lg border ${
                                                isUrgent
                                                    ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                                    : 'border-border bg-muted/30'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between mb-2">
                                                <h4 className="font-semibold text-sm">{deadline.title}</h4>
                                                <Badge variant={isUrgent ? 'destructive' : 'secondary'}>
                                                    {daysUntil} days
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground mb-2">
                                                {deadline.description}
                                            </p>
                                            <p className="text-xs font-medium">
                                                Due: {formatDate(deadline.deadline)}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Pending Items Alert */}
                {pendingItems.length > 0 && (
                    <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-amber-800 dark:text-amber-200">
                                <AlertCircle className="h-5 w-5" />
                                Pending Submissions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                {pendingItems.map((item, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between p-3 rounded-lg bg-white dark:bg-background border"
                                    >
                                        <div>
                                            <h4 className="font-medium">{item.title}</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {item.count} period{item.count > 1 ? 's' : ''} pending
                                            </p>
                                        </div>
                                        <Button asChild size="sm">
                                            <Link href={`/compliance/${item.type}`}>
                                                Review
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Quick Actions */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="hover:border-primary/50 transition-colors cursor-pointer">
                        <Link href="/compliance/uif">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Users className="h-5 w-5 text-blue-500" />
                                    UIF Declarations
                                </CardTitle>
                                <CardDescription>UI-19 monthly reports</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Generate and submit UIF contribution declarations to the Department of Labour.
                                </p>
                            </CardContent>
                        </Link>
                    </Card>

                    <Card className="hover:border-primary/50 transition-colors cursor-pointer">
                        <Link href="/compliance/emp201">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileSpreadsheet className="h-5 w-5 text-green-500" />
                                    EMP201 Helper
                                </CardTitle>
                                <CardDescription>Monthly tax submission</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Prepare PAYE, UIF, and SDL data for SARS monthly submission.
                                </p>
                            </CardContent>
                        </Link>
                    </Card>

                    <Card className="hover:border-primary/50 transition-colors cursor-pointer">
                        <Link href="/compliance/irp5">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="h-5 w-5 text-purple-500" />
                                    IRP5 Certificates
                                </CardTitle>
                                <CardDescription>Employee tax certificates</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Generate annual IRP5 tax certificates for employees.
                                </p>
                            </CardContent>
                        </Link>
                    </Card>

                    <Card className="hover:border-primary/50 transition-colors cursor-pointer">
                        <Link href="/compliance/sars-export">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Shield className="h-5 w-5 text-amber-500" />
                                    SARS Export
                                </CardTitle>
                                <CardDescription>Download for eFiling</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Export compliance data in SARS-compatible formats for eFiling.
                                </p>
                            </CardContent>
                        </Link>
                    </Card>
                </div>

                {/* Recent Submissions */}
                {submissions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5" />
                                Recent Submissions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-3">Type</th>
                                            <th className="text-left p-3">Period</th>
                                            <th className="text-left p-3">Status</th>
                                            <th className="text-left p-3">Created</th>
                                            <th className="text-left p-3">Submitted</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {submissions.map((submission) => (
                                            <tr key={submission.id} className="border-b hover:bg-muted/50">
                                                <td className="p-3 font-medium">{submission.type_display}</td>
                                                <td className="p-3">{submission.period}</td>
                                                <td className="p-3">{getStatusBadge(submission.status)}</td>
                                                <td className="p-3 text-muted-foreground">
                                                    {formatDate(submission.created_at)}
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {submission.submitted_at
                                                        ? formatDate(submission.submitted_at)
                                                        : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
