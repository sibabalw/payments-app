import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    CheckCircle2,
    Download,
    Edit,
    FileSpreadsheet,
    Plus,
    RefreshCw,
    Shield,
    AlertCircle,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Compliance', href: '/compliance' },
    { title: 'EMP201 Helper', href: '/compliance/emp201' },
];

interface Submission {
    id: number;
    period: string;
    period_display: string;
    status: string;
    data: any;
    submitted_at: string | null;
    created_at: string;
}

interface Period {
    value: string;
    label: string;
}

interface Employee {
    employee_id: number;
    employee_name: string;
    id_number: string;
    tax_number: string;
    gross_salary: number;
    paye: number;
    uif_employee: number;
    uif_employer: number;
    sdl: number;
}

interface ChecklistItem {
    item: string;
    amount: number;
    status: string;
}

interface PreviewData {
    business: {
        name: string;
        registration_number: string;
        paye_reference: string;
        sdl_reference: string;
        uif_reference: string;
    };
    period: string;
    period_display: string;
    submission_deadline: string;
    employees: Employee[];
    totals: {
        employees_count: number;
        total_gross: number;
        total_paye: number;
        total_uif_employee: number;
        total_uif_employer: number;
        total_uif: number;
        total_sdl: number;
        total_liability: number;
    };
    generated_at: string;
}

interface EMP201IndexProps {
    business: {
        id: number;
        name: string;
        tax_id: string | null;
    } | null;
    submissions: Submission[];
    pendingPeriods: Period[];
    previewData: PreviewData | null;
    checklist: ChecklistItem[];
    selectedPeriod: string | null;
}

