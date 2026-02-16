import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { CalendarIcon, X, Plus } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { format } from 'date-fns';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Bonuses', href: '/payroll/bonuses' },
    { title: 'Add Bonus', href: '#' },
];

export default function PaymentsCreate({ businesses, selectedBusinessId, employee, employees }: any) {
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');
    const employeeIdFromUrl = urlParams.get('employee_id');

    const [whoGetsThis, setWhoGetsThis] = useState<'all' | 'select'>(employee?.id || employeeIdFromUrl ? 'select' : 'all');
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<number[]>(employee?.id ? [employee.id] : []);
    const [employeeSearch, setEmployeeSearch] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    // Period selection - simple month picker
    const currentMonth = new Date().toISOString().slice(0, 7);
    const nextMonth = new Date(Date.now() + 30*24*60*60*1000).toISOString().slice(0, 7);
    const [selectedPeriod, setSelectedPeriod] = useState<string>(currentMonth);

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businessIdFromUrl || businesses[0]?.id || '',
        employee_id: employee?.id || employeeIdFromUrl || null,
        employee_ids: [] as number[],
        name: '',
        type: 'fixed',
        amount: '',
        adjustment_type: 'addition', // Bonuses are usually additions
        period_start: '',
        period_end: '',
        is_active: true,
        description: '',
    });

    // Auto-set period from selected month
    useEffect(() => {
        if (selectedPeriod) {
            const [year, month] = selectedPeriod.split('-');
            const startDate = new Date(parseInt(year), parseInt(month) - 1, 1);
            const endDate = new Date(parseInt(year), parseInt(month), 0);
            setData('period_start', format(startDate, 'yyyy-MM-dd'));
            setData('period_end', format(endDate, 'yyyy-MM-dd'));
        }
    }, [selectedPeriod]);

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
            setSelectedEmployeeIds([...selectedEmployeeIds, employee.id]);
            setData('employee_ids', [...selectedEmployeeIds, employee.id]);
        }
        setEmployeeSearch('');
        setSearchResults([]);
    };

    const handleRemoveEmployee = (employeeId: number) => {
        const newIds = selectedEmployeeIds.filter(id => id !== employeeId);
        setSelectedEmployeeIds(newIds);
        setData('employee_ids', newIds);
        if (newIds.length === 0) {
            setData('employee_id', null);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Set employee_ids based on selection
        if (whoGetsThis === 'all') {
            setData('employee_id', null);
            setData('employee_ids', []);
        } else if (whoGetsThis === 'select') {
            if (selectedEmployeeIds.length === 1) {
                // Single employee - use employee_id
                setData('employee_id', selectedEmployeeIds[0]);
                setData('employee_ids', []);
            } else {
                // Multiple employees - use employee_ids
                setData('employee_id', null);
                setData('employee_ids', selectedEmployeeIds);
            }
        }
        
        post('/payroll/bonuses');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Bonus" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Add Bonus</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            One-time bonus or allowance for a specific period
                        </p>
                    </div>
                </div>

                {employee && (
                    <div className="p-3 bg-muted rounded-lg">
                        <p className="text-sm text-muted-foreground">Bonus for:</p>
                        <p className="font-medium">{employee.name}</p>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Bonus Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {businesses && businesses.length > 0 && (
                                <div className="space-y-2">
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

                            {!employee && (
                                <div className="space-y-4">
                                    <Label>Who gets this?</Label>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="all_employees"
                                            checked={whoGetsThis === 'all'}
                                            onCheckedChange={(checked) => {
                                                if (checked) {
                                                    setWhoGetsThis('all');
                                                    setSelectedEmployeeIds([]);
                                                    setData('employee_id', null);
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
                                    <InputError message={errors.employee_id} />
                                </div>
                            )}

                            {whoGetsThis === 'select' && !employee && (
                                <div className="space-y-2">
                                    <Label>Select Employees</Label>
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
                                                        <span>{emp.name}</span>
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
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="name">Bonus Type</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Performance Bonus, Year-End Bonus, Allowance"
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
                                    placeholder={data.type === 'percentage' ? 'e.g., 5' : 'e.g., 5000'}
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="period">For Period</Label>
                                <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                                    <SelectTrigger id="period">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={currentMonth}>
                                            {new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })} (Current month)
                                        </SelectItem>
                                        <SelectItem value={nextMonth}>
                                            {new Date(Date.now() + 30*24*60*60*1000).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })} (Next month)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.period_start} />
                                <p className="text-xs text-muted-foreground">
                                    Period: {data.period_start && data.period_end 
                                        ? `${new Date(data.period_start).toLocaleDateString()} - ${new Date(data.period_end).toLocaleDateString()}`
                                        : 'Select a month'
                                    }
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Add any notes about this bonus"
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    <strong>Note:</strong> This bonus will be included in the payroll for the selected period only.
                                </p>
                            </div>

                            <div className="flex gap-2">
                                <Link href="/payroll/bonuses">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Bonus'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
