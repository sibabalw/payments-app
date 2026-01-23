import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState, useMemo, useRef, useEffect } from 'react';
import { X, ChevronDown } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Adjustments', href: '/adjustments' },
    { title: 'Create', href: '#' },
];

export default function AdjustmentsCreate({ businesses, selectedBusinessId, employee, employees, payrollSchedules: initialPayrollSchedules }: any) {
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    const employeeIdFromUrl = urlParams.get('employee_id');

    const [isEmployeeSpecific, setIsEmployeeSpecific] = useState(!!(employee?.id || employeeIdFromUrl));
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [isPopoverOpen, setIsPopoverOpen] = useState(false);
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [selectedEmployee, setSelectedEmployee] = useState<any>(employee || null);
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    const [payrollSchedules, setPayrollSchedules] = useState(initialPayrollSchedules || []);
    const [isCalculatingPeriod, setIsCalculatingPeriod] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businessIdFromUrl || businesses[0]?.id || '',
        employee_id: employee?.id || employeeIdFromUrl || null,
        payroll_schedule_id: null,
        name: '',
        type: 'fixed',
        amount: '',
        adjustment_type: 'deduction',
        is_recurring: true,
        payroll_period_start: '',
        payroll_period_end: '',
        is_active: true,
        description: '',
    });

    // Fetch payroll schedules when business or employee changes
    useEffect(() => {
        if (data.business_id) {
            const params: any = { business_id: String(data.business_id) };
            if (data.employee_id) {
                params.employee_id = String(data.employee_id);
            }
            
            router.get('/adjustments/create', params, {
                preserveState: true,
                only: ['payrollSchedules'],
                onSuccess: (page: any) => {
                    setPayrollSchedules(page.props.payrollSchedules || []);
                },
            });
        } else {
            setPayrollSchedules([]);
        }
    }, [data.business_id, data.employee_id]);

    // Debounce search input
    useEffect(() => {
        // Clear previous timeout
        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

        // If search is empty or no business selected, clear results
        if (!employeeSearch.trim() || !data.business_id) {
            setSearchResults([]);
            return;
        }

        // Set new timeout for debounced search
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
                } else {
                    setSearchResults([]);
                }
            } catch (error) {
                console.error('Error searching employees:', error);
                setSearchResults([]);
            } finally {
                setIsSearching(false);
            }
        }, 300); // 300ms debounce

        return () => {
            if (searchTimeoutRef.current) {
                clearTimeout(searchTimeoutRef.current);
            }
        };
    }, [employeeSearch, data.business_id]);

    // Open popover when search input is focused and business is selected
    const handleSearchFocus = () => {
        if (data.business_id) {
            setIsPopoverOpen(true);
        }
    };

    const handleEmployeeSelect = (emp: any) => {
        setData('employee_id', emp.id);
        setSelectedEmployee(emp);
        setEmployeeSearch('');
        setIsPopoverOpen(false);
        setSearchResults([]);
    };

    const handleClearEmployee = () => {
        setData('employee_id', null);
        setSelectedEmployee(null);
        setEmployeeSearch('');
        setIsPopoverOpen(false);
        setSearchResults([]);
    };

    // Update selected employee when employee_id changes
    useEffect(() => {
        if (data.employee_id && selectedEmployee?.id !== data.employee_id) {
            // If we have the employee in search results, use it
            const found = searchResults.find((emp: any) => emp.id === data.employee_id);
            if (found) {
                setSelectedEmployee(found);
            }
        } else if (!data.employee_id) {
            setSelectedEmployee(null);
        }
    }, [data.employee_id, searchResults]);

    // Auto-calculate period when schedule is selected for employee-specific once-off adjustments
    // This ensures the adjustment period matches exactly what the schedule will process
    useEffect(() => {
        // Only for employee-specific once-off adjustments
        const isEmployeeSpecificOnceOff = !data.is_recurring && (data.employee_id || employee?.id) && data.payroll_schedule_id && data.business_id;
        
        if (isEmployeeSpecificOnceOff) {
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
        } else if (!data.is_recurring && !data.employee_id && !employee?.id) {
            // Company-wide once-off: clear period if schedule changes (user can manually enter)
        }
    }, [data.payroll_schedule_id, data.is_recurring, data.employee_id, employee?.id]);

    // Check if this is an employee-specific adjustment (either from URL or checkbox)
    const isEmployeeSpecificAdjustment = employee?.id || data.employee_id || isEmployeeSpecific;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // If employee-specific is unchecked and no employee from URL, ensure employee_id is null
        if (!isEmployeeSpecific && !employee) {
            setData('employee_id', null);
        }
        
        post('/adjustments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Adjustment" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Create Adjustment</CardTitle>
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
                                    onValueChange={(value) => {
                                        setData('business_id', Number(value));
                                        // Clear employee selection when business changes
                                        if (isEmployeeSpecific) {
                                            setData('employee_id', null);
                                        }
                                    }}
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

                            {!employee && (
                                <>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_employee_specific"
                                            checked={isEmployeeSpecific}
                                            onCheckedChange={(checked) => {
                                                setIsEmployeeSpecific(checked as boolean);
                                                if (!checked) {
                                                    setData('employee_id', null);
                                                    setEmployeeSearch('');
                                                }
                                            }}
                                        />
                                        <Label htmlFor="is_employee_specific" className="cursor-pointer">
                                            Employee-specific adjustment
                                        </Label>
                                    </div>

                                    {isEmployeeSpecific && (
                                        <div>
                                            <Label htmlFor="employee_id">Employee</Label>
                                            <div className="space-y-2">
                                                {selectedEmployee ? (
                                                    <div className="flex items-center justify-between p-3 border rounded-md bg-muted/50">
                                                        <div>
                                                            <p className="font-medium">{selectedEmployee.name}</p>
                                                            {selectedEmployee.email && (
                                                                <p className="text-sm text-muted-foreground">{selectedEmployee.email}</p>
                                                            )}
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-6 w-6"
                                                            onClick={handleClearEmployee}
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <Popover open={isPopoverOpen} onOpenChange={setIsPopoverOpen}>
                                                        <PopoverTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                role="combobox"
                                                                aria-expanded={isPopoverOpen}
                                                                className="w-full justify-between"
                                                                disabled={!data.business_id}
                                                                onClick={() => {
                                                                    if (data.business_id) {
                                                                        setIsPopoverOpen(true);
                                                                    }
                                                                }}
                                                            >
                                                                <span className="text-muted-foreground">
                                                                    {data.business_id ? 'Search employees...' : 'Select business first'}
                                                                </span>
                                                                <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                                                            <div className="p-2">
                                                                <Input
                                                                    placeholder="Search by name or email..."
                                                                    value={employeeSearch}
                                                                    onChange={(e) => {
                                                                        setEmployeeSearch(e.target.value);
                                                                    }}
                                                                    onFocus={handleSearchFocus}
                                                                    className="mb-2"
                                                                />
                                                                <div className="max-h-60 overflow-auto">
                                                                    {isSearching ? (
                                                                        <div className="p-3 text-center text-sm text-muted-foreground">
                                                                            Searching...
                                                                        </div>
                                                                    ) : searchResults.length > 0 ? (
                                                                        <div className="space-y-1">
                                                                            {searchResults.map((emp: any) => (
                                                                                <button
                                                                                    key={emp.id}
                                                                                    type="button"
                                                                                    onClick={() => handleEmployeeSelect(emp)}
                                                                                    className="w-full text-left px-3 py-2 rounded-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none transition-colors"
                                                                                >
                                                                                    <p className="font-medium">{emp.name}</p>
                                                                                    {emp.email && (
                                                                                        <p className="text-sm text-muted-foreground">{emp.email}</p>
                                                                                    )}
                                                                                </button>
                                                                            ))}
                                                                        </div>
                                                                    ) : (
                                                                        <div className="p-3 text-center text-sm text-muted-foreground">
                                                                            {employeeSearch.trim() ? 'No employees found' : 'Start typing to search employees'}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </PopoverContent>
                                                    </Popover>
                                                )}
                                            </div>
                                            <InputError message={errors.employee_id} />
                                            {!data.business_id && (
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    Please select a business first to see employees
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}

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
                                            disabled={!data.business_id}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={data.business_id ? "Select payroll schedule" : "Select business first"} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {payrollSchedules.length > 0 ? (
                                                    payrollSchedules.map((schedule: any) => (
                                                        <SelectItem key={schedule.id} value={String(schedule.id)}>
                                                            {schedule.name} {schedule.next_run_at && `(${new Date(schedule.next_run_at).toLocaleDateString()})`}
                                                        </SelectItem>
                                                    ))
                                                ) : (
                                                    <SelectItem value="" disabled>
                                                        {data.business_id ? 'No active payroll schedules found' : 'Select business first'}
                                                    </SelectItem>
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.payroll_schedule_id} />
                                        {!data.business_id && (
                                            <p className="text-xs text-muted-foreground mt-1">
                                                Please select a business first to see payroll schedules
                                            </p>
                                        )}
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
                                    Create Adjustment
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
