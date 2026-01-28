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

export default function PayrollCreate({ businesses, employees, selectedBusinessId, escrowBalance }: any) {
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
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<number[]>([]);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businesses[0]?.id || '',
        name: '',
        schedule_type: 'recurring',
        scheduled_date: defaultDate.toISOString().split('T')[0],
        scheduled_time: defaultTime,
        frequency: 'monthly',
        employee_ids: [] as number[],
    });

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
            // All employees - set employee_ids to empty array (backend will interpret as all)
            setData('employee_ids', []);
        } else if (whoGetsThis === 'select') {
            // Selected employees
            setData('employee_ids', selectedEmployeeIds);
        }
        
        post('/payroll');
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Payroll', href: '/payroll' }, { title: 'Create', href: '#' }]}>
            <Head title="Create Payroll Schedule" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Payroll Schedule</CardTitle>
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
                                <Label>Who gets this payroll?</Label>
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
                            </div>

                            {(whoGetsThis === 'all' || selectedEmployeeIds.length > 0) && (
                                <Card className="bg-muted">
                                    <CardHeader>
                                        <CardTitle className="text-lg">Total Payroll Summary</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            {whoGetsThis === 'all' 
                                                ? `All employees (${employees.length} total). Tax calculations will be performed automatically for each employee when the payroll runs.`
                                                : `${selectedEmployeeIds.length} employee(s) selected. Tax calculations will be performed automatically for each employee when the payroll runs.`
                                            }
                                        </p>
                                        {escrowBalance !== null && escrowBalance !== undefined && (
                                            <p className="text-sm mt-2">
                                                Available Escrow Balance: {new Intl.NumberFormat('en-ZA', {
                                                    style: 'currency',
                                                    currency: 'ZAR',
                                                }).format(escrowBalance)}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Create Payroll
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
