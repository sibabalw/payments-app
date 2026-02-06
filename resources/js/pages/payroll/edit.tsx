import { useForm, router } from '@inertiajs/react';
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
import { Plus, X } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';

interface EmployeeAlreadyPaid {
    id: number;
    name: string;
    overlapping_jobs: Array<{ period_start: string; period_end: string; status: string }>;
}

export default function PayrollEdit({
    schedule,
    businesses,
    employees,
    employeeTaxBreakdowns,
    employees_already_paid_this_period = [],
}: {
    schedule: any;
    businesses: any;
    employees: any;
    employeeTaxBreakdowns?: any;
    employees_already_paid_this_period?: EmployeeAlreadyPaid[];
}) {
    // Parse scheduled date/time from schedule (provided by backend parser) or use defaults
    const initialScheduledDate = schedule.scheduled_date 
        ? new Date(schedule.scheduled_date + 'T' + (schedule.scheduled_time || '00:00'))
        : undefined;
    const initialScheduledTime = schedule.scheduled_time || '09:00';
    const parsedFrequency = schedule.parsed_frequency || 'monthly';

    // Determine if all employees are selected
    const scheduleEmployeeIds = schedule.employees?.map((e: any) => e.id) || [];
    const totalEmployeesCount = employees?.length || 0;
    const isAllEmployees = scheduleEmployeeIds.length === totalEmployeesCount && totalEmployeesCount > 0;

    const [whoGetsThis, setWhoGetsThis] = useState<'all' | 'select'>(isAllEmployees ? 'all' : 'select');
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<number[]>(scheduleEmployeeIds);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [includeInNextRunIds, setIncludeInNextRunIds] = useState<number[]>([]);
    const [includeInNextRunProcessing, setIncludeInNextRunProcessing] = useState(false);
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // Already paid this period: at least one overlapping job with status succeeded
    const alreadyPaidThisPeriod = employees_already_paid_this_period.filter((emp) =>
        emp.overlapping_jobs?.some((job) => job.status === 'succeeded')
    );
    // Pending/processing this period: overlapping jobs only pending or processing (can be "included in next run")
    const pendingProcessingThisPeriod = employees_already_paid_this_period.filter((emp) =>
        emp.overlapping_jobs?.every((job) => job.status === 'pending' || job.status === 'processing')
    );

    const { data, setData, put, processing, errors } = useForm({
        business_id: schedule.business_id,
        name: schedule.name,
        schedule_type: schedule.schedule_type || 'recurring',
        scheduled_date: schedule.scheduled_date || '',
        scheduled_time: initialScheduledTime,
        frequency: parsedFrequency,
        employee_ids: scheduleEmployeeIds,
    });

    // Derive current date from form data (so it updates when user changes it)
    const scheduledDate = data.scheduled_date 
        ? new Date(data.scheduled_date + 'T' + (data.scheduled_time || '00:00'))
        : initialScheduledDate;

    const isReadOnly = schedule.status === 'cancelled';

    // Employee search
    useEffect(() => {
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        if (!employeeSearch.trim() || !data.business_id) {
            setSearchResults([]);
            return;
        }

        searchTimeoutRef.current = setTimeout(async () => {
            setIsSearching(true);
            try {
                const params = new URLSearchParams({
                    business_id: String(data.business_id),
                    query: employeeSearch.trim(),
                });

                const response = await fetch(`/employees/search?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    const results = await response.json();
                    setSearchResults(results);
                }
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                setIsSearching(false);
            }
        }, 300);
    }, [employeeSearch, data.business_id]);

    const handleAddEmployee = (employee: any) => {
        if (!selectedEmployeeIds.includes(employee.id)) {
            const newIds = [...selectedEmployeeIds, employee.id];
            setSelectedEmployeeIds(newIds);
            setData('employee_ids', newIds);
        }
        setEmployeeSearch('');
        setSearchResults([]);
    };

    const handleRemoveEmployee = (employeeId: number) => {
        const newIds = selectedEmployeeIds.filter(id => id !== employeeId);
        setSelectedEmployeeIds(newIds);
        setData('employee_ids', newIds);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Set employee_ids based on selection
        if (whoGetsThis === 'all') {
            // All employees - send all employee IDs
            const allEmployeeIds = employees?.map((emp: any) => emp.id) || [];
            setData('employee_ids', allEmployeeIds);
        } else if (whoGetsThis === 'select') {
            // Selected employees
            setData('employee_ids', selectedEmployeeIds);
        }
        
        put(`/payroll/${schedule.id}`);
    };

    const handleIncludeInNextRun = (e: React.FormEvent) => {
        e.preventDefault();
        if (includeInNextRunIds.length === 0) return;
        setIncludeInNextRunProcessing(true);
        router.post(`/payroll/${schedule.id}/cancel-overlapping-jobs`, { employee_ids: includeInNextRunIds }, {
            preserveScroll: true,
            onFinish: () => setIncludeInNextRunProcessing(false),
        });
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

                            {schedule.next_run_at_missing && (
                                <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                                    Next run not set. Set a date and time below and save to recalculate.
                                </div>
                            )}

                            {alreadyPaidThisPeriod.length > 0 && (
                                <div className="rounded-md border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-100">
                                    <p className="font-medium">
                                        {alreadyPaidThisPeriod.length} employee(s) already paid for this period will be skipped on the next run.
                                    </p>
                                    <ul className="mt-2 list-inside list-disc space-y-1">
                                        {alreadyPaidThisPeriod.map((emp) => (
                                            <li key={emp.id}>
                                                <span className="font-medium">{emp.name}</span>
                                                {emp.overlapping_jobs?.length > 0 && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        — {emp.overlapping_jobs.map((job) => `${job.period_start} to ${job.period_end} (${job.status})`).join(', ')}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {pendingProcessingThisPeriod.length > 0 && (
                                <div className="rounded-md border border-blue-200 bg-blue-50 px-3 py-3 text-sm text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-100">
                                    <p className="font-medium">
                                        {pendingProcessingThisPeriod.length} employee(s) with a pending or in-progress payment for this period will be skipped.
                                    </p>
                                    <ul className="mt-2 list-inside list-disc space-y-1">
                                        {pendingProcessingThisPeriod.map((emp) => (
                                            <li key={emp.id}>
                                                <span className="font-medium">{emp.name}</span>
                                                {emp.overlapping_jobs?.length > 0 && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        — {emp.overlapping_jobs.map((job) => `${job.period_start} to ${job.period_end} (${job.status})`).join(', ')}
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                    {!isReadOnly && (
                                        <form onSubmit={handleIncludeInNextRun} className="mt-3 space-y-2">
                                            <p className="text-xs font-medium">Include in next run (cancels pending/processing payment for this period):</p>
                                            <div className="flex flex-wrap items-center gap-3">
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <Checkbox
                                                        checked={
                                                            pendingProcessingThisPeriod.length > 0 &&
                                                            pendingProcessingThisPeriod.every((emp) => includeInNextRunIds.includes(emp.id))
                                                        }
                                                        onCheckedChange={(checked) => {
                                                            if (checked) {
                                                                setIncludeInNextRunIds(pendingProcessingThisPeriod.map((emp) => emp.id));
                                                            } else {
                                                                setIncludeInNextRunIds((prev) =>
                                                                    prev.filter((id) => !pendingProcessingThisPeriod.some((e) => e.id === id))
                                                                );
                                                            }
                                                        }}
                                                    />
                                                    <span className="text-xs font-medium">Select all</span>
                                                </label>
                                            </div>
                                            <div className="flex flex-wrap gap-3">
                                                {pendingProcessingThisPeriod.map((emp) => (
                                                    <label key={emp.id} className="flex items-center gap-2 cursor-pointer">
                                                        <Checkbox
                                                            checked={includeInNextRunIds.includes(emp.id)}
                                                            onCheckedChange={(checked) => {
                                                                if (checked) {
                                                                    setIncludeInNextRunIds((prev) => [...prev, emp.id]);
                                                                } else {
                                                                    setIncludeInNextRunIds((prev) => prev.filter((id) => id !== emp.id));
                                                                }
                                                            }}
                                                        />
                                                        <span>{emp.name}</span>
                                                    </label>
                                                ))}
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="secondary"
                                                size="sm"
                                                disabled={includeInNextRunIds.length === 0 || includeInNextRunProcessing}
                                            >
                                                {includeInNextRunProcessing ? 'Processing…' : 'Include selected in next run'}
                                            </Button>
                                        </form>
                                    )}
                                </div>
                            )}

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
                                <Label>Who gets this payroll?</Label>
                                {isReadOnly ? (
                                    <div className="mt-2">
                                        <div className="space-y-1">
                                            {isAllEmployees ? (
                                                <p className="text-sm text-muted-foreground">All employees ({totalEmployeesCount} total)</p>
                                            ) : schedule.employees && schedule.employees.length > 0 ? (
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
                                        <div className="space-y-4 mt-2">
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    id="all_employees"
                                                    checked={whoGetsThis === 'all'}
                                                    onCheckedChange={(checked) => {
                                                        if (checked) {
                                                            setWhoGetsThis('all');
                                                            setSelectedEmployeeIds([]);
                                                            setData('employee_ids', []);
                                                        }
                                                    }}
                                                />
                                                <Label htmlFor="all_employees" className="cursor-pointer font-normal">
                                                    All Employees
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Checkbox
                                                    id="select_employees"
                                                    checked={whoGetsThis === 'select'}
                                                    onCheckedChange={(checked) => {
                                                        if (checked) {
                                                            setWhoGetsThis('select');
                                                        }
                                                    }}
                                                />
                                                <Label htmlFor="select_employees" className="cursor-pointer font-normal">
                                                    Select Employees
                                                </Label>
                                            </div>
                                            <InputError message={errors.employee_ids} />
                                        </div>

                                        {whoGetsThis === 'select' && (
                                            <div className="space-y-2 mt-4">
                                                <Label>Select Employees</Label>
                                                {employees.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">
                                                        No employees found for this business. <Link href="/employees/create" className="text-primary underline">Create an employee</Link> first.
                                                    </p>
                                                ) : (
                                                    <>
                                                        <Popover>
                                                            <PopoverTrigger asChild>
                                                                <Button type="button" variant="outline" className="w-full justify-start">
                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                    {isSearching ? 'Searching...' : 'Search and add employees'}
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent className="w-80 p-0" align="start">
                                                                <div className="p-2">
                                                                    <Input
                                                                        placeholder="Search employees..."
                                                                        value={employeeSearch}
                                                                        onChange={(e) => setEmployeeSearch(e.target.value)}
                                                                    />
                                                                </div>
                                                                <div className="max-h-60 overflow-auto">
                                                                    {searchResults.length > 0 ? (
                                                                        <div className="p-2 space-y-1">
                                                                            {searchResults.map((emp: any) => (
                                                                                <Button
                                                                                    key={emp.id}
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    className="w-full justify-start"
                                                                                    onClick={() => handleAddEmployee(emp)}
                                                                                    disabled={selectedEmployeeIds.includes(emp.id)}
                                                                                >
                                                                                    {emp.name}
                                                                                    {emp.email && (
                                                                                        <span className="text-xs text-muted-foreground ml-2">
                                                                                            ({emp.email})
                                                                                        </span>
                                                                                    )}
                                                                                </Button>
                                                                            ))}
                                                                        </div>
                                                                    ) : employeeSearch && !isSearching && (
                                                                        <div className="p-4 text-center text-sm text-muted-foreground">
                                                                            No employees found
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </PopoverContent>
                                                        </Popover>

                                                        {selectedEmployeeIds.length > 0 && (
                                                            <div className="space-y-2 mt-2">
                                                                {selectedEmployeeIds.map((empId) => {
                                                                    const emp = employees?.find((e: any) => e.id === empId);
                                                                    if (!emp) return null;
                                                                    return (
                                                                        <div key={empId} className="flex items-center justify-between p-2 border rounded-md">
                                                                            <div className="flex-1">
                                                                                <span className="font-medium">{emp.name}</span>
                                                                                <p className="text-xs text-muted-foreground">
                                                                                    Gross: ZAR {parseFloat(emp.gross_salary || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                                                </p>
                                                                                {employeeTaxBreakdowns && employeeTaxBreakdowns[empId] && (
                                                                                    <p className="text-xs text-green-600 mt-1">
                                                                                        Net: ZAR {parseFloat(employeeTaxBreakdowns[empId].net).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                                                                    </p>
                                                                                )}
                                                                            </div>
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="h-6 w-6"
                                                                                onClick={() => handleRemoveEmployee(empId)}
                                                                            >
                                                                                <X className="h-4 w-4" />
                                                                            </Button>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        )}
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
