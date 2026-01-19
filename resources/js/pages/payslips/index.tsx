import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Download } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payslips', href: '/payslips' },
];

export default function PayslipsIndex({ payslips, employees, filters }: any) {
    const [employeeId, setEmployeeId] = useState(filters?.employee_id || '');
    const [businessId, setBusinessId] = useState(filters?.business_id || '');

    const handleEmployeeChange = (value: string) => {
        setEmployeeId(value);
        router.get('/payslips', { employee_id: value || null, business_id: businessId || null }, { preserveState: true });
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const formatCurrency = (amount: number | string) => {
        return `ZAR ${parseFloat(String(amount)).toLocaleString('en-ZA', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payslips" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Payslips</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="max-w-xs">
                            <label className="text-sm font-medium mb-2 block">Employee</label>
                            <Select
                                value={employeeId || 'all'}
                                onValueChange={(value) => handleEmployeeChange(value === 'all' ? '' : value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All employees" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All employees</SelectItem>
                                    {employees.map((employee: any) => (
                                        <SelectItem key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {payslips?.data && payslips.data.length > 0 ? (
                    <div className="space-y-4">
                        {payslips.data.map((payslip: any) => (
                            <Card key={payslip.id}>
                                <CardHeader>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <CardTitle className="text-lg">{payslip.employee?.name}</CardTitle>
                                            <p className="text-sm text-muted-foreground mt-1">
                                                {payslip.pay_period_start && payslip.pay_period_end 
                                                    ? `${formatDate(payslip.pay_period_start)} - ${formatDate(payslip.pay_period_end)}`
                                                    : 'Pay period not specified'}
                                            </p>
                                            {payslip.processed_at && (
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    Paid: {formatDate(payslip.processed_at)}
                                                </p>
                                            )}
                                        </div>
                                        <span className="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {payslip.status}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span className="text-muted-foreground">Gross Salary:</span>
                                                <span className="font-medium ml-2">{formatCurrency(payslip.gross_salary)}</span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground">Net Pay:</span>
                                                <span className="font-medium text-green-600 ml-2">{formatCurrency(payslip.net_salary)}</span>
                                            </div>
                                        </div>
                                        <div className="flex gap-2 pt-2">
                                            <Link href={`/payslips/${payslip.id}`} className="flex-1">
                                                <Button variant="outline" size="sm" className="w-full">
                                                    <FileText className="mr-2 h-4 w-4" />
                                                    View Payslip
                                                </Button>
                                            </Link>
                                            <a href={`/payslips/${payslip.id}/download`} className="flex-1">
                                                <Button size="sm" className="w-full">
                                                    <Download className="mr-2 h-4 w-4" />
                                                    Download PDF
                                                </Button>
                                            </a>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No payslips found.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
