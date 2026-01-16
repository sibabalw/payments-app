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

export default function EmployeesEdit({ employee, businesses, taxBreakdown: initialTaxBreakdown }: any) {
    const [taxBreakdown, setTaxBreakdown] = useState<any>(initialTaxBreakdown);

    const { data, setData, put, processing, errors } = useForm({
        business_id: employee.business_id,
        name: employee.name,
        email: employee.email || '',
        id_number: employee.id_number || '',
        tax_number: employee.tax_number || '',
        employment_type: employee.employment_type,
        department: employee.department || '',
        start_date: employee.start_date || '',
        gross_salary: employee.gross_salary,
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

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: Object.keys(body).length > 0 ? JSON.stringify(body) : undefined,
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
    }, [data.gross_salary, employee.id, employee.gross_salary]);

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
                                    required
                                />
                                <InputError message={errors.gross_salary} />
                            </div>

                            {taxBreakdown && (
                                <Card className="bg-muted">
                                    <CardHeader>
                                        <CardTitle className="text-lg">Tax Calculation Preview</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <div className="flex justify-between">
                                            <span>Gross Salary:</span>
                                            <span className="font-medium">ZAR {parseFloat(taxBreakdown.gross).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between text-red-600">
                                            <span>PAYE:</span>
                                            <span>- ZAR {parseFloat(taxBreakdown.paye).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between text-red-600">
                                            <span>UIF:</span>
                                            <span>- ZAR {parseFloat(taxBreakdown.uif).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="flex justify-between text-red-600">
                                            <span>SDL:</span>
                                            <span>- ZAR {parseFloat(taxBreakdown.sdl).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
                                        <div className="border-t pt-2 flex justify-between font-bold">
                                            <span>Net Salary:</span>
                                            <span className="text-green-600">ZAR {parseFloat(taxBreakdown.net).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        </div>
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
            </div>
        </AppLayout>
    );
}
