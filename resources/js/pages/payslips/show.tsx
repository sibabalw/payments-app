import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Download, FileText, ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Payslip', href: '#' },
];

export default function PayslipShow({ payslip }: any) {
    const { job, employee, business, custom_deductions } = payslip;

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
            month: 'long',
            day: 'numeric'
        });
    };

    const calculatePercentage = (amount: number, gross: number) => {
        if (gross === 0) return 0;
        return ((amount / gross) * 100).toFixed(2);
    };

    const gross = parseFloat(job.gross_salary);
    const totalDeductions = parseFloat(job.paye_amount) + parseFloat(job.uif_amount);
    const customDeductionsTotal = custom_deductions?.reduce((sum: number, deduction: any) => {
        return sum + (deduction.amount || 0);
    }, 0) || 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payslip - ${employee.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/payroll/jobs">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">Payslip</h1>
                    </div>
                    <div className="flex gap-2">
                        <a href={`/payslips/${job.id}/pdf`} target="_blank" rel="noopener noreferrer">
                            <Button variant="outline">
                                <FileText className="mr-2 h-4 w-4" />
                                View PDF
                            </Button>
                        </a>
                        <a href={`/payslips/${job.id}/download`}>
                            <Button>
                                <Download className="mr-2 h-4 w-4" />
                                Download PDF
                            </Button>
                        </a>
                    </div>
                </div>

                <div className="max-w-4xl mx-auto w-full">
                    <Card className="print:shadow-none print:border-0">
                        <CardHeader className="print:pb-2">
                            <div className="flex justify-between items-start">
                                <div>
                                    <CardTitle className="text-2xl mb-2">{business.name}</CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        {business.street_address && `${business.street_address}, `}
                                        {business.city && `${business.city}, `}
                                        {business.province && `${business.province}`}
                                        {business.postal_code && ` ${business.postal_code}`}
                                    </p>
                                    {business.email && (
                                        <p className="text-sm text-muted-foreground mt-1">{business.email}</p>
                                    )}
                                    {business.phone && (
                                        <p className="text-sm text-muted-foreground">{business.phone}</p>
                                    )}
                                </div>
                                <div className="text-right">
                                    <h2 className="text-xl font-bold mb-2">PAYSLIP</h2>
                                    <p className="text-sm text-muted-foreground">
                                        Pay Period: {formatDate(job.pay_period_start)} - {formatDate(job.pay_period_end)}
                                    </p>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        Payment Date: {formatDate(job.processed_at)}
                                    </p>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-6 print:space-y-4">
                            {/* Employee Information */}
                            <div className="border-b pb-4">
                                <h3 className="font-semibold mb-3">Employee Information</h3>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">Name</p>
                                        <p className="font-medium">{employee.name}</p>
                                    </div>
                                    {employee.email && (
                                        <div>
                                            <p className="text-muted-foreground">Email</p>
                                            <p className="font-medium">{employee.email}</p>
                                        </div>
                                    )}
                                    {employee.id_number && (
                                        <div>
                                            <p className="text-muted-foreground">ID Number</p>
                                            <p className="font-medium">{employee.id_number}</p>
                                        </div>
                                    )}
                                    {employee.tax_number && (
                                        <div>
                                            <p className="text-muted-foreground">Tax Number</p>
                                            <p className="font-medium">{employee.tax_number}</p>
                                        </div>
                                    )}
                                    {employee.department && (
                                        <div>
                                            <p className="text-muted-foreground">Department</p>
                                            <p className="font-medium">{employee.department}</p>
                                        </div>
                                    )}
                                    {employee.employment_type && (
                                        <div>
                                            <p className="text-muted-foreground">Employment Type</p>
                                            <p className="font-medium capitalize">{employee.employment_type.replace('_', ' ')}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Earnings */}
                            <div>
                                <h3 className="font-semibold mb-3">Earnings</h3>
                                <div className="space-y-2">
                                    <div className="flex justify-between items-center">
                                        <span>Gross Salary</span>
                                        <span className="font-medium">{formatCurrency(gross)}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Deductions */}
                            <div>
                                <h3 className="font-semibold mb-3">Deductions</h3>
                                <div className="space-y-2">
                                    <div className="flex justify-between items-center text-red-600">
                                        <span>PAYE (Pay As You Earn)</span>
                                        <span>
                                            - {formatCurrency(job.paye_amount)}
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                ({calculatePercentage(parseFloat(job.paye_amount), gross)}%)
                                            </span>
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center text-red-600">
                                        <span>UIF (Unemployment Insurance Fund)</span>
                                        <span>
                                            - {formatCurrency(job.uif_amount)}
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                ({calculatePercentage(parseFloat(job.uif_amount), gross)}%)
                                            </span>
                                        </span>
                                    </div>
                                    
                                    {/* Custom Deductions */}
                                    {custom_deductions && custom_deductions.length > 0 && (
                                        <>
                                            {custom_deductions.map((deduction: any, index: number) => (
                                                <div key={index} className="flex justify-between items-center text-red-600">
                                                    <span>{deduction.name}</span>
                                                    <span>
                                                        - {formatCurrency(deduction.amount)}
                                                        {deduction.type === 'percentage' ? (
                                                            <span className="text-muted-foreground ml-2 text-xs">
                                                                ({parseFloat(deduction.original_amount || deduction.amount).toFixed(2)}%)
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground ml-2 text-xs">
                                                                ({calculatePercentage(parseFloat(deduction.amount), gross)}%)
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* Net Pay */}
                            <div className="border-t pt-4">
                                <div className="flex justify-between items-center text-lg font-bold">
                                    <span>Net Pay</span>
                                    <span className="text-green-600">{formatCurrency(job.net_salary)}</span>
                                </div>
                            </div>

                            {/* Employer Costs */}
                            {parseFloat(job.sdl_amount) > 0 && (
                                <div className="border-t pt-4">
                                    <h3 className="font-semibold mb-2 text-sm text-muted-foreground">Employer Costs (Not Deducted from Employee)</h3>
                                    <div className="flex justify-between items-center text-sm text-muted-foreground">
                                        <span>SDL (Skills Development Levy)</span>
                                        <span>{formatCurrency(job.sdl_amount)}</span>
                                    </div>
                                </div>
                            )}

                            {/* Payment Details */}
                            {job.transaction_id && (
                                <div className="border-t pt-4">
                                    <h3 className="font-semibold mb-2 text-sm">Payment Details</h3>
                                    <div className="text-sm space-y-1">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Transaction ID:</span>
                                            <span className="font-mono">{job.transaction_id}</span>
                                        </div>
                                        {job.processed_at && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Processed At:</span>
                                                <span>{formatDate(job.processed_at)}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Status Badge */}
                            <div className="flex justify-end pt-4 border-t">
                                <span
                                    className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${
                                        job.status === 'succeeded'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                            : job.status === 'failed'
                                              ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                              : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                    }`}
                                >
                                    Status: {job.status.toUpperCase()}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <style>{`
                @media print {
                    .print\\:shadow-none { box-shadow: none !important; }
                    .print\\:border-0 { border: none !important; }
                    .print\\:pb-2 { padding-bottom: 0.5rem !important; }
                    .print\\:space-y-4 > * + * { margin-top: 1rem !important; }
                    button, a[href] { display: none !important; }
                }
            `}</style>
        </AppLayout>
    );
}
