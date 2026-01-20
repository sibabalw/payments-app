import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Trash2, Plus, AlertCircle } from 'lucide-react';
import { useState } from 'react';

interface IncomeSource {
    code: string;
    description: string;
    amount: number;
}

interface DeductionItem {
    code: string;
    description: string;
    amount: number;
}

interface SubmissionData {
    certificate_number: string;
    tax_year: string;
    employee: {
        name: string;
        id_number: string;
        tax_number: string;
        email?: string;
        employment_start: string;
        employment_end: string;
    };
    employer: {
        name: string;
        trading_name: string;
        registration_number: string;
        paye_reference: string;
        address: string;
    };
    income: {
        sources: IncomeSource[];
        total: number;
    };
    deductions: {
        items: DeductionItem[];
        total: number;
    };
    summary: {
        gross_remuneration: number;
        total_deductions: number;
        taxable_income: number;
        paye_deducted: number;
        periods_paid: number;
    };
    generated_at: string;
}

interface Submission {
    id: number;
    period: string;
    employee_id: number;
    status: string;
    data: SubmissionData;
}

interface EditIRP5Props {
    submission: Submission;
}

const INCOME_CODES = [
    { code: '3601', description: 'Gross Remuneration' },
    { code: '3605', description: 'Bonus' },
    { code: '3606', description: 'Commission' },
    { code: '3607', description: 'Overtime' },
    { code: '3701', description: 'Travel Allowance' },
    { code: '3801', description: 'Fringe Benefits' },
    { code: '3810', description: 'Use of Motor Vehicle' },
    { code: '3815', description: 'Medical Aid Fringe Benefit' },
];

const DEDUCTION_CODES = [
    { code: '4102', description: 'PAYE' },
    { code: '4141', description: 'UIF' },
    { code: '4001', description: 'Pension Fund' },
    { code: '4005', description: 'Medical Aid' },
    { code: '4472', description: 'Retirement Annuity' },
    { code: '4474', description: 'Provident Fund' },
];

