import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, FileText, TrendingUp, Users, Receipt, DollarSign, FileSpreadsheet } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
];

export default function ReportsIndex({ report, report_type, business_id, start_date, end_date, businesses }: any) {
    const [localReportType, setLocalReportType] = useState(report_type || 'payroll_summary');
    const [localBusinessId, setLocalBusinessId] = useState(business_id || '');
    const [localStartDate, setLocalStartDate] = useState(start_date || '');
    const [localEndDate, setLocalEndDate] = useState(end_date || '');

    const handleFilterChange = () => {
        router.get('/reports', {
            report_type: localReportType,
            business_id: localBusinessId || null,
            start_date: localStartDate || null,
            end_date: localEndDate || null,
        }, { preserveState: true });
    };

    const getExportUrl = (format: string) => {
        const params = new URLSearchParams({
            report_type: localReportType,
            ...(localBusinessId && { business_id: localBusinessId }),
            ...(localStartDate && { start_date: localStartDate }),
            ...(localEndDate && { end_date: localEndDate }),
        });
        return `/reports/export/${format}?${params.toString()}`;
    };

    const formatCurrency = (amount: number | string) => {
        return `ZAR ${parseFloat(String(amount)).toLocaleString('en-ZA', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const renderReport = () => {
        switch (localReportType) {
            case 'payroll_summary':
                return renderPayrollSummary();
            case 'payroll_by_employee':
                return renderPayrollByEmployee();
            case 'tax_summary':
                return renderTaxSummary();
            case 'deductions_summary':
                return renderDeductionsSummary();
            case 'payment_summary':
                return renderPaymentSummary();
            case 'employee_earnings':
                return renderEmployeeEarnings();
            default:
                return renderPayrollSummary();
        }
    };

    const renderPayrollSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{report.total_jobs || 0}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Gross</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_gross || 0)}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency((report.total_paye || 0) + (report.total_uif || 0) + (report.total_custom_deductions || 0))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Net</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{formatCurrency(report.total_net || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span>PAYE:</span>
                                <span>{formatCurrency(report.total_paye || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>UIF:</span>
                                <span>{formatCurrency(report.total_uif || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Custom Deductions:</span>
                                <span>{formatCurrency(report.total_custom_deductions || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>SDL (Employer Cost):</span>
                                <span>{formatCurrency(report.total_sdl || 0)}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {report.jobs && report.jobs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payroll Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Employee</th>
                                            <th className="text-right p-2">Gross</th>
                                            <th className="text-right p-2">PAYE</th>
                                            <th className="text-right p-2">UIF</th>
                                            <th className="text-right p-2">Custom</th>
                                            <th className="text-right p-2">Net</th>
                                            <th className="text-left p-2">Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.jobs.map((job: any) => (
                                            <tr key={job.id} className="border-b">
                                                <td className="p-2">{job.employee_name}</td>
                                                <td className="text-right p-2">{formatCurrency(job.gross_salary)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.paye_amount)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.uif_amount)}</td>
                                                <td className="text-right p-2 text-red-600">{formatCurrency(job.custom_deductions_total)}</td>
                                                <td className="text-right p-2 font-medium text-green-600">{formatCurrency(job.net_salary)}</td>
                                                <td className="p-2 text-xs text-muted-foreground">
                                                    {formatDate(job.pay_period_start)} - {formatDate(job.pay_period_end)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderPayrollByEmployee = () => {
        if (!report || !report.employees) return null;
        return (
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                            <div>
                                <div className="text-muted-foreground">Total Employees</div>
                                <div className="text-lg font-bold">{report.total_employees || 0}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Gross</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_gross || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Net</div>
                                <div className="text-lg font-bold text-green-600">{formatCurrency(report.summary?.total_net || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total PAYE</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_paye || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total UIF</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_uif || 0)}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {report.employees.map((emp: any) => (
                        <Card key={emp.employee_id}>
                            <CardHeader>
                                <CardTitle>{emp.employee_name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Payments</div>
                                        <div className="text-lg font-bold">{emp.total_jobs}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Gross</div>
                                        <div className="text-lg font-bold">{formatCurrency(emp.total_gross)}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Net</div>
                                        <div className="text-lg font-bold text-green-600">{formatCurrency(emp.total_net)}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Total Deductions</div>
                                        <div className="text-lg font-bold text-red-600">
                                            {formatCurrency(emp.total_paye + emp.total_uif + emp.total_custom_deductions)}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        );
    };

    const renderTaxSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>PAYE</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.paye?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.paye?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.paye?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>UIF</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.uif?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.uif?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.uif?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SDL (Employer Cost)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div>
                                    <div className="text-sm text-muted-foreground">Total</div>
                                    <div className="text-2xl font-bold">{formatCurrency(report.sdl?.total || 0)}</div>
                                </div>
                                <div className="text-sm">
                                    <div className="text-muted-foreground">Count: {report.sdl?.count || 0}</div>
                                    <div className="text-muted-foreground">Average: {formatCurrency(report.sdl?.average || 0)}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span>Total Tax Liability (PAYE + UIF):</span>
                                <span className="font-bold">{formatCurrency(report.total_tax_liability || 0)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Total Employer Costs (SDL):</span>
                                <span className="font-bold">{formatCurrency(report.total_employer_costs || 0)}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    };

    const renderDeductionsSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Statutory Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_statutory_deductions || 0)}</div>
                            <div className="text-sm text-muted-foreground mt-2">PAYE + UIF</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Custom Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_custom_deductions || 0)}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total All Deductions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{formatCurrency(report.total_all_deductions || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                {report.deductions && report.deductions.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Custom Deductions Breakdown</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Deduction Name</th>
                                            <th className="text-left p-2">Type</th>
                                            <th className="text-right p-2">Total Amount</th>
                                            <th className="text-right p-2">Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.deductions.map((deduction: any, index: number) => (
                                            <tr key={index} className="border-b">
                                                <td className="p-2">{deduction.name}</td>
                                                <td className="p-2 capitalize">{deduction.type}</td>
                                                <td className="text-right p-2 font-medium">{formatCurrency(deduction.total_amount)}</td>
                                                <td className="text-right p-2">{deduction.count}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderPaymentSummary = () => {
        if (!report) return null;
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Total Payments</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{report.total_jobs || 0}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total Amount</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{formatCurrency(report.total_amount || 0)}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Total Fees</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(report.total_fees || 0)}</div>
                        </CardContent>
                    </Card>
                </div>

                {report.jobs && report.jobs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Jobs</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left p-2">Receiver</th>
                                            <th className="text-right p-2">Amount</th>
                                            <th className="text-right p-2">Fee</th>
                                            <th className="text-left p-2">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.jobs.map((job: any) => (
                                            <tr key={job.id} className="border-b">
                                                <td className="p-2">{job.receiver_name}</td>
                                                <td className="text-right p-2 font-medium">{formatCurrency(job.amount)}</td>
                                                <td className="text-right p-2">{formatCurrency(job.fee)}</td>
                                                <td className="p-2 text-xs text-muted-foreground">{formatDate(job.processed_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        );
    };

    const renderEmployeeEarnings = () => {
        if (!report || !report.employees) return null;
        return (
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div className="text-muted-foreground">Total Employees</div>
                                <div className="text-lg font-bold">{report.summary?.total_employees || 0}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Gross</div>
                                <div className="text-lg font-bold">{formatCurrency(report.summary?.total_gross || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Net</div>
                                <div className="text-lg font-bold text-green-600">{formatCurrency(report.summary?.total_net || 0)}</div>
                            </div>
                            <div>
                                <div className="text-muted-foreground">Total Deductions</div>
                                <div className="text-lg font-bold text-red-600">{formatCurrency(report.summary?.total_deductions || 0)}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="text-left p-2">Employee</th>
                                <th className="text-right p-2">Payments</th>
                                <th className="text-right p-2">Total Gross</th>
                                <th className="text-right p-2">Avg Gross</th>
                                <th className="text-right p-2">Total Net</th>
                                <th className="text-right p-2">Avg Net</th>
                                <th className="text-right p-2">Total Deductions</th>
                                <th className="text-right p-2">Deduction %</th>
                            </tr>
                        </thead>
                        <tbody>
                            {report.employees.map((emp: any) => (
                                <tr key={emp.employee_id} className="border-b">
                                    <td className="p-2">
                                        <div className="font-medium">{emp.employee_name}</div>
                                        {emp.employee_email && (
                                            <div className="text-xs text-muted-foreground">{emp.employee_email}</div>
                                        )}
                                    </td>
                                    <td className="text-right p-2">{emp.total_payments}</td>
                                    <td className="text-right p-2 font-medium">{formatCurrency(emp.total_gross)}</td>
                                    <td className="text-right p-2 text-muted-foreground">{formatCurrency(emp.average_gross)}</td>
                                    <td className="text-right p-2 font-medium text-green-600">{formatCurrency(emp.total_net)}</td>
                                    <td className="text-right p-2 text-muted-foreground">{formatCurrency(emp.average_net)}</td>
                                    <td className="text-right p-2 text-red-600">{formatCurrency(emp.total_deductions)}</td>
                                    <td className="text-right p-2">{emp.deduction_percentage.toFixed(2)}%</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Reports</h1>
                    <div className="flex gap-2">
                        <a href={getExportUrl('csv')}>
                            <Button variant="outline" size="sm">
                                <FileSpreadsheet className="mr-2 h-4 w-4" />
                                Export CSV
                            </Button>
                        </a>
                        <a href={getExportUrl('excel')}>
                            <Button variant="outline" size="sm">
                                <FileSpreadsheet className="mr-2 h-4 w-4" />
                                Export Excel
                            </Button>
                        </a>
                        <a href={getExportUrl('pdf')}>
                            <Button variant="outline" size="sm">
                                <FileText className="mr-2 h-4 w-4" />
                                Export PDF
                            </Button>
                        </a>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <Label htmlFor="report_type">Report Type</Label>
                                <Select
                                    value={localReportType}
                                    onValueChange={(value) => {
                                        setLocalReportType(value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="payroll_summary">Payroll Summary</SelectItem>
                                        <SelectItem value="payroll_by_employee">Payroll by Employee</SelectItem>
                                        <SelectItem value="tax_summary">Tax Summary</SelectItem>
                                        <SelectItem value="deductions_summary">Deductions Summary</SelectItem>
                                        <SelectItem value="payment_summary">Payment Summary</SelectItem>
                                        <SelectItem value="employee_earnings">Employee Earnings</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={localBusinessId || 'all'}
                                    onValueChange={(value) => {
                                        setLocalBusinessId(value === 'all' ? '' : value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="All businesses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All businesses</SelectItem>
                                        {businesses.map((business: any) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="start_date">Start Date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={localStartDate}
                                    onChange={(e) => {
                                        setLocalStartDate(e.target.value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                />
                            </div>

                            <div>
                                <Label htmlFor="end_date">End Date</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={localEndDate}
                                    onChange={(e) => {
                                        setLocalEndDate(e.target.value);
                                        setTimeout(handleFilterChange, 100);
                                    }}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {renderReport()}
            </div>
        </AppLayout>
    );
}
