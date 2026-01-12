import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import { payments } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { isBusinessDay } from '@/lib/sa-holidays';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: payments.index().url },
    { title: 'Create', href: '#' },
];

interface PaymentsCreateProps {
    businesses: Array<{ id: number; name: string }>;
    receivers: Array<{ id: number; name: string }>;
    selectedBusinessId?: number;
    type?: string;
    escrowBalance?: number | null;
}

export default function PaymentsCreate({ businesses, receivers, selectedBusinessId, type = 'generic', escrowBalance }: PaymentsCreateProps) {
    // Get next business day as default date
    const getDefaultDate = () => {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        if (!isBusinessDay(tomorrow)) {
            const next = new Date(tomorrow);
            while (!isBusinessDay(next)) {
                next.setDate(next.getDate() + 1);
            }
            return next;
        }
        return tomorrow;
    };

    const defaultDate = getDefaultDate();
    const defaultTime = '09:00';

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businesses[0]?.id || '',
        type: type,
        name: '',
        schedule_type: 'recurring',
        scheduled_date: defaultDate.toISOString().split('T')[0],
        scheduled_time: defaultTime,
        frequency: 'daily',
        amount: '',
        currency: 'ZAR',
        receiver_ids: [] as number[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/payments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Payment Schedule" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Payment Schedule</CardTitle>
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
                                <p className="text-xs text-muted-foreground mt-1">
                                    {data.schedule_type === 'one_time' 
                                        ? 'Payment will execute once at the scheduled time and then be cancelled'
                                        : 'Payment will execute repeatedly based on the selected frequency'}
                                </p>
                            </div>

                            <div>
                                <Label>Schedule Date & Time</Label>
                                <DatePicker
                                    date={data.scheduled_date ? new Date(data.scheduled_date + 'T' + (data.scheduled_time || '00:00')) : undefined}
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
                            </div>

                            {data.schedule_type === 'recurring' && (
                                <div>
                                    <Label htmlFor="frequency">Frequency</Label>
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
                                    <p className="text-xs text-muted-foreground mt-1">
                                        {data.frequency === 'daily' && 'Payment will execute every day at the selected time'}
                                        {data.frequency === 'weekly' && 'Payment will execute every week on the same weekday at the selected time'}
                                        {data.frequency === 'monthly' && 'Payment will execute every month on the same day at the selected time'}
                                    </p>
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
                                {escrowBalance !== null && escrowBalance !== undefined && (
                                    <div className="mt-2">
                                        <p className="text-sm text-muted-foreground">
                                            Available Escrow Balance: {new Intl.NumberFormat('en-ZA', {
                                                style: 'currency',
                                                currency: 'ZAR',
                                            }).format(escrowBalance)}
                                        </p>
                                        {data.amount && Number(data.amount) > escrowBalance && (
                                            <p className="text-sm text-red-600 dark:text-red-400 mt-1 font-medium">
                                                ⚠️ Insufficient escrow balance. Please make a deposit first.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div>
                                <Label>Receivers</Label>
                                <div className="space-y-2 mt-2">
                                    {receivers.map((receiver) => (
                                        <label key={receiver.id} className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                checked={data.receiver_ids.includes(receiver.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setData('receiver_ids', [...data.receiver_ids, receiver.id]);
                                                    } else {
                                                        setData('receiver_ids', data.receiver_ids.filter(id => id !== receiver.id));
                                                    }
                                                }}
                                            />
                                            <span>{receiver.name}</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.receiver_ids} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Create Schedule
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
