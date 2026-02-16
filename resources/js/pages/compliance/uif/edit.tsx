import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Trash2, Plus, AlertCircle } from 'lucide-react';
import { useState } from 'react';

interface Employee {
    employee_id: number;
    employee_name: string;
    id_number: string;
    gross_remuneration: number;
    uif_employee: number;
    uif_employer: number;
    total_uif: number;
    days_worked?: number;
}

interface SubmissionData {
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

interface Submission {
    id: number;
    period: string;
    period_display: string;
    status: string;
    data: SubmissionData;
}

interface EditUI19Props {
    submission: Submission;
}

export default function EditUI19({ submission }: EditUI19Props) {
    const [employees, setEmployees] = useState<Employee[]>(submission.data.employees || []);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Compliance', href: '/compliance' },
        { title: 'UIF Declarations', href: '/compliance/uif' },
        { title: `Edit ${submission.period_display}`, href: `/compliance/uif/${submission.id}/edit` },
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const calculateTotals = (emps: Employee[]) => {
        return {
            total_employees: emps.length,
            total_gross_remuneration: emps.reduce((sum, e) => sum + (Number(e.gross_remuneration) || 0), 0),
            total_uif_employee: emps.reduce((sum, e) => sum + (Number(e.uif_employee) || 0), 0),
            total_uif_employer: emps.reduce((sum, e) => sum + (Number(e.uif_employer) || 0), 0),
            total_uif_contribution: emps.reduce((sum, e) => sum + (Number(e.total_uif) || 0), 0),
        };
    };

    const updateEmployee = (index: number, field: keyof Employee, value: string | number) => {
        const updated = [...employees];
        updated[index] = { ...updated[index], [field]: value };

        // Auto-calculate UIF if gross remuneration changes
        if (field === 'gross_remuneration') {
            const gross = Number(value) || 0;
            const uifBase = Math.min(gross, 17712); // UIF ceiling
            const uifEmployee = Math.round(uifBase * 0.01 * 100) / 100;
            const uifEmployer = uifEmployee;
            updated[index].uif_employee = uifEmployee;
            updated[index].uif_employer = uifEmployer;
            updated[index].total_uif = uifEmployee + uifEmployer;
        }

        // Update total if individual UIF changes
        if (field === 'uif_employee' || field === 'uif_employer') {
            updated[index].total_uif =
                (Number(updated[index].uif_employee) || 0) + (Number(updated[index].uif_employer) || 0);
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
                gross_remuneration: 0,
                uif_employee: 0,
                uif_employer: 0,
                total_uif: 0,
                days_worked: 30,
            },
        ]);
    };

    const removeEmployee = (index: number) => {
        setEmployees(employees.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const totals = calculateTotals(employees);

        router.put(`/compliance/uif/${submission.id}`, {
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
            <Head title={`Edit UI-19 - ${submission.period_display}`} />
            <form onSubmit={handleSubmit}>
                <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Button type="button" variant="ghost" size="icon" asChild>
                                <Link href="/compliance/uif">
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-2xl font-bold">Edit UI-19 Declaration</h1>
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
                            <div className="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="text-sm text-muted-foreground">Business Name</label>
                                    <p className="font-medium">{submission.data.business?.name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">Registration Number</label>
                                    <p className="font-medium">{submission.data.business?.registration_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm text-muted-foreground">UIF Reference</label>
                                    <p className="font-medium">{submission.data.business?.uif_reference || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Totals Summary */}
                    <div className="grid gap-4 md:grid-cols-5">
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Employees</p>
                                <p className="text-2xl font-bold">{totals.total_employees}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Gross Remuneration</p>
                                <p className="text-2xl font-bold">{formatCurrency(totals.total_gross_remuneration)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Employee UIF</p>
                                <p className="text-2xl font-bold text-blue-600">{formatCurrency(totals.total_uif_employee)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Employer UIF</p>
                                <p className="text-2xl font-bold text-blue-600">{formatCurrency(totals.total_uif_employer)}</p>
                            </CardContent>
                        </Card>
                        <Card className="bg-primary/5">
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">Total UIF</p>
                                <p className="text-2xl font-bold text-primary">{formatCurrency(totals.total_uif_contribution)}</p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Employee Table */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Employee Contributions</CardTitle>
                                <CardDescription>Edit employee UIF contribution details</CardDescription>
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
                                            <th className="text-left p-3">ID Number</th>
                                            <th className="text-left p-3">Employee Name</th>
                                            <th className="text-right p-3">Gross Remuneration</th>
                                            <th className="text-right p-3">Employee UIF</th>
                                            <th className="text-right p-3">Employer UIF</th>
                                            <th className="text-right p-3">Total UIF</th>
                                            <th className="text-center p-3 w-16">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {employees.map((employee, index) => (
                                            <tr key={employee.employee_id || index} className="border-b">
                                                <td className="p-2">
                                                    <Input
                                                        value={employee.id_number || ''}
                                                        onChange={(e) => updateEmployee(index, 'id_number', e.target.value)}
                                                        className="font-mono text-xs"
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        value={employee.employee_name || ''}
                                                        onChange={(e) => updateEmployee(index, 'employee_name', e.target.value)}
                                                        disabled={submission.status === 'submitted'}
                                                    />
                                                </td>
                                                <td className="p-2">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        value={employee.gross_remuneration || ''}
                                                        onChange={(e) => updateEmployee(index, 'gross_remuneration', parseFloat(e.target.value) || 0)}
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
                                                <td className="p-2 text-right font-semibold">
                                                    {formatCurrency(employee.total_uif || 0)}
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

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/compliance/uif">Cancel</Link>
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
