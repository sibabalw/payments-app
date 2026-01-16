import { useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import InputError from '@/components/input-error';

export default function PayrollEdit({ schedule, businesses, employees, employeeTaxBreakdowns }: any) {
    // Parse scheduled date/time from schedule (provided by backend parser) or use defaults
    const initialScheduledDate = schedule.scheduled_date 
        ? new Date(schedule.scheduled_date + 'T' + (schedule.scheduled_time || '00:00'))
        : undefined;
    const initialScheduledTime = schedule.scheduled_time || '09:00';
    const parsedFrequency = schedule.parsed_frequency || 'monthly';

    const { data, setData, put, processing, errors } = useForm({
        business_id: schedule.business_id,
        name: schedule.name,
        schedule_type: schedule.schedule_type || 'recurring',
        scheduled_date: schedule.scheduled_date || '',
        scheduled_time: initialScheduledTime,
        frequency: parsedFrequency,
        employee_ids: schedule.employees?.map((e: any) => e.id) || [],
    });

    // Derive current date from form data (so it updates when user changes it)
    const scheduledDate = data.scheduled_date 
        ? new Date(data.scheduled_date + 'T' + (data.scheduled_time || '00:00'))
        : initialScheduledDate;

    const isReadOnly = schedule.status === 'cancelled';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/payroll/${schedule.id}`);
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Payroll', href: '/payroll' }, { title: 'Edit', href: '#' }]}>
            <Head title="Edit Payroll Schedule" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Payroll Schedule</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="name">Payroll Name</Label>
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
                                            {scheduledDate ? scheduledDate.toLocaleDateString() : 'N/A'} at {data.scheduled_time}
                                        </p>
                                    </div>
                                ) : (
                                    <>
                                        <DatePicker
                                            date={scheduledDate}
                                            onDateChange={(date) => {
                                                if (date) {
                                                    setData('scheduled_date', date.toISOString().split('T')[0]);
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
                                <Label>Employees</Label>
                                {isReadOnly ? (
                                    <div className="mt-2">
                                        <div className="space-y-1">
                                            {schedule.employees && schedule.employees.length > 0 ? (
                                                schedule.employees.map((employee: any) => (
                                                    <div key={employee.id} className="p-2 border rounded">
                                                        <p className="text-sm font-medium">{employee.name}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            Gross: ZAR {parseFloat(employee.gross_salary).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                        </p>
                                                        {employeeTaxBreakdowns && employeeTaxBreakdowns[employee.id] && (
                                                            <p className="text-xs text-green-600 mt-1">
                                                                Net: ZAR {parseFloat(employeeTaxBreakdowns[employee.id].net).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                    </p>
                                                        )}
                                                    </div>
                                                ))
                                            ) : (
                                                <p className="text-sm text-muted-foreground">No employees assigned</p>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="space-y-2 mt-2">
                                            {employees.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    No employees found for this business. <Link href="/employees/create" className="text-primary underline">Create an employee</Link> first.
                                                </p>
                                            ) : (
                                                employees.map((employee: any) => (
                                                    <label key={employee.id} className="flex items-center space-x-2 p-2 border rounded">
                                                        <input
                                                            type="checkbox"
                                                            checked={data.employee_ids.includes(employee.id)}
                                                            onChange={(e) => {
                                                                if (e.target.checked) {
                                                                    setData('employee_ids', [...data.employee_ids, employee.id]);
                                                                } else {
                                                                    setData('employee_ids', data.employee_ids.filter((id: number) => id !== employee.id));
                                                                }
                                                            }}
                                                        />
                                                        <div className="flex-1">
                                                            <span className="font-medium">{employee.name}</span>
                                                            <p className="text-xs text-muted-foreground">
                                                                Gross: ZAR {parseFloat(employee.gross_salary).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                            </p>
                                                            {employeeTaxBreakdowns && employeeTaxBreakdowns[employee.id] && (
                                                                <p className="text-xs text-green-600 mt-1">
                                                                    Net: ZAR {parseFloat(employeeTaxBreakdowns[employee.id].net).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </label>
                                                ))
                                            )}
                                        </div>
                                        <InputError message={errors.employee_ids} />
                                    </>
                                )}
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Payroll
                                </Button>
                                <Link href="/payroll">
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