export default function EditIRP5({ submission }: EditIRP5Props) {
    const [employeeData, setEmployeeData] = useState(submission.data.employee);
    const [incomeSources, setIncomeSources] = useState<IncomeSource[]>(submission.data.income?.sources || []);
    const [deductionItems, setDeductionItems] = useState<DeductionItem[]>(submission.data.deductions?.items || []);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Compliance', href: '/compliance' },
        { title: 'IRP5 Certificates', href: '/compliance/irp5' },
        { title: `Edit ${submission.data.employee?.name || 'Certificate'}`, href: `/compliance/irp5/${submission.id}/edit` },
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const calculateTotals = () => {
        const incomeTotal = incomeSources.reduce((sum, s) => sum + (Number(s.amount) || 0), 0);
        const deductionsTotal = deductionItems.reduce((sum, d) => sum + (Number(d.amount) || 0), 0);
        const payeDeducted = deductionItems.find(d => d.code === '4102')?.amount || 0;

        return {
            incomeTotal,
            deductionsTotal,
            payeDeducted,
        };
    };

    const updateEmployeeField = (field: string, value: string) => {
        setEmployeeData({ ...employeeData, [field]: value });
    };

    const updateIncomeSource = (index: number, field: keyof IncomeSource, value: string | number) => {
        const updated = [...incomeSources];
        updated[index] = { ...updated[index], [field]: value };
        setIncomeSources(updated);
    };

    const addIncomeSource = () => {
        setIncomeSources([
            ...incomeSources,
            { code: '3601', description: 'Gross Remuneration', amount: 0 },
        ]);
    };

    const removeIncomeSource = (index: number) => {
        setIncomeSources(incomeSources.filter((_, i) => i !== index));
    };

    const updateDeductionItem = (index: number, field: keyof DeductionItem, value: string | number) => {
        const updated = [...deductionItems];
        updated[index] = { ...updated[index], [field]: value };
        setDeductionItems(updated);
    };

    const addDeductionItem = () => {
        setDeductionItems([
            ...deductionItems,
            { code: '4102', description: 'PAYE', amount: 0 },
        ]);
    };

    const removeDeductionItem = (index: number) => {
        setDeductionItems(deductionItems.filter((_, i) => i !== index));
    };

    const handleCodeChange = (
        index: number,
        code: string,
        type: 'income' | 'deduction'
    ) => {
        const codes = type === 'income' ? INCOME_CODES : DEDUCTION_CODES;
        const found = codes.find(c => c.code === code);

        if (type === 'income') {
            const updated = [...incomeSources];
            updated[index] = { ...updated[index], code, description: found?.description || '' };
            setIncomeSources(updated);
        } else {
            const updated = [...deductionItems];
            updated[index] = { ...updated[index], code, description: found?.description || '' };
            setDeductionItems(updated);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const totals = calculateTotals();

        const updatedData: SubmissionData = {
            ...submission.data,
            employee: employeeData,
            income: {
                sources: incomeSources,
                total: totals.incomeTotal,
            },
            deductions: {
                items: deductionItems,
                total: totals.deductionsTotal,
            },
            summary: {
                ...submission.data.summary,
                gross_remuneration: totals.incomeTotal,
                total_deductions: totals.deductionsTotal,
                taxable_income: totals.incomeTotal,
                paye_deducted: totals.payeDeducted,
            },
        };

        router.put(`/compliance/irp5/${submission.id}`, {
            data: updatedData,
        });
    };

    const totals = calculateTotals();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit IRP5 - ${submission.data.employee?.name || 'Certificate'}`} />
            <form onSubmit={handleSubmit}>
                <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button type="button" variant="ghost" size="icon" asChild>
                                <Link href="/compliance/irp5">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-2xl font-bold">Edit IRP5 Certificate</h1>
                                <p className="text-muted-foreground">
                                    Tax Year: {submission.period} | Certificate: {submission.data.certificate_number}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {submission.status === 'submitted' && (
                                <Badge variant="secondary" className="bg-amber-100 text-amber-800">
                                    <AlertCircle className="mr-1 h-3 w-3" />
                                    Read Only (Submitted)
                                </Badge>
                            )}
                            <Button type="submit" disabled={submission.status === 'submitted'}>
                                <Save className="mr-2 h-4 w-4" />
                                Save Changes
                            </Button>
                        </div>
                    </div>

                    {/* Employer Info (Read-only) */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Employer Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="text-sm text-muted-foreground">Employer Name</label>
                                    <p className="font-medium">{submission.data.employer?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">Registration Number</label>
                                    <p className="font-medium">{submission.data.employer?.registration_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">PAYE Reference</label>
                                    <p className="font-medium">{submission.data.employer?.paye_reference || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Employee Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Employee Details</CardTitle>
                            <CardDescription>Edit employee information for the certificate</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="text-sm font-medium">Employee Name</label>
                                    <Input
                                        value={employeeData.name || ''}
                                        onChange={(e) => updateEmployeeField('name', e.target.value)}
                                        disabled={submission.status === 'submitted'}
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">ID Number</label>
                                    <Input
                                        value={employeeData.id_number || ''}
                                        onChange={(e) => updateEmployeeField('id_number', e.target.value)}
                                        className="font-mono"
                                        disabled={submission.status === 'submitted'}
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Tax Reference Number</label>
                                    <Input
                                        value={employeeData.tax_number || ''}
                                        onChange={(e) => updateEmployeeField('tax_number', e.target.value)}
                                        className="font-mono"
                                        disabled={submission.status === 'submitted'}
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Employment Start</label>
                                    <Input
                                        type="date"
                                        value={employeeData.employment_start || ''}
                                        onChange={(e) => updateEmployeeField('employment_start', e.target.value)}
                                        disabled={submission.status === 'submitted'}
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Employment End</label>
                                    <Input
                                        type="date"
                                        value={employeeData.employment_end || ''}
                                        onChange={(e) => updateEmployeeField('employment_end', e.target.value)}
                                        disabled={submission.status === 'submitted'}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Summary Cards */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Total Income</p>
                                <p className="text-2xl font-bold text-green-600">{formatCurrency(totals.incomeTotal)}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Total Deductions</p>
                                <p className="text-2xl font-bold text-red-600">{formatCurrency(totals.deductionsTotal)}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">PAYE Deducted</p>
                                <p className="text-2xl font-bold text-blue-600">{formatCurrency(totals.payeDeducted)}</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Income Sources */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Income (Source Codes 3000-3999)</CardTitle>
                                <CardDescription>Edit income sources for the tax year</CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addIncomeSource}
                                disabled={submission.status === 'submitted'}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Income
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="text-left p-3 w-32">Source Code</th>
                                            <th className="text-left p-3">Description</th>
                                            <th className="text-right p-3 w-48">Amount (ZAR)</th>
                                            <th className="text-center p-3 w-16">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {incomeSources.map((source, index) => (
                                            <tr key={index} className="border-b">
                                                <td className="p-2">
                                                    <select
                                                        value={source.code}
                                                        onChange={(e) => handleCodeChange(index, e.target.value, 'income')}
                                                        className="w-full p-2 border rounded font-mono text-sm"
                                                        disabled={submission.status === 'submitted'}
                                                    >
                                                        {INCOME_CODES.map(c => (
                                                            <option key={c.code} value={c.code}>{c.code}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        value={source.description || ''}
                                                        onChange={(e) => updateIncomeSource(index, 'description', e.target.value)}
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={source.amount || ''}
                                                        onChange={(e) => updateIncomeSource(index, 'amount', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeIncomeSource(index)}
                                                        disabled={submission.status === 'submitted'}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="font-bold bg-muted/30">
                                            <td colSpan={2} className="p-3">TOTAL INCOME</td>
                                            <td className="p-3 text-right">{formatCurrency(totals.incomeTotal)}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Deductions */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Deductions (Source Codes 4000-4999)</CardTitle>
                                <CardDescription>Edit deductions for the tax year</CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addDeductionItem}
                                disabled={submission.status === 'submitted'}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Deduction
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="text-left p-3 w-32">Source Code</th>
                                            <th className="text-left p-3">Description</th>
                                            <th className="text-right p-3 w-48">Amount (ZAR)</th>
                                            <th className="text-center p-3 w-16">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {deductionItems.map((item, index) => (
                                            <tr key={index} className="border-b">
                                                <td className="p-2">
                                                    <select
                                                        value={item.code}
                                                        onChange={(e) => handleCodeChange(index, e.target.value, 'deduction')}
                                                        className="w-full p-2 border rounded font-mono text-sm"
                                                        disabled={submission.status === 'submitted'}
                                                    >
                                                        {DEDUCTION_CODES.map(c => (
                                                            <option key={c.code} value={c.code}>{c.code}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        value={item.description || ''}
                                                        onChange={(e) => updateDeductionItem(index, 'description', e.target.value)}
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={item.amount || ''}
                                                        onChange={(e) => updateDeductionItem(index, 'amount', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeDeductionItem(index)}
                                                        disabled={submission.status === 'submitted'}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="font-bold bg-muted/30">
                                            <td colSpan={2} className="p-3">TOTAL DEDUCTIONS</td>
                                            <td className="p-3 text-right">{formatCurrency(totals.deductionsTotal)}</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/compliance/irp5">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={submission.status === 'submitted'}>
                            <Save className="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
