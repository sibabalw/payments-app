import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
];

export default function PayrollIndex({ schedules, filters }: any) {
    const handlePause = (id: number) => {
        router.post(`/payroll/${id}/pause`);
    };

    const handleResume = (id: number) => {
        router.post(`/payroll/${id}/resume`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payroll" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Payroll Schedules</h1>
                    <Link href="/payroll/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Payroll
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4">
                    {schedules?.data?.map((schedule: any) => (
                        <Card key={schedule.id}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>{schedule.name}</CardTitle>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            {schedule.receivers?.length || 0} employee(s) • {schedule.frequency}
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
                                                Next run: {new Date(schedule.next_run_at).toLocaleString()}
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
                                            <Button variant="outline" size="sm" onClick={() => {
                                                if (confirm('Are you sure you want to cancel this schedule?')) {
                                                    router.post(`/payroll/${schedule.id}/cancel`);
                                                }
                                            }}>
                                                Cancel
                                            </Button>
                                        )}
                                        <Link href={`/payroll/${schedule.id}/edit`}>
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
            </div>
        </AppLayout>
    );
}
