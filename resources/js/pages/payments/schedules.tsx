import ConfirmationDialog from '@/components/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Play, Pause, X, Edit, Calendar, DollarSign, Users, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { cronToHumanReadable } from '@/lib/cronUtils';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: '/payments' },
];

export default function PaymentSchedulesIndex({ schedules, filters }: any) {
    const [statusFilter, setStatusFilter] = useState(filters?.status || 'all');
    const [businessId, setBusinessId] = useState(filters?.business_id || '');
    const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
    const [scheduleToDelete, setScheduleToDelete] = useState<number | null>(null);
    const [scheduleToDeleteName, setScheduleToDeleteName] = useState<string>('');

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get('/payments', { status: value === 'all' ? '' : value, business_id: businessId }, { preserveState: true });
    };

    const handlePause = (scheduleId: number) => {
        if (confirm('Are you sure you want to pause this payment schedule?')) {
            router.post(`/payments/${scheduleId}/pause`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    // Page will refresh automatically
                },
            });
        }
    };

    const handleResume = (scheduleId: number) => {
        router.post(`/payments/${scheduleId}/resume`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Page will refresh automatically
            },
        });
    };

    const handleCancel = (scheduleId: number) => {
        if (confirm('Are you sure you want to cancel this payment schedule? This action cannot be undone.')) {
            router.post(`/payments/${scheduleId}/cancel`, {}, {
                preserveScroll: true,
                onSuccess: () => {
                    // Page will refresh automatically
                },
            });
        }
    };

    const handleDelete = (scheduleId: number, name: string) => {
        setScheduleToDelete(scheduleId);
        setScheduleToDeleteName(name);
        setDeleteConfirmOpen(true);
    };

    const confirmDelete = () => {
        if (scheduleToDelete) {
            router.delete(`/payments/${scheduleToDelete}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteConfirmOpen(false);
                    setScheduleToDelete(null);
                    setScheduleToDeleteName('');
                },
            });
        }
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<string, string> = {
            active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };

        return (
            <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${variants[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'}`}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Schedules" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Payment Schedules</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Manage recurring payment schedules for recipients
                        </p>
                    </div>
                    <Link href="/payments/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Schedule
                        </Button>
                    </Link>
                </div>

                <div className="flex gap-4">
                    <div className="max-w-xs">
                        <label className="text-sm font-medium mb-2 block">Status</label>
                        <Select value={statusFilter || 'all'} onValueChange={handleStatusChange}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="paused">Paused</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {schedules?.data && schedules.data.length > 0 ? (
                    <div className="grid gap-4">
                        {schedules.data.map((schedule: any) => (
                            <Card key={schedule.id}>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <CardTitle>{schedule.name}</CardTitle>
                                                {getStatusBadge(schedule.status)}
                                            </div>
                                            <div className="flex items-center gap-4 mt-2 text-sm text-muted-foreground">
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-4 w-4" />
                                                    {cronToHumanReadable(schedule.frequency)}
                                                </div>
                                                {schedule.recipients && (
                                                    <div className="flex items-center gap-1">
                                                        <Users className="h-4 w-4" />
                                                        {schedule.recipients.length} recipient(s)
                                                    </div>
                                                )}
                                                {schedule.amount && (
                                                    <div className="flex items-center gap-1">
                                                        <DollarSign className="h-4 w-4" />
                                                        {schedule.currency} {parseFloat(schedule.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </div>
                                                )}
                                            </div>
                                            {schedule.next_run_at && (
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    Next run: {new Date(schedule.next_run_at).toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Link href={`/payments/${schedule.id}/edit`}>
                                                <Button variant="outline" size="sm">
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleDelete(schedule.id, schedule.name)}
                                                className="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-950"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete
                                            </Button>
                                            {schedule.status === 'active' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handlePause(schedule.id)}
                                                >
                                                    <Pause className="mr-2 h-4 w-4" />
                                                    Pause
                                                </Button>
                                            )}
                                            {schedule.status === 'paused' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleResume(schedule.id)}
                                                >
                                                    <Play className="mr-2 h-4 w-4" />
                                                    Resume
                                                </Button>
                                            )}
                                            {schedule.status !== 'cancelled' && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleCancel(schedule.id)}
                                                >
                                                    <X className="mr-2 h-4 w-4" />
                                                    Cancel
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                {schedule.business && (
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            Business: {schedule.business.name}
                                        </p>
                                    </CardContent>
                                )}
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No payment schedules found.</p>
                            <p className="text-sm text-muted-foreground mt-2">
                                Create a payment schedule to start making recurring payments to recipients.
                            </p>
                            <Link href="/payments/create" className="mt-4 inline-block">
                                <Button>Create your first schedule</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {schedules?.links && (
                    <div className="flex justify-center gap-2">
                        {schedules.links.map((link: any, index: number) => (
                            <Link
                                key={index}
                                href={link.url || '#'}
                                className={`px-3 py-2 rounded-md text-sm ${
                                    link.active
                                        ? 'bg-primary text-primary-foreground'
                                        : link.url
                                            ? 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                            : 'bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-gray-800 dark:text-gray-600'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}

                <ConfirmationDialog
                    open={deleteConfirmOpen}
                    onOpenChange={setDeleteConfirmOpen}
                    onConfirm={confirmDelete}
                    title="Permanently Delete Payment Schedule"
                    description={`Are you sure you want to permanently delete "${scheduleToDeleteName}"? This action cannot be undone and will permanently remove the schedule and all associated data.`}
                    confirmText="Delete Permanently"
                    variant="destructive"
                />
            </div>
        </AppLayout>
    );
}
