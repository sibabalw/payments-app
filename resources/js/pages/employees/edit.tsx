import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: '/employees' },
    { title: 'Edit', href: '#' },
];

export default function EmployeesEdit({ employee, businesses, taxBreakdown: initialTaxBreakdown, adjustments }: any) {
    const [taxBreakdown, setTaxBreakdown] = useState<any>(initialTaxBreakdown);

    const { data, setData, put, processing, errors } = useForm({
        business_id: employee.business_id,
        name: employee.name,
        email: employee.email || '',
        id_number: employee.id_number || '',
        tax_number: employee.tax_number || '',
        employment_type: employee.employment_type,
        hours_worked_per_month: employee.hours_worked_per_month || '',
        department: employee.department || '',
        start_date: employee.start_date || '',
        gross_salary: employee.gross_salary,
        hourly_rate: employee.hourly_rate || '',
        overtime_rate_multiplier: employee.overtime_rate_multiplier || 1.5,
        weekend_rate_multiplier: employee.weekend_rate_multiplier || 1.5,
        holiday_rate_multiplier: employee.holiday_rate_multiplier || 2.0,
        bank_account_details: employee.bank_account_details || {},
        tax_status: employee.tax_status || '',
        notes: employee.notes || '',
    });

    // Calculate tax when gross salary changes
    useEffect(() => {
        const calculateTax = async () => {
            if (!data.gross_salary || parseFloat(data.gross_salary) <= 0) {
                setTaxBreakdown(null);
                return;
            }

            try {
                // Use employee route - if gross_salary changed, send new value; otherwise use employee's existing salary
                const url = `/employees/${employee.id}/calculate-tax`;
                const body: any = {};
                
                // Only send gross_salary if it's different from the original
                if (parseFloat(data.gross_salary) !== parseFloat(employee.gross_salary)) {
                    body.gross_salary = parseFloat(data.gross_salary);
                }

                // Always include business_id and employee_id for adjustments
                body.business_id = data.business_id;
                body.employee_id = employee.id;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify(body),
                });

                if (response.ok) {
                    const breakdown = await response.json();
                    setTaxBreakdown(breakdown);
                }
            } catch (error) {
                console.error('Error calculating tax:', error);
            }
        };

        const timeoutId = setTimeout(() => {
            calculateTax();
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [data.gross_salary, data.business_id, employee.id, employee.gross_salary]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/employees/${employee.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Employee" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Employee</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={String(data.business_id)}
                                    onValueChange={(value) => setData('business_id', Number(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {businesses.map((business: any) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.business_id} />
                            </div>

                            <div>
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="id_number">ID Number</Label>
                                    <Input
                                        id="id_number"
                                        value={data.id_number}
                                        onChange={(e) => setData('id_number', e.target.value)}
                                    />
                                    <InputError message={errors.id_number} />
                                </div>

                                <div>
                                    <Label htmlFor="tax_number">Tax Number</Label>
                                    <Input
                                        id="tax_number"
                                        value={data.tax_number}
                                        onChange={(e) => setData('tax_number', e.target.value)}
                                    />
                                    <InputError message={errors.tax_number} />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="employment_type">Employment Type</Label>
                                    <Select
                                        value={data.employment_type}
                                        onValueChange={(value) => setData('employment_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="full_time">Full Time</SelectItem>
                                            <SelectItem value="part_time">Part Time</SelectItem>
                                            <SelectItem value="contract">Contract</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.employment_type} />
                                </div>

                                <div>
                                    <Label htmlFor="department">Department</Label>
                                    <Input
                                        id="department"
                                        value={data.department}
                                        onChange={(e) => setData('department', e.target.value)}
                                    />
                                    <InputError message={errors.department} />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) => setData('start_date', e.target.value)}
                                />
                                <InputError message={errors.start_date} />
                            </div>

                            <div>
                                <Label htmlFor="gross_salary">Gross Salary (ZAR)</Label>
                                <Input
                                    id="gross_salary"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.gross_salary}
                                    onChange={(e) => setData('gross_salary', e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground mt-1">
                                    Used for fixed salary employees. Leave empty if using hourly rate.
                                </p>
                                <InputError message={errors.gross_salary} />
                            </div>

                            <div className="border-t pt-4">
                                <h3 className="text-lg font-semibold mb-4">Hourly Rate Settings</h3>
                                <p className="text-sm text-muted-foreground mb-4">
                                    If set, salary will be calculated from time entries. Leave empty to use fixed gross salary.
                                </p>
                                
                                <div>
                                    <Label htmlFor="hourly_rate">Hourly Rate (ZAR)</Label>
                                    <Input
                                        id="hourly_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.hourly_rate}
                                        onChange={(e) => setData('hourly_rate', e.target.value)}
                                    />
                                    <InputError message={errors.hourly_rate} />
                                </div>

                                <div className="grid grid-cols-3 gap-4 mt-4">
                                    <div>
                                        <Label htmlFor="overtime_rate_multiplier">Overtime Multiplier</Label>
                                        <Input
                                            id="overtime_rate_multiplier"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            value={data.overtime_rate_multiplier}
                                            onChange={(e) => setData('overtime_rate_multiplier', e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground mt-1">Default: 1.5x</p>
                                        <InputError message={errors.overtime_rate_multiplier} />
                                    </div>

                                    <div>
                                        <Label htmlFor="weekend_rate_multiplier">Weekend Multiplier</Label>
                                        <Input
                                            id="weekend_rate_multiplier"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            value={data.weekend_rate_multiplier}
                                            onChange={(e) => setData('weekend_rate_multiplier', e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground mt-1">Default: 1.5x</p>
                                        <InputError message={errors.weekend_rate_multiplier} />
                                    </div>

                                    <div>
                                        <Label htmlFor="holiday_rate_multiplier">Holiday Multiplier</Label>
                                        <Input
                                            id="holiday_rate_multiplier"
                                            type="number"
                                            step="0.1"
                                            min="1"
                                            value={data.holiday_rate_multiplier}
                                            onChange={(e) => setData('holiday_rate_multiplier', e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground mt-1">Default: 2.0x</p>
                                        <InputError message={errors.holiday_rate_multiplier} />
                                    </div>
                                </div>

                                <div className="mt-4 flex gap-2">
                                    <Link href={`/employees/${employee.id}/schedule`}>
                                        <Button type="button" variant="outline">
                                            Manage Work Schedule
                                        </Button>
                                    </Link>
                                    <Link href={`/employees/${employee.id}/payslips`}>
                                        <Button type="button" variant="outline">
                                            View Payslips
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            {taxBreakdown && (
                                <Card className="bg-muted">
                                    <CardHeader>
                                        <CardTitle className="text-lg">Deduction Calculation Preview</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Gross Salary:</span>
                                            <span className="font-medium">ZAR {parseFloat(taxBreakdown.gross).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                        {(() => {
                                            const gross = parseFloat(taxBreakdown.gross);
                                            const calculatePercentage = (amount: number) => {
                                                if (gross === 0) return 0;
                                                return (amount / gross) * 100;
                                            };
                                            
                                            return (
                                                <>
                                                    <div className="flex justify-between text-red-600">
                                                        <span>PAYE:</span>
                                                        <span>
                                                            - ZAR {parseFloat(taxBreakdown.paye).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                            <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(taxBreakdown.paye)).toFixed(2)}%)</span>
                                                        </span>
                                                    </div>
                                                    <div className="flex justify-between text-red-600">
                                                        <span>UIF:</span>
                                                        <span>
                                                            - ZAR {parseFloat(taxBreakdown.uif).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                            <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(taxBreakdown.uif)).toFixed(2)}%)</span>
                                                        </span>
                                                    </div>
                                                    {taxBreakdown.adjustments && taxBreakdown.adjustments.length > 0 && (
                                                        <>
                                                            {/* Deduction Adjustments */}
                                                            {taxBreakdown.adjustments
                                                                .filter((adj: any) => (adj.adjustment_type || 'deduction') === 'deduction')
                                                                .map((adjustment: any, index: number) => (
                                                                    <div key={`deduction-${index}`} className="flex justify-between text-red-600">
                                                                        <span>{adjustment.name}:</span>
                                                                        <span>
                                                                            - ZAR {parseFloat(adjustment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                                            {adjustment.type === 'percentage' ? (
                                                                                <span className="text-muted-foreground ml-2">({parseFloat(adjustment.original_amount || adjustment.amount).toFixed(2)}%)</span>
                                                                            ) : (
                                                                                <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(adjustment.amount)).toFixed(2)}%)</span>
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ))}
                                                            {/* Addition Adjustments */}
                                                            {taxBreakdown.adjustments
                                                                .filter((adj: any) => (adj.adjustment_type || 'deduction') === 'addition')
                                                                .map((adjustment: any, index: number) => (
                                                                    <div key={`addition-${index}`} className="flex justify-between text-green-600">
                                                                        <span>{adjustment.name}:</span>
                                                                        <span>
                                                                            + ZAR {parseFloat(adjustment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                                            {adjustment.type === 'percentage' ? (
                                                                                <span className="text-muted-foreground ml-2">({parseFloat(adjustment.original_amount || adjustment.amount).toFixed(2)}%)</span>
                                                                            ) : (
                                                                                <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(adjustment.amount)).toFixed(2)}%)</span>
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                ))}
                                                        </>
                                                    )}
                                                </>
                                            );
                                        })()}
                                        <div className="border-t pt-2 flex justify-between font-bold">
                                            <span>Net Salary:</span>
                                            <span className="text-green-600">
                                                ZAR {parseFloat(taxBreakdown.final_net_salary || taxBreakdown.net).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </span>
                                        </div>
                                        {parseFloat(taxBreakdown.sdl) > 0 && (
                                            <div className="border-t pt-2 mt-2">
                                                <div className="flex justify-between text-sm text-muted-foreground">
                                                    <span>SDL (Employer Cost - not deducted from employee):</span>
                                                    <span>ZAR {parseFloat(taxBreakdown.sdl).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            <div>
                                <Label htmlFor="tax_status">Tax Status</Label>
                                <Input
                                    id="tax_status"
                                    value={data.tax_status}
                                    onChange={(e) => setData('tax_status', e.target.value)}
                                />
                                <InputError message={errors.tax_status} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Employee
                                </Button>
                                <Link href="/employees">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <div>
                                <CardTitle>Adjustments</CardTitle>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Company-wide adjustments apply to all employees. Employee-specific adjustments override or supplement company-wide ones.
                                </p>
                            </div>
                            <Link href={`/adjustments/create?business_id=${data.business_id}&employee_id=${employee.id}`}>
                                <Button size="sm">Add Adjustment</Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {adjustments && adjustments.length > 0 ? (
                            <div className="space-y-4">
                                {/* Company-wide adjustments */}
                                {adjustments.filter((a: any) => a.employee_id === null).length > 0 && (
                                    <div>
                                        <h4 className="text-sm font-semibold mb-2 text-muted-foreground">Company-wide Adjustments</h4>
                                        <div className="space-y-2">
                                            {adjustments
                                                .filter((a: any) => a.employee_id === null)
                                                .map((adjustment: any) => (
                                                    <div key={adjustment.id} className="flex justify-between items-center p-3 border rounded-lg bg-blue-50/50 dark:bg-blue-950/20">
                                                        <div>
                                                            <div className="font-medium">{adjustment.name}</div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {adjustment.type === 'percentage' 
                                                                    ? `${adjustment.amount}% of gross salary`
                                                                    : `ZAR ${parseFloat(adjustment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                                }
                                                                {' • '}
                                                                <span className={`capitalize ${
                                                                    adjustment.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                                }`}>
                                                                    {adjustment.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                                </span>
                                                                {' • '}
                                                                <span className="capitalize">
                                                                    {adjustment.is_recurring ? 'Recurring' : 'Once-off'}
                                                                </span>
                                                                <span className="ml-2 text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-0.5 rounded">Company-wide</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}
                                
                                {/* Employee-specific adjustments */}
                                {adjustments.filter((a: any) => a.employee_id === employee.id).length > 0 && (
                                    <div>
                                        <h4 className="text-sm font-semibold mb-2 text-muted-foreground">Employee-specific Adjustments</h4>
                                        <div className="space-y-2">
                                            {adjustments
                                                .filter((a: any) => a.employee_id === employee.id)
                                                .map((adjustment: any) => (
                                                    <div key={adjustment.id} className="flex justify-between items-center p-3 border rounded-lg">
                                                        <div>
                                                            <div className="font-medium">{adjustment.name}</div>
                                                            <div className="text-sm text-muted-foreground">
                                                                {adjustment.type === 'percentage' 
                                                                    ? `${adjustment.amount}% of gross salary`
                                                                    : `ZAR ${parseFloat(adjustment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                                }
                                                                {' • '}
                                                                <span className={`capitalize ${
                                                                    adjustment.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                                }`}>
                                                                    {adjustment.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                                </span>
                                                                {' • '}
                                                                <span className="capitalize">
                                                                    {adjustment.is_recurring ? 'Recurring' : 'Once-off'}
                                                                </span>
                                                                <span className="ml-2 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-0.5 rounded">Employee-specific</span>
                                                            </div>
                                                        </div>
                                                        <div className="flex gap-2">
                                                            <Link href={`/adjustments/${adjustment.id}/edit`}>
                                                                <Button variant="outline" size="sm">Edit</Button>
                                                            </Link>
                                                            <Link
                                                                href={`/adjustments/${adjustment.id}`}
                                                                method="delete"
                                                                as="button"
                                                                className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
                                                            >
                                                                Delete
                                                            </Link>
                                                        </div>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                <p className="text-sm text-muted-foreground">No adjustments configured for this employee.</p>
                                <div className="flex gap-2">
                                    <Link href={`/adjustments/create?business_id=${data.business_id}&employee_id=${employee.id}`}>
                                        <Button size="sm" variant="outline">Add Employee Adjustment</Button>
                                    </Link>
                                    <Link href={`/adjustments?business_id=${data.business_id}`}>
                                        <Button size="sm" variant="outline">Manage Company-wide Adjustments</Button>
                                    </Link>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
