import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave Management', href: '/leave' },
    { title: 'Edit', href: '#' },
];

export default function LeaveEdit({ leaveEntry, businesses, employees }: any) {
    const { data, setData, put, processing, errors } = useForm({
        business_id: leaveEntry.business_id,
        employee_id: leaveEntry.employee_id,
        leave_type: leaveEntry.leave_type,
        start_date: leaveEntry.start_date,
        end_date: leaveEntry.end_date,
        hours: leaveEntry.hours || '',
        notes: leaveEntry.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/leave/${leaveEntry.id}`);
    };

    const filteredEmployees = employees.filter((emp: any) => 
        emp.business_id === Number(data.business_id)
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Leave Entry" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Leave Entry</CardTitle>
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
                                <Label htmlFor="employee_id">Employee</Label>
                                <Select
                                    value={String(data.employee_id)}
                                    onValueChange={(value) => setData('employee_id', Number(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredEmployees.map((employee: any) => (
                                            <SelectItem key={employee.id} value={String(employee.id)}>
                                                {employee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.employee_id} />
                            </div>

                            <div>
                                <Label htmlFor="leave_type">Leave Type</Label>
                                <Select
                                    value={data.leave_type}
                                    onValueChange={(value) => setData('leave_type', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="paid">Paid Leave</SelectItem>
                                        <SelectItem value="unpaid">Unpaid Leave</SelectItem>
                                        <SelectItem value="sick">Sick Leave</SelectItem>
                                        <SelectItem value="public_holiday">Public Holiday</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.leave_type} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="start_date">Start Date</Label>
                                    <Input
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.start_date} />
                                </div>

                                <div>
                                    <Label htmlFor="end_date">End Date</Label>
                                    <Input
                                        id="end_date"
                                        type="date"
                                        value={data.end_date}
                                        onChange={(e) => setData('end_date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            </div>

                            {data.leave_type === 'paid' && (
                                <div>
                                    <Label htmlFor="hours">Hours (for paid leave)</Label>
                                    <Input
                                        id="hours"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.hours}
                                        onChange={(e) => setData('hours', e.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Leave empty to auto-calculate (8 hours per day)
                                    </p>
                                    <InputError message={errors.hours} />
                                </div>
                            )}

                            <div>
                                <Label htmlFor="notes">Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Leave Entry
                                </Button>
                                <Link href="/leave">
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
