import ConfirmationDialog from '@/components/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import payments from '@/routes/payments';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { cronToHumanReadable } from '@/lib/cronUtils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: payments.index().url },
];

function formatNextRunDate(dateString: string): string {
    const date = new Date(dateString);
    const options: Intl.DateTimeFormatOptions = {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    };
    return date.toLocaleDateString('en-US', options);
}

interface PaymentSchedule {
    id: number;
    name: string;
    type: string;
    status: string;
    schedule_type?: string;
    amount: string;
    currency: string;
    frequency: string;
    next_run_at: string | null;
    receivers?: Array<{ id: number; name: string }>;
    recipients?: Array<{ id: number; name: string }>;
}

interface PaymentsIndexProps {
    schedules: {
        data: PaymentSchedule[];
        links: any;
    };
    filters: {
        type?: string;
        status?: string;
        business_id?: number;
    };
}

export default function PaymentsIndex({ schedules, filters }: PaymentsIndexProps) {
    const [cancelConfirmOpen, setCancelConfirmOpen] = useState(false);
    const [scheduleToCancel, setScheduleToCancel] = useState<number | null>(null);

    const handlePause = (id: number) => {
        router.post(`/payments/${id}/pause`);
    };

    const handleResume = (id: number) => {
        router.post(`/payments/${id}/resume`);
    };

    const handleCancel = (id: number) => {
        setScheduleToCancel(id);
        setCancelConfirmOpen(true);
    };

    const confirmCancel = () => {
        if (scheduleToCancel) {
            router.post(`/payments/${scheduleToCancel}/cancel`, {
                onSuccess: () => {
                    setCancelConfirmOpen(false);
                    setScheduleToCancel(null);
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Payment Schedules</h1>
                    <div className="flex gap-2">
                        <Link href="/recipients/create">
                            <Button variant="outline">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Recipient
                            </Button>
                        </Link>
                    <Link href="/payments/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Schedule
                        </Button>
                    </Link>
                    </div>
                </div>

                <div className="grid gap-4">
                    {schedules.data.map((schedule) => (
                        <Card key={schedule.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>{schedule.name}</CardTitle>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            {(schedule.recipients?.length || schedule.receivers?.length || 0)} recipient(s) • {cronToHumanReadable(schedule.frequency)}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                schedule.schedule_type === 'one_time'
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                    : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                            }`}
                                        >
                                            {schedule.schedule_type === 'one_time' ? 'One-time' : 'Recurring'}
                                        </span>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                                schedule.status === 'active'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : schedule.status === 'paused'
                                                      ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                                      : schedule.schedule_type === 'one_time' && schedule.status === 'cancelled'
                                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                            }`}
                                        >
                                            {schedule.schedule_type === 'one_time' && schedule.status === 'cancelled' ? 'Completed' : schedule.status}
                                        </span>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-2xl font-bold">
                                            {schedule.currency} {schedule.amount}
                                        </p>
                                        {schedule.next_run_at && (
                                            <p className="text-sm text-muted-foreground">
                                                Next run: {formatNextRunDate(schedule.next_run_at)}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        {schedule.status === 'active' && (
                                            <Button variant="outline" size="sm" onClick={() => handlePause(schedule.id)}>
                                                Pause
                                            </Button>
                                        )}
                                        {schedule.status === 'paused' && (
                                            <Button variant="outline" size="sm" onClick={() => handleResume(schedule.id)}>
                                                Resume
                                            </Button>
                                        )}
                                        {schedule.status !== 'cancelled' && (
                                            <Button variant="outline" size="sm" onClick={() => handleCancel(schedule.id)}>
                                                Cancel
                                            </Button>
                                        )}
                                        <Link href={`/payments/${schedule.id}/edit`}>
                                            <Button variant="outline" size="sm">
                                                Edit
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {schedules.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No payment schedules found.</p>
                            <Link href="/payments/create" className="mt-4 inline-block">
                                <Button>Create your first schedule</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmationDialog
                open={cancelConfirmOpen}
                onOpenChange={setCancelConfirmOpen}
                onConfirm={confirmCancel}
                title="Are you sure you want to cancel this schedule?"
                description="This action cannot be undone. The payment schedule will be cancelled and no further payments will be processed."
                confirmText="Cancel Schedule"
                variant="destructive"
            />
        </AppLayout>
    );
}
