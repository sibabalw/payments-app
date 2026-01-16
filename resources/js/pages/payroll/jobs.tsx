import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Jobs', href: '#' },
];

export default function PayrollJobs({ jobs }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Payroll Jobs</h1>

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
                                            <div className="flex justify-between text-sm">
                                                <span>Gross Salary:</span>
                                                <span className="font-medium">ZAR {parseFloat(job.gross_salary).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                            <div className="flex justify-between text-sm text-red-600">
                                                <span>PAYE:</span>
                                                <span>- ZAR {parseFloat(job.paye_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                            <div className="flex justify-between text-sm text-red-600">
                                                <span>UIF:</span>
                                                <span>- ZAR {parseFloat(job.uif_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                            <div className="flex justify-between text-sm text-red-600">
                                                <span>SDL:</span>
                                                <span>- ZAR {parseFloat(job.sdl_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                            <div className="border-t pt-2 flex justify-between font-bold">
                                                <span>Net Salary (Paid):</span>
                                                <span className="text-green-600">ZAR {parseFloat(job.net_salary).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                            </div>
                                        </div>

                                        {job.error_message && (
                                            <div className="mt-2 p-2 bg-red-50 dark:bg-red-900/20 rounded text-sm text-red-600">
                                                {job.error_message}
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
