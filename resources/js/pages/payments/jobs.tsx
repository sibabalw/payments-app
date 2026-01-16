import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import payments from '@/routes/payments';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: payments.index().url },
    { title: 'Jobs', href: '#' },
];

interface PaymentJob {
    id: number;
    status: string;
    amount: string;
    currency: string;
    processed_at: string | null;
    error_message: string | null;
    receiver: { name: string };
    payment_schedule: { name: string };
}

interface PaymentsJobsProps {
    jobs: {
        data: PaymentJob[];
        links: any;
    };
}

export default function PaymentsJobs({ jobs }: PaymentsJobsProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Payment Jobs</h1>

                <div className="space-y-4">
                    {jobs.data.map((job) => (
                        <Card key={job.id}>
                            <CardHeader>
                                <CardTitle>{job.payment_schedule.name}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">{job.receiver.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {job.currency} {job.amount}
                                        </p>
                                        {job.processed_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Processed: {new Date(job.processed_at).toLocaleString()}
                                            </p>
                                        )}
                                        {job.error_message && (
                                            <p className="text-xs text-red-600 dark:text-red-400 mt-1">
                                                Error: {job.error_message}
                                            </p>
                                        )}
                                    </div>
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                            job.status === 'succeeded'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : job.status === 'failed'
                                                  ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                  : job.status === 'processing'
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
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

                {jobs.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No payment jobs found.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
