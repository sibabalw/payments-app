import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    Edit,
    FileText,
    RefreshCw,
    Users,
    Calendar,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Compliance', href: '/compliance' },
    { title: 'IRP5 Certificates', href: '/compliance/irp5' },
];

interface TaxYear {
    value: string;
    label: string;
}

interface Employee {
    id: number;
    name: string;
    id_number: string | null;
    tax_number: string | null;
    total_gross: number;
    total_paye: number;
    periods: number;
    irp5_status: string;
    submission_id: number | null;
}

interface IRP5IndexProps {
    business: {
        id: number;
        name: string;
    } | null;
    employees: Employee[];
    taxYears: TaxYear[];
    selectedTaxYear: string;
    generatedCount: number;
    pendingCount: number;
}

export default function IRP5Index({
    business,
    employees,
    taxYears,
    selectedTaxYear,
    generatedCount,
    pendingCount,
}: IRP5IndexProps) {
    const [taxYear, setTaxYear] = useState(selectedTaxYear);
    const [selectedEmployees, setSelectedEmployees] = useState<number[]>([]);
    const [generating, setGenerating] = useState(false);
    const [generatingBulk, setGeneratingBulk] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const handleTaxYearChange = (value: string) => {
        setTaxYear(value);
        setSelectedEmployees([]);
        router.get('/compliance/irp5', { tax_year: value }, { preserveState: true });
    };

    const handleGenerateIndividual = (employeeId: number) => {
        setGenerating(true);
        router.post(`/compliance/irp5/generate/${employeeId}`, { tax_year: taxYear }, {
            onFinish: () => setGenerating(false),
        });
    };

    const handleGenerateBulk = () => {
        setGeneratingBulk(true);
        router.post('/compliance/irp5/generate-bulk', { tax_year: taxYear }, {
            onFinish: () => setGeneratingBulk(false),
        });
    };

    const handleSelectAll = () => {
        if (selectedEmployees.length === employees.length) {
            setSelectedEmployees([]);
        } else {
            setSelectedEmployees(employees.map(e => e.id));
        }
    };

    const handleSelectEmployee = (employeeId: number) => {
        setSelectedEmployees(prev => 
            prev.includes(employeeId)
                ? prev.filter(id => id !== employeeId)
                : [...prev, employeeId]
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'submitted':
                return <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Submitted</Badge>;
            case 'generated':
                return <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Generated</Badge>;
            case 'pending':
                return <Badge variant="secondary">Pending</Badge>;
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    if (!business) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="IRP5 Certificates" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                            <h2 className="text-xl font-semibold mb-2">No Business Selected</h2>
                            <p className="text-muted-foreground text-center mb-4">
                                Please select a business to manage IRP5 certificates.
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

    // Counts are pre-computed by backend - no frontend filtering needed

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="IRP5 Certificates" />
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
                            <h1 className="text-2xl font-bold">IRP5 Tax Certificates</h1>
                            <p className="text-muted-foreground">
                                Generate annual employee tax certificates for {business.name}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Tax Year Selection */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Tax Year Selection
                        </CardTitle>
                        <CardDescription>
                            Select a tax year to view and generate IRP5 certificates
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4 items-end">
                            <div className="flex-1 min-w-[200px] max-w-[300px]">
                                <label className="text-sm font-medium mb-2 block">Tax Year</label>
                                <Select value={taxYear} onValueChange={handleTaxYearChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select tax year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {taxYears.map((ty) => (
                                            <SelectItem key={ty.value} value={ty.value}>
                                                {ty.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button 
                                onClick={handleGenerateBulk} 
                                disabled={generatingBulk || employees.length === 0}
                            >
                                {generatingBulk ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Generating...
                                    </>
                                ) : (
                                    <>
                                        <Users className="mr-2 h-4 w-4" />
                                        Generate All Certificates
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">Tax Year</p>
                            <p className="text-2xl font-bold">{taxYear}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">Total Employees</p>
                            <p className="text-2xl font-bold">{employees.length}</p>
                        </CardContent>
                    </Card>
                    <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">Certificates Generated</p>
                            <p className="text-2xl font-bold text-green-600">{generatedCount}</p>
                        </CardContent>
                    </Card>
                    <Card className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950">
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">Pending</p>
                            <p className="text-2xl font-bold text-amber-600">{pendingCount}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Employee List */}
                {employees.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Employees ({employees.length})
                            </CardTitle>
                            <CardDescription>
                                Generate or download IRP5 certificates for each employee
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="text-left p-3 w-10">
                                                <Checkbox 
                                                    checked={selectedEmployees.length === employees.length && employees.length > 0}
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </th>
                                            <th className="text-left p-3">Employee</th>
                                            <th className="text-left p-3">Tax Number</th>
                                            <th className="text-left p-3">ID Number</th>
                                            <th className="text-right p-3">Total Gross</th>
                                            <th className="text-right p-3">Total PAYE</th>
                                            <th className="text-center p-3">Periods</th>
                                            <th className="text-center p-3">Status</th>
                                            <th className="text-right p-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.map((employee) => (
                                            <tr key={employee.id} className="border-b hover:bg-muted/30">
                                                <td className="p-3">
                                                    <Checkbox 
                                                        checked={selectedEmployees.includes(employee.id)}
                                                        onCheckedChange={() => handleSelectEmployee(employee.id)}
                                                    />
                                                </td>
                                                <td className="p-3 font-medium">{employee.name}</td>
                                                <td className="p-3 font-mono text-xs">
                                                    {employee.tax_number || <span className="text-muted-foreground">Not set</span>}
                                                </td>
                                                <td className="p-3 font-mono text-xs">
                                                    {employee.id_number || <span className="text-muted-foreground">Not set</span>}
                                                </td>
                                                <td className="p-3 text-right font-medium">
                                                    {formatCurrency(employee.total_gross)}
                                                </td>
                                                <td className="p-3 text-right text-blue-600 font-medium">
                                                    {formatCurrency(employee.total_paye)}
                                                </td>
                                                <td className="p-3 text-center">{employee.periods}</td>
                                                <td className="p-3 text-center">
                                                    {getStatusBadge(employee.irp5_status)}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {employee.irp5_status === 'pending' ? (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleGenerateIndividual(employee.id)}
                                                                disabled={generating}
                                                            >
                                                                <FileText className="mr-2 h-4 w-4" />
                                                                Generate
                                                            </Button>
                                                        ) : (
                                                            <>
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handleGenerateIndividual(employee.id)}
                                                                    disabled={generating}
                                                                >
                                                                    <RefreshCw className="h-4 w-4" />
                                                                </Button>
                                                                {employee.submission_id && employee.irp5_status !== 'submitted' && (
                                                                    <Button variant="outline" size="sm" asChild>
                                                                        <Link href={`/compliance/irp5/${employee.submission_id}/edit`}>
                                                                            <Edit className="h-4 w-4" />
                                                                        </Link>
                                                                    </Button>
                                                                )}
                                                                {employee.submission_id && (
                                                                    <Button variant="default" size="sm" asChild>
                                                                        <a href={`/compliance/irp5/${employee.submission_id}/download`}>
                                                                            <Download className="mr-2 h-4 w-4" />
                                                                            PDF
                                                                        </a>
                                                                    </Button>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Users className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No Employees Found</h3>
                            <p className="text-muted-foreground text-center">
                                No employees with payroll data found for the selected tax year.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Information Card */}
                <Card className="bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-blue-800 dark:text-blue-200">
                            <CheckCircle2 className="h-5 w-5" />
                            About IRP5 Certificates
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-blue-800 dark:text-blue-200">
                        <ul className="space-y-2 text-sm">
                            <li>
                                <strong>Tax Year:</strong> South African tax year runs from 1 March to end of February.
                            </li>
                            <li>
                                <strong>IRP5:</strong> Employee tax certificate showing income, deductions, and tax paid during the tax year.
                            </li>
                            <li>
                                <strong>Deadline:</strong> IRP5 certificates must be submitted to SARS via EMP501 reconciliation by 31 May each year.
                            </li>
                            <li>
                                <strong>Terminated Employees:</strong> IRP5 certificates can be generated at any time for employees who have left the company.
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
