import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: '/payments' },
    { title: 'Edit Schedule', href: '#' },
];

export default function PaymentScheduleEdit({ schedule, businesses, recipients }: any) {
    const scheduleRecipientIds = schedule?.recipients?.map((r: any) => r.id) || [];
    const totalRecipientsCount = recipients?.length || 0;
    const isAllRecipients = totalRecipientsCount > 0 && scheduleRecipientIds.length === totalRecipientsCount;

    const [whoGetsThis, setWhoGetsThis] = useState<'all' | 'select'>(isAllRecipients ? 'all' : 'select');
    const [selectedRecipientIds, setSelectedRecipientIds] = useState<number[]>(scheduleRecipientIds);

    const initialScheduledTime = schedule?.scheduled_time || '09:00';
    const parsedFrequency = schedule?.parsed_frequency || 'monthly';

    const { data, setData, put, processing, errors } = useForm({
        business_id: schedule?.business_id ?? '',
        name: schedule?.name ?? '',
        schedule_type: schedule?.schedule_type ?? 'recurring',
        scheduled_date: schedule?.scheduled_date ?? '',
        scheduled_time: initialScheduledTime,
        frequency: parsedFrequency,
        amount: schedule?.amount ?? '',
        currency: schedule?.currency ?? 'ZAR',
        recipient_ids: scheduleRecipientIds,
    });

    const scheduledDate = data.scheduled_date
        ? new Date(data.scheduled_date + 'T' + (data.scheduled_time || '00:00'))
        : undefined;

    const isReadOnly = schedule?.status === 'cancelled';

    const handleAddRecipient = (recipient: any) => {
        if (!selectedRecipientIds.includes(recipient.id)) {
            const newIds = [...selectedRecipientIds, recipient.id];
            setSelectedRecipientIds(newIds);
            setData('recipient_ids', newIds);
        }
    };

    const handleRemoveRecipient = (recipientId: number) => {
        const newIds = selectedRecipientIds.filter(id => id !== recipientId);
        setSelectedRecipientIds(newIds);
        setData('recipient_ids', newIds);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (whoGetsThis === 'all' && recipients?.length) {
            setData('recipient_ids', recipients.map((r: any) => r.id));
        } else {
            setData('recipient_ids', selectedRecipientIds);
        }
        put(`/payments/${schedule.id}`);
    };

    const filteredRecipients = recipients || [];

    if (!schedule) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Edit Payment Schedule" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">Schedule not found.</p>
                            <Link href="/payments" className="mt-4 inline-block">
                                <Button variant="outline">Back to Payments</Button>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

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
                            {businesses?.length > 0 && (
                                <div>
                                    <Label htmlFor="business_id">Business</Label>
                                    <Select
                                        value={String(data.business_id)}
                                        onValueChange={(value) => setData('business_id', value)}
                                        disabled={isReadOnly}
                                    >
                                        <SelectTrigger id="business_id">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {businesses.map((business: any) => (
                                                <SelectItem key={business.id} value={String(business.id)}>
                                                    {business.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.business_id} />
                                </div>
                            )}

                            <div>
                                <Label htmlFor="name">Schedule Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Monthly Vendor Payments"
                                    required
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <Label htmlFor="amount">Amount (ZAR)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    required
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div>
                                <Label htmlFor="schedule_type">Schedule Type</Label>
                                {isReadOnly ? (
                                    <p className="text-sm text-muted-foreground mt-2 capitalize">
                                        {data.schedule_type === 'one_time' ? 'One-time' : 'Recurring'}
                                    </p>
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

                            {schedule?.next_run_at_missing && (
                                <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                    Next run not set. Set a date and time below and save to recalculate.
                                </div>
                            )}

                            <div>
                                <Label>Schedule Date & Time</Label>
                                {isReadOnly ? (
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {scheduledDate ? scheduledDate.toLocaleDateString() : 'N/A'} at {data.scheduled_time}
                                    </p>
                                ) : (
                                    <>
                                        <DatePicker
                                            date={scheduledDate}
                                            onDateChange={(date) => {
                                                if (date) {
                                                    const y = date.getFullYear();
                                                    const m = String(date.getMonth() + 1).padStart(2, '0');
                                                    const d = String(date.getDate()).padStart(2, '0');
                                                    setData('scheduled_date', `${y}-${m}-${d}`);
                                                } else {
                                                    setData('scheduled_date', '');
                                                }
                                            }}
                                            time={data.scheduled_time}
                                            onTimeChange={(time) => setData('scheduled_time', time)}
                                            showTime={true}
                                        />
                                        <InputError message={errors.scheduled_date} />
                                        <InputError message={errors.scheduled_time} />
                                    </>
                                )}
                            </div>

                            {data.schedule_type === 'recurring' && (
                                <div>
                                    <Label htmlFor="frequency">Frequency</Label>
                                    {isReadOnly ? (
                                        <p className="text-sm text-muted-foreground mt-2 capitalize">{parsedFrequency}</p>
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
                                <Label>Who receives this payment?</Label>
                                {isReadOnly ? (
                                    <p className="text-sm text-muted-foreground mt-2">
                                        {scheduleRecipientIds.length} recipient(s) selected
                                    </p>
                                ) : (
                                    <>
                                        <div className="space-y-4 mt-2">
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    id="all_recipients"
                                                    checked={whoGetsThis === 'all'}
                                                    onCheckedChange={(checked) => {
                                                        if (checked) {
                                                            setWhoGetsThis('all');
                                                            setSelectedRecipientIds([]);
                                                        }
                                                    }}
                                                />
                                                <Label htmlFor="all_recipients" className="cursor-pointer font-normal">
                                                    All Recipients
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    id="select_recipients"
                                                    checked={whoGetsThis === 'select'}
                                                    onCheckedChange={(checked) => {
                                                        if (checked) setWhoGetsThis('select');
                                                    }}
                                                />
                                                <Label htmlFor="select_recipients" className="cursor-pointer font-normal">
                                                    Select Recipients
                                                </Label>
                                            </div>
                                        </div>
                                        <InputError message={errors.recipient_ids} />
                                        {whoGetsThis === 'select' && (
                                            <div className="space-y-2 mt-4 max-h-60 overflow-auto border rounded-md p-2">
                                                {filteredRecipients.map((recipient: any) => (
                                                    <div key={recipient.id} className="flex items-center space-x-2">
                                                        <Checkbox
                                                            id={`recipient_${recipient.id}`}
                                                            checked={selectedRecipientIds.includes(recipient.id)}
                                                            onCheckedChange={(checked) => {
                                                                if (checked) handleAddRecipient(recipient);
                                                                else handleRemoveRecipient(recipient.id);
                                                            }}
                                                        />
                                                        <Label
                                                            htmlFor={`recipient_${recipient.id}`}
                                                            className="cursor-pointer font-normal flex-1"
                                                        >
                                                            {recipient.name}
                                                            {recipient.email && (
                                                                <span className="text-xs text-muted-foreground ml-2">
                                                                    ({recipient.email})
                                                                </span>
                                                            )}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>

                            <div className="flex gap-2">
                                {!isReadOnly && (
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save Changes'}
                                    </Button>
                                )}
                                <Link href="/payments">
                                    <Button type="button" variant="outline">
                                        {isReadOnly ? 'Back' : 'Cancel'}
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
