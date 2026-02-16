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
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import InputError from '@/components/input-error';
import { isBusinessDay } from '@/lib/sa-holidays';
import { Plus, X } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: '/payments' },
    { title: 'Create Schedule', href: '#' },
];

export default function PaymentScheduleCreate({ businesses, recipients, selectedBusinessId, escrowBalance }: any) {
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

    const [whoGetsThis, setWhoGetsThis] = useState<'all' | 'select'>('all');
    const [selectedRecipientIds, setSelectedRecipientIds] = useState<number[]>([]);

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businesses?.[0]?.id || '',
        name: '',
        schedule_type: 'recurring',
        scheduled_date: defaultDate.toISOString().split('T')[0],
        scheduled_time: defaultTime,
        frequency: 'monthly',
        amount: '',
        recipient_ids: [] as number[],
    });

    // Update recipients when business changes
    useEffect(() => {
        if (data.business_id) {
            setSelectedRecipientIds([]);
            setData('recipient_ids', []);
            setWhoGetsThis('all');
        }
    }, [data.business_id]);

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
        
        // Set recipient_ids based on selection
        if (whoGetsThis === 'all') {
            // All recipients - backend will need all recipient IDs
            if (recipients && recipients.length > 0) {
                const allIds = recipients.map((r: any) => r.id);
                setData('recipient_ids', allIds);
            } else {
                setData('recipient_ids', []);
            }
        } else if (whoGetsThis === 'select') {
            // Selected recipients
            setData('recipient_ids', selectedRecipientIds);
        }
        
        post('/payments');
    };

    const filteredRecipients = recipients || [];

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
                            {businesses && businesses.length > 0 && (
                                <div>
                                    <Label htmlFor="business_id">Business</Label>
                                    <Select
                                        value={String(data.business_id)}
                                        onValueChange={(value) => setData('business_id', value)}
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
                                    placeholder="0.00"
                                    required
                                />
                                <InputError message={errors.amount} />
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
                                    date={data.scheduled_date ? (() => {
                                        const [year, month, day] = data.scheduled_date.split('-').map(Number);
                                        const [hours, minutes] = (data.scheduled_time || '00:00').split(':').map(Number);
                                        return new Date(year, month - 1, day, hours, minutes);
                                    })() : undefined}
                                    onDateChange={(date) => {
                                        if (date) {
                                            // Use local date formatting to avoid timezone issues
                                            const year = date.getFullYear();
                                            const month = String(date.getMonth() + 1).padStart(2, '0');
                                            const day = String(date.getDate()).padStart(2, '0');
                                            setData('scheduled_date', `${year}-${month}-${day}`);
                                            const hours = String(date.getHours()).padStart(2, '0');
                                            const minutes = String(date.getMinutes()).padStart(2, '0');
                                            setData('scheduled_time', `${hours}:${minutes}`);
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
                                <Label>Who receives this payment?</Label>
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
                                                if (checked) {
                                                    setWhoGetsThis('select');
                                                }
                                            }}
                                        />
                                        <Label htmlFor="select_recipients" className="cursor-pointer font-normal">
                                            Select Recipients
                                        </Label>
                                    </div>
                                    <InputError message={errors.recipient_ids} />
                                </div>

                                {whoGetsThis === 'select' && (
                                    <div className="space-y-2 mt-4">
                                        <Label>Select Recipients</Label>
                                        {!data.business_id ? (
                                            <p className="text-sm text-muted-foreground">
                                                Please select a business first.
                                            </p>
                                        ) : filteredRecipients.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No recipients found for this business. <Link href="/recipients/create" className="text-primary underline">Create a recipient</Link> first.
                                            </p>
                                        ) : (
                                            <>
                                                <div className="space-y-2 max-h-60 overflow-auto border rounded-md p-2">
                                                    {filteredRecipients.map((recipient: any) => (
                                                        <div key={recipient.id} className="flex items-center space-x-2">
                                                            <Checkbox
                                                                id={`recipient_${recipient.id}`}
                                                                checked={selectedRecipientIds.includes(recipient.id)}
                                                                onCheckedChange={(checked) => {
                                                                    if (checked) {
                                                                        handleAddRecipient(recipient);
                                                                    } else {
                                                                        handleRemoveRecipient(recipient.id);
                                                                    }
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
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>

                            {(whoGetsThis === 'all' || selectedRecipientIds.length > 0) && data.amount && (
                                <Card className="bg-muted">
                                    <CardHeader>
                                        <CardTitle className="text-lg">Payment Summary</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <p className="text-sm">
                                                <span className="text-muted-foreground">Recipients: </span>
                                                <span className="font-medium">
                                                    {whoGetsThis === 'all' 
                                                        ? `All recipients (${filteredRecipients.length} total)`
                                                        : `${selectedRecipientIds.length} recipient(s) selected`
                                                    }
                                                </span>
                                            </p>
                                            <p className="text-sm">
                                                <span className="text-muted-foreground">Amount per recipient: </span>
                                                <span className="font-medium">
                                                    {new Intl.NumberFormat('en-ZA', {
                                                        style: 'currency',
                                                        currency: 'ZAR',
                                                    }).format(parseFloat(data.amount))}
                                                </span>
                                            </p>
                                            <p className="text-sm">
                                                <span className="text-muted-foreground">Total amount: </span>
                                                <span className="font-medium">
                                                    {new Intl.NumberFormat('en-ZA', {
                                                        style: 'currency',
                                                        currency: 'ZAR',
                                                    }).format(
                                                        parseFloat(data.amount) * (whoGetsThis === 'all' 
                                                            ? filteredRecipients.length 
                                                            : selectedRecipientIds.length
                                                        )
                                                    )}
                                                </span>
                                            </p>
                                            {escrowBalance !== null && escrowBalance !== undefined && (
                                                <p className="text-sm mt-2">
                                                    <span className="text-muted-foreground">Available Escrow Balance: </span>
                                                    <span className={parseFloat(data.amount) * (whoGetsThis === 'all' 
                                                        ? filteredRecipients.length 
                                                        : selectedRecipientIds.length
                                                    ) > escrowBalance ? 'text-red-600 font-medium' : ''}>
                                                        {new Intl.NumberFormat('en-ZA', {
                                                            style: 'currency',
                                                            currency: 'ZAR',
                                                        }).format(escrowBalance)}
                                                    </span>
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Schedule'}
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
