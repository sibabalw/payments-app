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

                <div className="space-y-4">
                    {jobs?.data?.map((job: any) => (
                        <Card key={job.id}>
                            <CardHeader>
                                <CardTitle>{job.payment_schedule?.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">{job.receiver?.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {job.currency} {job.amount}
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
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
