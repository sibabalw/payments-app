import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    Edit,
    FileSpreadsheet,
    Plus,
    RefreshCw,
    Users,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Compliance', href: '/compliance' },
    { title: 'UIF Declarations', href: '/compliance/uif' },
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
    gross_remuneration: number;
    uif_employee: number;
    uif_employer: number;
    total_uif: number;
}

interface PreviewData {
    business: {
        name: string;
        registration_number: string;
        uif_reference: string;
    };
    period: string;
    period_display: string;
    employees: Employee[];
    totals: {
        total_employees: number;
        total_gross_remuneration: number;
        total_uif_employee: number;
        total_uif_employer: number;
        total_uif_contribution: number;
    };
    generated_at: string;
}

interface UIFIndexProps {
    business: {
        id: number;
        name: string;
    } | null;
    submissions: Submission[];
    pendingPeriods: Period[];
    previewData: PreviewData | null;
    selectedPeriod: string | null;
}

export default function UIFIndex({
    business,
    submissions,
    pendingPeriods,
    previewData,
    selectedPeriod,
}: UIFIndexProps) {
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
        router.get('/compliance/uif', { period: value }, { preserveState: true });
    };

    const handleGenerate = () => {
        if (!period) return;
        setGenerating(true);
        router.post('/compliance/uif/generate', { period }, {
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

    if (!business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="UIF Declarations" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Users className="h-12 w-12 text-muted-foreground mb-4" />
                            <h2 className="text-xl font-semibold mb-2">No Business Selected</h2>
                            <p className="text-muted-foreground text-center mb-4">
                                Please select a business to manage UIF declarations.
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
            <Head title="UIF Declarations" />
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
                            <h1 className="text-2xl font-bold">UIF Declarations (UI-19)</h1>
                            <p className="text-muted-foreground">
                                Monthly contribution reports for {business.name}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Generate New Declaration */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Plus className="h-5 w-5" />
                            Generate UI-19 Declaration
                        </CardTitle>
                        <CardDescription>
                            Select a period to generate a new UIF contribution declaration
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
                                        Generating...
                                    </>
                                ) : (
                                    <>
                                        <FileSpreadsheet className="mr-2 h-4 w-4" />
                                        Generate Declaration
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Preview Data */}
                {previewData && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Preview: {previewData.period_display}</CardTitle>
                            <CardDescription>
                                Review the data before generating the declaration
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Business Info */}
                            <div className="grid gap-4 md:grid-cols-3 p-4 bg-muted/50 rounded-lg">
                                <div>
                                    <p className="text-sm text-muted-foreground">Business Name</p>
                                    <p className="font-medium">{previewData.business.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Registration Number</p>
                                    <p className="font-medium">{previewData.business.registration_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">UIF Reference</p>
                                    <p className="font-medium">{previewData.business.uif_reference || 'N/A'}</p>
                                </div>
                            </div>

                            {/* Totals */}
                            <div className="grid gap-4 md:grid-cols-5">
                                <Card>
                                    <CardContent className="pt-6">
                                        <p className="text-sm text-muted-foreground">Employees</p>
                                        <p className="text-2xl font-bold">{previewData.totals.total_employees}</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-6">
                                        <p className="text-sm text-muted-foreground">Gross Remuneration</p>
                                        <p className="text-2xl font-bold">{formatCurrency(previewData.totals.total_gross_remuneration)}</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-6">
                                        <p className="text-sm text-muted-foreground">Employee UIF</p>
                                        <p className="text-2xl font-bold text-blue-600">{formatCurrency(previewData.totals.total_uif_employee)}</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-6">
                                        <p className="text-sm text-muted-foreground">Employer UIF</p>
                                        <p className="text-2xl font-bold text-blue-600">{formatCurrency(previewData.totals.total_uif_employer)}</p>
                                    </CardContent>
                                </Card>
                                <Card className="bg-primary/5">
                                    <CardContent className="pt-6">
                                        <p className="text-sm text-muted-foreground">Total UIF</p>
                                        <p className="text-2xl font-bold text-primary">{formatCurrency(previewData.totals.total_uif_contribution)}</p>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Employee Table */}
                            {previewData.employees.length > 0 && (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="text-left p-3">ID Number</th>
                                                <th className="text-left p-3">Employee Name</th>
                                                <th className="text-right p-3">Gross Remuneration</th>
                                                <th className="text-right p-3">Employee UIF</th>
                                                <th className="text-right p-3">Employer UIF</th>
                                                <th className="text-right p-3">Total UIF</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {previewData.employees.map((employee) => (
                                                <tr key={employee.employee_id} className="border-b hover:bg-muted/30">
                                                    <td className="p-3 font-mono text-xs">{employee.id_number || 'N/A'}</td>
                                                    <td className="p-3 font-medium">{employee.employee_name}</td>
                                                    <td className="p-3 text-right">{formatCurrency(employee.gross_remuneration)}</td>
                                                    <td className="p-3 text-right text-blue-600">{formatCurrency(employee.uif_employee)}</td>
                                                    <td className="p-3 text-right text-blue-600">{formatCurrency(employee.uif_employer)}</td>
                                                    <td className="p-3 text-right font-semibold">{formatCurrency(employee.total_uif)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Previous Submissions */}
                {submissions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5" />
                                Generated Declarations
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-3">Period</th>
                                            <th className="text-left p-3">Status</th>
                                            <th className="text-right p-3">Employees</th>
                                            <th className="text-right p-3">Total UIF</th>
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
                                                    {submission.data?.totals?.total_employees || 0}
                                                </td>
                                                <td className="p-3 text-right font-semibold">
                                                    {formatCurrency(submission.data?.totals?.total_uif_contribution || 0)}
                                                </td>
                                                <td className="p-3 text-muted-foreground">
                                                    {formatDate(submission.created_at)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {submission.status !== 'submitted' && (
                                                            <Button variant="outline" size="sm" asChild>
                                                                <Link href={`/compliance/uif/${submission.id}/edit`}>
                                                                    <Edit className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        <Button variant="outline" size="sm" asChild>
                                                            <a href={`/compliance/uif/${submission.id}/download`}>
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
                            <h3 className="text-lg font-semibold mb-2">No Declarations Yet</h3>
                            <p className="text-muted-foreground text-center">
                                Select a period above to generate your first UI-19 declaration.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
