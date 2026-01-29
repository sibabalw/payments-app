import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarIcon } from 'lucide-react';
import { useState, useEffect } from 'react';
import { format } from 'date-fns';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Bonuses', href: '/payroll/bonuses' },
    { title: 'Edit Bonus', href: '#' },
];

export default function PaymentsEdit({ payment, businesses, employees }: any) {
    if (!payment) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Edit Bonus" />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">Bonus not found.</p>
                            <Link href="/payroll/bonuses" className="mt-4 inline-block">
                                <Button variant="outline">Back to Bonuses</Button>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </AppLayout>
        );
    }

    const [periodStart, setPeriodStart] = useState<Date | undefined>(
        payment.period_start ? new Date(payment.period_start) : undefined
    );
    const [periodEnd, setPeriodEnd] = useState<Date | undefined>(
        payment.period_end ? new Date(payment.period_end) : undefined
    );

    const { data, setData, put, processing, errors } = useForm({
        business_id: payment.business_id,
        employee_id: payment.employee_id || null,
        name: payment.name || '',
        type: payment.type || 'fixed',
        amount: payment.amount || '',
        adjustment_type: payment.adjustment_type || 'addition',
        period_start: payment.period_start || '',
        period_end: payment.period_end || '',
        is_active: payment.is_active ?? true,
        description: payment.description || '',
    });

    // Update form data when dates change
    useEffect(() => {
        if (periodStart) {
            setData('period_start', format(periodStart, 'yyyy-MM-dd'));
        }
    }, [periodStart]);

    useEffect(() => {
        if (periodEnd) {
            setData('period_end', format(periodEnd, 'yyyy-MM-dd'));
        }
    }, [periodEnd]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/payroll-payments/${payment.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Payment" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Edit Payment</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Update payment details
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Bonus Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {payment.employee && (
                                <div className="p-3 bg-muted rounded-lg">
                                    <p className="text-sm text-muted-foreground">Bonus for:</p>
                                    <p className="font-medium">{payment.employee.name}</p>
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="name">Bonus Type</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Performance Bonus"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="type">Amount Type</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => setData('type', value as 'fixed' | 'percentage')}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fixed">Fixed amount</SelectItem>
                                        <SelectItem value="percentage">Percentage of salary</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="amount">
                                    {data.type === 'percentage' ? 'Percentage' : 'Amount'} 
                                    {data.type === 'percentage' && ' (0-100)'}
                                </Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step={data.type === 'percentage' ? '0.01' : '0.01'}
                                    min="0"
                                    max={data.type === 'percentage' ? '100' : undefined}
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>From Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full justify-start text-left font-normal"
                                            >
                                                <CalendarIcon className="mr-2 h-4 w-4" />
                                                {periodStart ? format(periodStart, 'PPP') : 'Pick a date'}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={periodStart}
                                                onSelect={setPeriodStart}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <InputError message={errors.period_start} />
                                </div>

                                <div className="space-y-2">
                                    <Label>To Date</Label>
                                    <Popover>
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="w-full justify-start text-left font-normal"
                                            >
                                                <CalendarIcon className="mr-2 h-4 w-4" />
                                                {periodEnd ? format(periodEnd, 'PPP') : 'Pick a date'}
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-auto p-0" align="start">
                                            <Calendar
                                                mode="single"
                                                selected={periodEnd}
                                                onSelect={setPeriodEnd}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <InputError message={errors.period_end} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex gap-2">
                                <Link href="/payroll/bonuses">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Updating...' : 'Update Bonus'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
