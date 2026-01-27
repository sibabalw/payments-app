import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { format } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Benefits & Deductions', href: '/benefits' },
    { title: 'Temporarily Change', href: '#' },
];

export default function BenefitsTemporarilyChange({ benefit }: any) {
    const [periodStart, setPeriodStart] = useState<Date | undefined>(undefined);
    const [periodEnd, setPeriodEnd] = useState<Date | undefined>(undefined);

    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        period_start: '',
        period_end: '',
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/benefits/${benefit.id}/temporarily-change`);
    };

    // Update form data when dates change
    const handleStartDateChange = (date: Date | undefined) => {
        setPeriodStart(date);
        if (date) {
            setData('period_start', format(date, 'yyyy-MM-dd'));
        }
    };

    const handleEndDateChange = (date: Date | undefined) => {
        setPeriodEnd(date);
        if (date) {
            setData('period_end', format(date, 'yyyy-MM-dd'));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Temporarily Change Benefit" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Temporarily Change Benefit</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Change the amount for a specific period, then it will revert to the original
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Temporary Change for {benefit.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-6 p-4 bg-muted rounded-lg">
                            <p className="text-sm text-muted-foreground">Current amount:</p>
                            <p className="text-lg font-semibold">
                                {benefit.type === 'percentage' 
                                    ? `${benefit.amount}%`
                                    : `ZAR ${parseFloat(benefit.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                }
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="amount">
                                    New Amount
                                    {benefit.type === 'percentage' && ' (0-100)'}
                                </Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step={benefit.type === 'percentage' ? '0.01' : '0.01'}
                                    min="0"
                                    max={benefit.type === 'percentage' ? '100' : undefined}
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    placeholder={benefit.type === 'percentage' ? 'e.g., 6' : 'e.g., 600'}
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
                                                onSelect={handleStartDateChange}
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
                                                onSelect={handleEndDateChange}
                                                initialFocus
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <InputError message={errors.period_end} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="e.g., Temporary increase for Q1"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    <strong>Note:</strong> All employees will receive the new amount ({data.amount || '...'}) 
                                    from {periodStart ? format(periodStart, 'MMM d, yyyy') : 'start date'} to {periodEnd ? format(periodEnd, 'MMM d, yyyy') : 'end date'}. 
                                    After that, it will automatically revert to {benefit.type === 'percentage' ? `${benefit.amount}%` : `ZAR ${benefit.amount}`}.
                                </p>
                            </div>

                            <div className="flex gap-2">
                                <Link href="/benefits">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Temporary Change'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
