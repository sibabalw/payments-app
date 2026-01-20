import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save, Trash2, Plus, AlertCircle } from 'lucide-react';
import { useState } from 'react';

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

interface SubmissionData {
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

interface Submission {
    id: number;
    period: string;
    period_display: string;
    status: string;
    data: SubmissionData;
}

interface EditEMP201Props {
    submission: Submission;
}

export default function EditEMP201({ submission }: EditEMP201Props) {
    const [employees, setEmployees] = useState<Employee[]>(submission.data.employees || []);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Compliance', href: '/compliance' },
        { title: 'EMP201 Helper', href: '/compliance/emp201' },
        { title: `Edit ${submission.period_display}`, href: `/compliance/emp201/${submission.id}/edit` },
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const calculateTotals = (emps: Employee[]) => {
        const totalGross = emps.reduce((sum, e) => sum + (Number(e.gross_salary) || 0), 0);
        const totalPaye = emps.reduce((sum, e) => sum + (Number(e.paye) || 0), 0);
        const totalUifEmployee = emps.reduce((sum, e) => sum + (Number(e.uif_employee) || 0), 0);
        const totalUifEmployer = emps.reduce((sum, e) => sum + (Number(e.uif_employer) || 0), 0);
        const totalSdl = emps.reduce((sum, e) => sum + (Number(e.sdl) || 0), 0);

        return {
            employees_count: emps.length,
            total_gross: totalGross,
            total_paye: totalPaye,
            total_uif_employee: totalUifEmployee,
            total_uif_employer: totalUifEmployer,
            total_uif: totalUifEmployee + totalUifEmployer,
            total_sdl: totalSdl,
            total_liability: totalPaye + totalUifEmployee + totalUifEmployer + totalSdl,
        };
    };

    const updateEmployee = (index: number, field: keyof Employee, value: string | number) => {
        const updated = [...employees];
        updated[index] = { ...updated[index], [field]: value };

        // Auto-calculate tax components if gross salary changes
        if (field === 'gross_salary') {
            const gross = Number(value) || 0;
            // Simplified calculations - can be adjusted
            const monthlyGross = gross;
            const annualGross = monthlyGross * 12;

            // Simple PAYE calculation (simplified)
            let paye = 0;
            if (annualGross > 237100) {
                paye = monthlyGross * 0.18; // First bracket rate
            }
            updated[index].paye = Math.round(paye * 100) / 100;

            // UIF
            const uifBase = Math.min(gross, 17712);
            updated[index].uif_employee = Math.round(uifBase * 0.01 * 100) / 100;
            updated[index].uif_employer = Math.round(uifBase * 0.01 * 100) / 100;

            // SDL (1% of payroll)
            updated[index].sdl = Math.round(gross * 0.01 * 100) / 100;
        }

        setEmployees(updated);
    };

    const addEmployee = () => {
        setEmployees([
            ...employees,
            {
                employee_id: Date.now(),
                employee_name: '',
                id_number: '',
                tax_number: '',
                gross_salary: 0,
                paye: 0,
                uif_employee: 0,
                uif_employer: 0,
                sdl: 0,
            },
        ]);
    };

    const removeEmployee = (index: number) => {
        setEmployees(employees.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const totals = calculateTotals(employees);

        router.put(`/compliance/emp201/${submission.id}`, {
            data: {
                ...submission.data,
                employees,
                totals,
            },
        });
    };

    const totals = calculateTotals(employees);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit EMP201 - ${submission.period_display}`} />
            <form onSubmit={handleSubmit}>
                <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button type="button" variant="ghost" size="icon" asChild>
                                <Link href="/compliance/emp201">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-2xl font-bold">Edit EMP201 Data</h1>
                                <p className="text-muted-foreground">{submission.period_display}</p>
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

                    {/* Business Info (Read-only) */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Business Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label className="text-sm text-muted-foreground">Business Name</label>
                                    <p className="font-medium">{submission.data.business?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">PAYE Reference</label>
                                    <p className="font-medium">{submission.data.business?.paye_reference || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">SDL Reference</label>
                                    <p className="font-medium">{submission.data.business?.sdl_reference || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">UIF Reference</label>
                                    <p className="font-medium">{submission.data.business?.uif_reference || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tax Summary */}
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">PAYE</p>
                                <p className="text-2xl font-bold text-blue-600">{formatCurrency(totals.total_paye)}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">UIF Total</p>
                                <p className="text-2xl font-bold text-green-600">{formatCurrency(totals.total_uif)}</p>
                                <p className="text-xs text-muted-foreground">
                                    Employee: {formatCurrency(totals.total_uif_employee)} |
                                    Employer: {formatCurrency(totals.total_uif_employer)}
                                </p>
                            </CardContent>
                        </Card>
                        <Card className="border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-950">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">SDL</p>
                                <p className="text-2xl font-bold text-purple-600">{formatCurrency(totals.total_sdl)}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-primary bg-primary/5">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Total Liability</p>
                                <p className="text-2xl font-bold text-primary">{formatCurrency(totals.total_liability)}</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Employee Table */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Employee Tax Details</CardTitle>
                                <CardDescription>Edit employee tax calculations for the period</CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addEmployee}
                                disabled={submission.status === 'submitted'}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Employee
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/50">
                                            <th className="text-left p-3">Tax Number</th>
                                            <th className="text-left p-3">Employee Name</th>
                                            <th className="text-right p-3">Gross Salary</th>
                                            <th className="text-right p-3">PAYE</th>
                                            <th className="text-right p-3">UIF (Emp)</th>
                                            <th className="text-right p-3">UIF (Er)</th>
                                            <th className="text-right p-3">SDL</th>
                                            <th className="text-center p-3 w-16">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.map((employee, index) => (
                                            <tr key={employee.employee_id || index} className="border-b">
                                                <td className="p-2">
                                                    <Input
                                                        value={employee.tax_number || ''}
                                                        onChange={(e) => updateEmployee(index, 'tax_number', e.target.value)}
                                                        className="font-mono text-xs"
                                                        placeholder="Tax number"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        value={employee.employee_name || ''}
                                                        onChange={(e) => updateEmployee(index, 'employee_name', e.target.value)}
                                                        placeholder="Employee name"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.gross_salary || ''}
                                                        onChange={(e) => updateEmployee(index, 'gross_salary', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.paye || ''}
                                                        onChange={(e) => updateEmployee(index, 'paye', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.uif_employee || ''}
                                                        onChange={(e) => updateEmployee(index, 'uif_employee', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.uif_employer || ''}
                                                        onChange={(e) => updateEmployee(index, 'uif_employer', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.sdl || ''}
                                                        onChange={(e) => updateEmployee(index, 'sdl', parseFloat(e.target.value) || 0)}
                                                        className="text-right"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2 text-center">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => removeEmployee(index)}
                                                        disabled={submission.status === 'submitted'}
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {employees.length === 0 && (
                                <div className="text-center py-8 text-muted-foreground">
                                    No employees added. Click "Add Employee" to start.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Summary Stats */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <p className="text-sm text-muted-foreground">Number of Employees</p>
                                    <p className="text-xl font-bold">{totals.employees_count}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Gross Remuneration</p>
                                    <p className="text-xl font-bold">{formatCurrency(totals.total_gross)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/compliance/emp201">Cancel</Link>
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
