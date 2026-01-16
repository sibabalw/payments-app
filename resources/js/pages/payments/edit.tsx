import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import payments from '@/routes/payments';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: payments.index().url },
    { title: 'Edit', href: '#' },
];

interface PaymentsEditProps {
    schedule: any;
    businesses: Array<{ id: number; name: string }>;
    recipients: Array<{ id: number; name: string }>;
}

export default function PaymentsEdit({ schedule, businesses, recipients }: PaymentsEditProps) {
    // Parse scheduled date/time from schedule (provided by backend parser) or use defaults
    const scheduledDate = schedule.scheduled_date 
        ? new Date(schedule.scheduled_date + 'T' + (schedule.scheduled_time || '00:00'))
        : undefined;
    const scheduledTime = schedule.scheduled_time || '09:00';
    const parsedFrequency = schedule.parsed_frequency || 'daily';

    const { data, setData, put, processing, errors } = useForm({
        business_id: schedule.business_id,
        name: schedule.name,
        schedule_type: schedule.schedule_type || 'recurring',
        scheduled_date: schedule.scheduled_date || '',
        scheduled_time: scheduledTime,
        frequency: parsedFrequency,
        amount: String(schedule.amount),
        currency: schedule.currency,
        recipient_ids: schedule.recipients?.map((r: any) => r.id) || schedule.receivers?.map((r: any) => r.id) || [],
    });

    const isReadOnly = schedule.status === 'cancelled';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/payments/${schedule.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Payment Schedule" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Payment Schedule</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={String(data.business_id)}
                                    onValueChange={(value) => setData('business_id', Number(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {businesses.map((business) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.business_id} />
                            </div>

                            <div>
                                <Label htmlFor="name">Schedule Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <Label htmlFor="schedule_type">Schedule Type</Label>
                                {isReadOnly ? (
                                    <div className="flex items-center gap-2 mt-2">
                                        <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                            data.schedule_type === 'one_time'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                        }`}>
                                            {data.schedule_type === 'one_time' ? 'One-time' : 'Recurring'}
                                        </span>
                                    </div>
                                ) : (
                                    <>
                                        <Select
                                            value={data.schedule_type}
                                            onValueChange={(value) => setData('schedule_type', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="recurring">Recurring Payment</SelectItem>
                                                <SelectItem value="one_time">One-time Payment</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.schedule_type} />
                                    </>
                                )}
                            </div>

                            <div>
                                <Label>Schedule Date & Time</Label>
                                {isReadOnly ? (
                                    <div className="mt-2">
                                        <p className="text-sm text-muted-foreground">
                                            {scheduledDate ? scheduledDate.toLocaleDateString() : 'N/A'} at {scheduledTime}
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <DatePicker
                                            date={scheduledDate}
                                            onDateChange={(date) => {
                                                if (date) {
                                                    setData('scheduled_date', date.toISOString().split('T')[0]);
                                                    setData('scheduled_time', date.toTimeString().slice(0, 5));
                                                }
                                            }}
                                            time={data.scheduled_time}
                                            onTimeChange={(time) => setData('scheduled_time', time)}
                                            showTime={true}
                                        />
                                        <InputError message={errors.scheduled_date} />
                                        <InputError message={errors.scheduled_time} />
                                        <p className="text-xs text-muted-foreground mt-1">
                                            Weekends and South Africa public holidays are not allowed
                                        </p>
                                    </>
                                )}
                            </div>

                            {data.schedule_type === 'recurring' && (
                                <div>
                                    <Label htmlFor="frequency">Frequency</Label>
                                    {isReadOnly ? (
                                        <div className="mt-2">
                                            <p className="text-sm text-muted-foreground capitalize">{parsedFrequency}</p>
                                        </div>
                                    ) : (
                                        <>
                                            <Select
                                                value={data.frequency}
                                                onValueChange={(value) => setData('frequency', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="daily">Daily</SelectItem>
                                                    <SelectItem value="weekly">Weekly</SelectItem>
                                                    <SelectItem value="monthly">Monthly</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.frequency} />
                                        </>
                                    )}
                                </div>
                            )}

                            <div>
                                <Label htmlFor="amount">Amount (ZAR)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div>
                                <Label>Recipients</Label>
                                <div className="space-y-2 mt-2">
                                    {recipients.length > 0 ? (
                                        recipients.map((recipient: { id: number; name: string }) => (
                                            <label key={recipient.id} className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                    checked={data.recipient_ids.includes(recipient.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                            setData('recipient_ids', [...data.recipient_ids, recipient.id]);
                                                    } else {
                                                            setData('recipient_ids', data.recipient_ids.filter((id: number) => id !== recipient.id));
                                                    }
                                                }}
                                            />
                                                <span>{recipient.name}</span>
                                        </label>
                                        ))
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No recipients found. <Link href="/recipients/create" className="text-primary underline">Create one</Link>
                                        </p>
                                    )}
                                </div>
                                <InputError message={errors.recipient_ids} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Schedule
                                </Button>
                                <Link href="/payments">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
