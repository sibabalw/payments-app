import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: '/employees' },
    { title: 'Schedule', href: '#' },
];

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export default function EmployeeSchedule({ employee, schedules }: any) {
    const { data, setData, put, processing, errors } = useForm({
        schedules: schedules.map((s: any) => ({
            day_of_week: s.day_of_week,
            scheduled_hours: s.scheduled_hours,
            is_active: s.is_active,
        })),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/employees/${employee.id}/schedule`);
    };

    const updateSchedule = (index: number, field: string, value: any) => {
        const newSchedules = [...data.schedules];
        newSchedules[index] = { ...newSchedules[index], [field]: value };
        setData('schedules', newSchedules);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${employee.name} - Schedule`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex justify-between items-center">
                            <div>
                                <CardTitle>Work Schedule: {employee.name}</CardTitle>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Set scheduled hours for each day of the week. Overtime is calculated when hours worked exceed scheduled hours.
                                </p>
                            </div>
                            <Link href={`/employees/${employee.id}/edit`}>
                                <Button variant="outline">Back to Employee</Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-3">
                                {data.schedules.map((schedule: any, index: number) => (
                                    <div key={schedule.day_of_week} className="flex items-center gap-4 p-3 border rounded-lg">
                                        <div className="flex items-center space-x-2 w-32">
                                            <Checkbox
                                                id={`day_${schedule.day_of_week}`}
                                                checked={schedule.is_active}
                                                onCheckedChange={(checked) => 
                                                    updateSchedule(index, 'is_active', checked)
                                                }
                                            />
                                            <Label 
                                                htmlFor={`day_${schedule.day_of_week}`}
                                                className="font-medium cursor-pointer"
                                            >
                                                {DAY_NAMES[schedule.day_of_week]}
                                            </Label>
                                        </div>

                                        <div className="flex-1 flex items-center gap-2">
                                            <Label htmlFor={`hours_${schedule.day_of_week}`} className="text-sm">
                                                Hours:
                                            </Label>
                                            <Input
                                                id={`hours_${schedule.day_of_week}`}
                                                type="number"
                                                step="0.25"
                                                min="0"
                                                max="24"
                                                value={schedule.scheduled_hours}
                                                onChange={(e) => 
                                                    updateSchedule(index, 'scheduled_hours', parseFloat(e.target.value) || 0)
                                                }
                                                disabled={!schedule.is_active}
                                                className="w-24"
                                            />
                                            <span className="text-sm text-muted-foreground">per day</span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Save Schedule
                                </Button>
                                <Link href={`/employees/${employee.id}/edit`}>
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
