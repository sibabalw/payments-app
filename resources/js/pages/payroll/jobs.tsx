import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Jobs', href: '#' },
];

export default function PayrollJobs({ jobs, employees, filters }: any) {
    const [employeeId, setEmployeeId] = useState(filters?.employee_id || '');

    const handleEmployeeChange = (value: string) => {
        setEmployeeId(value);
        router.get('/payroll/jobs', { 
            employee_id: value === 'all' ? null : value,
            business_id: filters?.business_id || null,
            status: filters?.status || null,
        }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Payroll Jobs</h1>
                    <Link href="/payslips">
                        <Button variant="outline">
                            <FileText className="mr-2 h-4 w-4" />
                            View All Payslips
                        </Button>
                    </Link>
                </div>

                {employees && employees.length > 0 && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="max-w-xs">
                                <label className="text-sm font-medium mb-2 block">Filter by Employee</label>
                                <Select
                                    value={employeeId || 'all'}
                                    onValueChange={handleEmployeeChange}
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
                )}

                {jobs?.data && jobs.data.length > 0 ? (
                    <div className="space-y-4">
                        {jobs.data.map((job: any) => (
                            <Card key={job.id}>
                                <CardHeader>
                                    <CardTitle>{job.payroll_schedule?.name || 'Payroll Schedule'}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                                <p className="font-medium">{job.employee?.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {job.pay_period_start && job.pay_period_end 
                                                        ? `${new Date(job.pay_period_start).toLocaleDateString()} - ${new Date(job.pay_period_end).toLocaleDateString()}`
                                                        : 'Pay period not specified'}
                                            </p>
                                        </div>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                job.status === 'succeeded'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : job.status === 'failed'
                                                      ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                      : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            }`}
                                        >
                                            {job.status}
                                        </span>
                                        </div>

                                        <div className="border-t pt-3 space-y-2">
                                            {(() => {
                                                const gross = parseFloat(job.gross_salary);
                                                const calculatePercentage = (amount: number) => {
                                                    if (gross === 0) return 0;
                                                    return (amount / gross) * 100;
                                                };
                                                
                                                return (
                                                    <>
                                                        <div className="flex justify-between text-sm">
                                                            <span>Gross Salary:</span>
                                                            <span className="font-medium">ZAR {gross.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                        </div>
                                                        <div className="flex justify-between text-sm text-red-600">
                                                            <span>PAYE:</span>
                                                            <span>
                                                                - ZAR {parseFloat(job.paye_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                                <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(job.paye_amount)).toFixed(2)}%)</span>
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between text-sm text-red-600">
                                                            <span>UIF:</span>
                                                            <span>
                                                                - ZAR {parseFloat(job.uif_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} 
                                                                <span className="text-muted-foreground ml-2">({calculatePercentage(parseFloat(job.uif_amount)).toFixed(2)}%)</span>
                                                            </span>
                                                        </div>
                                                        <div className="border-t pt-2 flex justify-between font-bold">
                                                            <span>Net Salary (Paid):</span>
                                                            <span className="text-green-600">ZAR {parseFloat(job.net_salary).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                        </div>
                                                        {parseFloat(job.sdl_amount) > 0 && (
                                                            <div className="border-t pt-2 mt-2">
                                                                <div className="flex justify-between text-sm text-muted-foreground">
                                                                    <span>SDL (Employer Cost - not deducted from employee):</span>
                                                                    <span>ZAR {parseFloat(job.sdl_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                                </div>
                                                            </div>
                                                        )}
                                                    </>
                                                );
                                            })()}
                                        </div>

                                        {job.error_message && (
                                            <div className="mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded text-sm text-red-600">
                                                {job.error_message}
                                            </div>
                                        )}

                                        {job.status === 'succeeded' && (
                                            <div className="mt-3 pt-3 border-t">
                                                <Link href={`/payslips/${job.id}`}>
                                                    <Button variant="outline" size="sm" className="w-full">
                                                        <FileText className="mr-2 h-4 w-4" />
                                                        View Payslip
                                                    </Button>
                                                </Link>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No payroll jobs found.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