export default function EMP201Index({
    business,
    submissions,
    pendingPeriods,
    previewData,
    checklist,
    selectedPeriod,
}: EMP201IndexProps) {
    const [period, setPeriod] = useState(selectedPeriod || '');
    const [generating, setGenerating] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const handlePeriodChange = (value: string) => {
        setPeriod(value);
        router.get('/compliance/emp201', { period: value }, { preserveState: true });
    };

    const handleGenerate = () => {
        if (!period) return;
        setGenerating(true);
        router.post('/compliance/emp201/generate', { period }, {
            onFinish: () => setGenerating(false),
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'submitted':
                return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Submitted</Badge>;
            case 'generated':
                return <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Generated</Badge>;
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const getChecklistIcon = (status: string) => {
        switch (status) {
            case 'ready':
                return <CheckCircle className="h-5 w-5 text-green-500" />;
            case 'warning':
                return <AlertCircle className="h-5 w-5 text-amber-500" />;
            default:
                return <CheckCircle className="h-5 w-5 text-muted-foreground" />;
        }
    };

    if (!business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="EMP201 Helper" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Shield className="h-12 w-12 text-muted-foreground mb-4" />
                            <h2 className="text-xl font-semibold mb-2">No Business Selected</h2>
                            <p className="text-muted-foreground text-center mb-4">
                                Please select a business to manage EMP201 submissions.
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
            <Head title="EMP201 Helper" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/compliance">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">EMP201 Monthly Submission</h1>
                            <p className="text-muted-foreground">
                                Prepare PAYE, UIF, and SDL data for SARS eFiling
                            </p>
                        </div>
                    </div>
                </div>

                {/* Generate New Submission */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="h-5 w-5" />
                            Prepare EMP201 Data
                        </CardTitle>
                        <CardDescription>
                            Select a period to calculate tax liabilities for SARS submission
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4 items-end">
                            <div className="flex-1 min-w-[200px]">
                                <label className="text-sm font-medium mb-2 block">Period</label>
                                <Select value={period} onValueChange={handlePeriodChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select period" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pendingPeriods.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                        {submissions.map((s) => (
                                            <SelectItem key={s.period} value={s.period}>
                                                {s.period_display} (already generated)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button onClick={handleGenerate} disabled={!period || generating}>
                                {generating ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Calculating...
                                    </>
                                ) : (
                                    <>
                                        <FileSpreadsheet className="mr-2 h-4 w-4" />
                                        Generate EMP201 Data
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Preview Data */}
                {previewData && (
                    <>
                        {/* Submission Checklist */}
                        {checklist.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CheckCircle2 className="h-5 w-5" />
                                        Submission Checklist
                                    </CardTitle>
                                    <CardDescription>
                                        Deadline: {formatDate(previewData.submission_deadline)}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {checklist.map((item, index) => (
                                            <div
                                                key={index}
                                                className="flex items-center justify-between p-3 bg-muted/30 rounded-lg"
                                            >
                                                <div className="flex items-center gap-3">
                                                    {getChecklistIcon(item.status)}
                                                    <span className="font-medium">{item.item}</span>
                                                </div>
                                                <span className="font-bold">{formatCurrency(item.amount)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Tax Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Tax Summary: {previewData.period_display}</CardTitle>
                                <CardDescription>
                                    Generated: {formatDate(previewData.generated_at)}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Business Info */}
                                <div className="grid gap-4 md:grid-cols-4 p-4 bg-muted/50 rounded-lg">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Business Name</p>
                                        <p className="font-medium">{previewData.business.name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">PAYE Reference</p>
                                        <p className="font-medium">{previewData.business.paye_reference || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">SDL Reference</p>
                                        <p className="font-medium">{previewData.business.sdl_reference || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">UIF Reference</p>
                                        <p className="font-medium">{previewData.business.uif_reference || 'N/A'}</p>
                                    </div>
                                </div>

                                {/* Tax Breakdown */}
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                                        <CardContent className="pt-6">
                                            <p className="text-sm text-muted-foreground">PAYE</p>
                                            <p className="text-2xl font-bold text-blue-600">
                                                {formatCurrency(previewData.totals.total_paye)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">Pay As You Earn</p>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                                        <CardContent className="pt-6">
                                            <p className="text-sm text-muted-foreground">UIF Total</p>
                                            <p className="text-2xl font-bold text-green-600">
                                                {formatCurrency(previewData.totals.total_uif)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Employee: {formatCurrency(previewData.totals.total_uif_employee)} | 
                                                Employer: {formatCurrency(previewData.totals.total_uif_employer)}
                                            </p>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-950">
                                        <CardContent className="pt-6">
                                            <p className="text-sm text-muted-foreground">SDL</p>
                                            <p className="text-2xl font-bold text-purple-600">
                                                {formatCurrency(previewData.totals.total_sdl)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">Skills Development Levy</p>
                                        </CardContent>
                                    </Card>
                                    <Card className="border-primary bg-primary/5">
                                        <CardContent className="pt-6">
                                            <p className="text-sm text-muted-foreground">Total Liability</p>
                                            <p className="text-2xl font-bold text-primary">
                                                {formatCurrency(previewData.totals.total_liability)}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">Amount due to SARS</p>
                                        </CardContent>
                                    </Card>
                                </div>

                                {/* Summary Stats */}
                                <div className="grid gap-4 md:grid-cols-2 p-4 bg-muted/30 rounded-lg">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Number of Employees</p>
                                        <p className="text-xl font-bold">{previewData.totals.employees_count}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Total Gross Remuneration</p>
                                        <p className="text-xl font-bold">{formatCurrency(previewData.totals.total_gross)}</p>
                                    </div>
                                </div>

                                {/* Employee Details Table */}
                                {previewData.employees.length > 0 && (
                                    <div>
                                        <h4 className="font-semibold mb-3">Employee Breakdown</h4>
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b bg-muted/50">
                                                        <th className="text-left p-3">Tax Number</th>
                                                        <th className="text-left p-3">Employee</th>
                                                        <th className="text-right p-3">Gross</th>
                                                        <th className="text-right p-3">PAYE</th>
                                                        <th className="text-right p-3">UIF (Emp)</th>
                                                        <th className="text-right p-3">UIF (Er)</th>
                                                        <th className="text-right p-3">SDL</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {previewData.employees.map((employee) => (
                                                        <tr key={employee.employee_id} className="border-b hover:bg-muted/30">
                                                            <td className="p-3 font-mono text-xs">{employee.tax_number || 'N/A'}</td>
                                                            <td className="p-3 font-medium">{employee.employee_name}</td>
                                                            <td className="p-3 text-right">{formatCurrency(employee.gross_salary)}</td>
                                                            <td className="p-3 text-right text-blue-600">{formatCurrency(employee.paye)}</td>
                                                            <td className="p-3 text-right text-green-600">{formatCurrency(employee.uif_employee)}</td>
                                                            <td className="p-3 text-right text-green-600">{formatCurrency(employee.uif_employer)}</td>
                                                            <td className="p-3 text-right text-purple-600">{formatCurrency(employee.sdl)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}

                {/* Previous Submissions */}
                {submissions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5" />
                                Generated EMP201 Data
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-3">Period</th>
                                            <th className="text-left p-3">Status</th>
                                            <th className="text-right p-3">PAYE</th>
                                            <th className="text-right p-3">UIF</th>
                                            <th className="text-right p-3">SDL</th>
                                            <th className="text-right p-3">Total</th>
                                            <th className="text-left p-3">Generated</th>
                                            <th className="text-right p-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {submissions.map((submission) => (
                                            <tr key={submission.id} className="border-b hover:bg-muted/30">
                                                <td className="p-3 font-medium">{submission.period_display}</td>
                                                <td className="p-3">{getStatusBadge(submission.status)}</td>
                                                <td className="p-3 text-right">
                                                    {formatCurrency(submission.data?.totals?.total_paye || 0)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    {formatCurrency(submission.data?.totals?.total_uif || 0)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    {formatCurrency(submission.data?.totals?.total_sdl || 0)}
                                                </td>
                                                <td className="p-3 text-right font-semibold">
                                                    {formatCurrency(submission.data?.totals?.total_liability || 0)}
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {formatDate(submission.created_at)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {submission.status !== 'submitted' && (
                                                            <Button variant="outline" size="sm" asChild>
                                                                <Link href={`/compliance/emp201/${submission.id}/edit`}>
                                                                    <Edit className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        <Button variant="outline" size="sm" asChild>
                                                            <a href={`/compliance/emp201/${submission.id}/download`}>
                                                                <Download className="mr-2 h-4 w-4" />
                                                                CSV
                                                            </a>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Empty State */}
                {submissions.length === 0 && !previewData && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <FileSpreadsheet className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No EMP201 Data Yet</h3>
                            <p className="text-muted-foreground text-center">
                                Select a period above to calculate your tax liabilities.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
