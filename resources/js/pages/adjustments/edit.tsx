import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Adjustments', href: '/adjustments' },
    { title: 'Edit', href: '#' },
];

export default function AdjustmentsEdit({ adjustment, businesses, employee, payrollSchedules }: any) {
    const [isCalculatingPeriod, setIsCalculatingPeriod] = useState(false);
    
    const { data, setData, put, processing, errors } = useForm({
        business_id: adjustment.business_id,
        employee_id: adjustment.employee_id || null,
        payroll_schedule_id: adjustment.payroll_schedule_id || null,
        name: adjustment.name,
        type: adjustment.type,
        amount: adjustment.amount,
        adjustment_type: adjustment.adjustment_type || 'deduction',
        is_recurring: adjustment.is_recurring ?? true,
        payroll_period_start: adjustment.payroll_period_start || '',
        payroll_period_end: adjustment.payroll_period_end || '',
        is_active: adjustment.is_active,
        description: adjustment.description || '',
    });

    // Check if this is an employee-specific adjustment
    const isEmployeeSpecificAdjustment = employee?.id || data.employee_id;

    // Auto-calculate period when schedule changes for employee-specific once-off adjustments
    // Only recalculate if the schedule actually changed from the original
    useEffect(() => {
        const isEmployeeSpecificOnceOff = !data.is_recurring && isEmployeeSpecificAdjustment && data.payroll_schedule_id;
        const scheduleChanged = adjustment.payroll_schedule_id !== data.payroll_schedule_id;
        
        if (isEmployeeSpecificOnceOff && scheduleChanged && data.payroll_schedule_id) {
            setIsCalculatingPeriod(true);
            
            fetch(`/adjustments/calculate-period?payroll_schedule_id=${data.payroll_schedule_id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to calculate period');
                return res.json();
            })
            .then(result => {
                setData('payroll_period_start', result.payroll_period_start);
                setData('payroll_period_end', result.payroll_period_end);
                setIsCalculatingPeriod(false);
            })
            .catch(err => {
                console.error('Failed to calculate period:', err);
                setIsCalculatingPeriod(false);
            });
        }
    }, [data.payroll_schedule_id, data.is_recurring, isEmployeeSpecificAdjustment]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/adjustments/${adjustment.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Adjustment" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Adjustment</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            {employee && (
                                <div className="p-3 bg-muted rounded-lg">
                                    <p className="text-sm text-muted-foreground">Employee-specific adjustment for:</p>
                                    <p className="font-medium">{employee.name}</p>
                                </div>
                            )}

                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={String(data.business_id)}
                                    onValueChange={(value) => setData('business_id', Number(value))}
                                    disabled={!!employee}
                                >
                                    <SelectTrigger>
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

                            <div>
                                <Label htmlFor="name">Adjustment Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Medical Aid, Pension Fund, Bonus"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <Label htmlFor="adjustment_type">Adjustment Type</Label>
                                <Select
                                    value={data.adjustment_type}
                                    onValueChange={(value) => setData('adjustment_type', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="deduction">Deduction (reduces net salary)</SelectItem>
                                        <SelectItem value="addition">Addition (increases net salary)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.adjustment_type} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="type">Calculation Type</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value) => setData('type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="fixed">Fixed Amount</SelectItem>
                                            <SelectItem value="percentage">Percentage</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>

                                <div>
                                    <Label htmlFor="amount">
                                        {data.type === 'percentage' ? 'Percentage (%)' : 'Amount (ZAR)'}
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
                                    {data.type === 'percentage' && (
                                        <p className="text-xs text-muted-foreground mt-1">
                                            Percentage of gross salary
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_recurring"
                                    checked={data.is_recurring}
                                    onCheckedChange={(checked) => {
                                        setData('is_recurring', checked as boolean);
                                        if (checked) {
                                            setData('payroll_period_start', '');
                                            setData('payroll_period_end', '');
                                            setData('payroll_schedule_id', null);
                                        }
                                    }}
                                />
                                <Label htmlFor="is_recurring" className="cursor-pointer">
                                    Recurring (applied automatically on every payroll run)
                                </Label>
                            </div>

                            {!data.is_recurring && (
                                <>
                                    <div>
                                        <Label htmlFor="payroll_schedule_id">Payroll Schedule *</Label>
                                        <Select
                                            value={data.payroll_schedule_id ? String(data.payroll_schedule_id) : ''}
                                            onValueChange={(value) => {
                                                if (value) {
                                                    setData('payroll_schedule_id', Number(value));
                                                } else {
                                                    setData('payroll_schedule_id', null);
                                                    // Clear period when schedule is cleared for employee-specific
                                                    if (isEmployeeSpecificAdjustment) {
                                                        setData('payroll_period_start', '');
                                                        setData('payroll_period_end', '');
                                                    }
                                                }
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select payroll schedule" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {payrollSchedules && payrollSchedules.length > 0 ? (
                                                    payrollSchedules.map((schedule: any) => (
                                                        <SelectItem key={schedule.id} value={String(schedule.id)}>
                                                            {schedule.name} {schedule.next_run_at && `(${new Date(schedule.next_run_at).toLocaleDateString()})`}
                                                        </SelectItem>
                                                    ))
                                                ) : (
                                                    <SelectItem value="" disabled>
                                                        No active payroll schedules found
                                                    </SelectItem>
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.payroll_schedule_id} />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                                        <div>
                                            <Label htmlFor="payroll_period_start">
                                                Payroll Period Start
                                                {isEmployeeSpecificAdjustment && (
                                                    <span className="text-xs text-muted-foreground ml-2">
                                                        (Auto-calculated)
                                                    </span>
                                                )}
                                            </Label>
                                            <Input
                                                id="payroll_period_start"
                                                type="date"
                                                value={data.payroll_period_start}
                                                onChange={(e) => setData('payroll_period_start', e.target.value)}
                                                required={!data.is_recurring}
                                                disabled={isEmployeeSpecificAdjustment}
                                                className={isEmployeeSpecificAdjustment ? 'bg-background' : ''}
                                            />
                                            {isCalculatingPeriod && isEmployeeSpecificAdjustment && (
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    Calculating period from schedule...
                                                </p>
                                            )}
                                            <InputError message={errors.payroll_period_start} />
                                        </div>
                                        <div>
                                            <Label htmlFor="payroll_period_end">
                                                Payroll Period End
                                                {isEmployeeSpecificAdjustment && (
                                                    <span className="text-xs text-muted-foreground ml-2">
                                                        (Auto-calculated)
                                                    </span>
                                                )}
                                            </Label>
                                            <Input
                                                id="payroll_period_end"
                                                type="date"
                                                value={data.payroll_period_end}
                                                onChange={(e) => setData('payroll_period_end', e.target.value)}
                                                required={!data.is_recurring}
                                                disabled={isEmployeeSpecificAdjustment}
                                                className={isEmployeeSpecificAdjustment ? 'bg-background' : ''}
                                            />
                                            <InputError message={errors.payroll_period_end} />
                                        </div>
                                    </div>
                                    
                                    {isEmployeeSpecificAdjustment && data.payroll_period_start && (
                                        <div className="p-3 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                                <strong>Note:</strong> For employee-specific once-off adjustments, the payroll period is automatically calculated from the selected schedule. This ensures the adjustment will match exactly when the schedule processes payroll, preventing it from being applied to other schedules.
                                            </p>
                                        </div>
                                    )}
                                </>
                            )}

                            <div>
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="is_active" className="cursor-pointer">
                                    Active (adjustment will be applied to payroll)
                                </Label>
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Adjustment
                                </Button>
                                <Link href={employee ? `/employees/${employee.id}/edit` : '/adjustments'}>
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
