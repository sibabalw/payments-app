import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Calendar } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Leave Management', href: '/leave' },
];

export default function LeaveIndex({ leaveEntries, employees, businesses, selectedBusinessId, filters }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses[0]?.id || '');
    const [localFilters, setLocalFilters] = useState(filters || {});

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/leave', { business_id: value, ...localFilters }, { preserveState: true });
    };

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = { ...localFilters, [key]: value === 'all' ? null : value };
        setLocalFilters(newFilters);
        router.get('/leave', { business_id: businessId, ...newFilters }, { preserveState: true });
    };

    const getLeaveTypeColor = (type: string) => {
        const colors: Record<string, string> = {
            paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            unpaid: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
            sick: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            public_holiday: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            other: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        };
        return colors[type] || colors.other;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-ZA');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Leave Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Leave Management</h1>
                    <Link href={`/leave/create?business_id=${businessId}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Leave
                        </Button>
                    </Link>
                </div>

                {businesses && businesses.length > 0 && (
                    <div className="max-w-xs">
                        <label className="text-sm font-medium mb-2 block">Business</label>
                        <Select value={String(businessId)} onValueChange={handleBusinessChange}>
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
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <Label htmlFor="filter_employee">Employee</Label>
                                <Select
                                    value={localFilters.employee_id || 'all'}
                                    onValueChange={(value) => handleFilterChange('employee_id', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="All employees" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All employees</SelectItem>
                                        {employees.map((employee: any) => (
                                            <SelectItem key={employee.id} value={String(employee.id)}>
                                                {employee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="filter_type">Leave Type</Label>
                                <Select
                                    value={localFilters.leave_type || 'all'}
                                    onValueChange={(value) => handleFilterChange('leave_type', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        <SelectItem value="paid">Paid Leave</SelectItem>
                                        <SelectItem value="unpaid">Unpaid Leave</SelectItem>
                                        <SelectItem value="sick">Sick Leave</SelectItem>
                                        <SelectItem value="public_holiday">Public Holiday</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label htmlFor="filter_start_date">Start Date</Label>
                                <Input
                                    id="filter_start_date"
                                    type="date"
                                    value={localFilters.start_date || ''}
                                    onChange={(e) => handleFilterChange('start_date', e.target.value)}
                                />
                            </div>

                            <div>
                                <Label htmlFor="filter_end_date">End Date</Label>
                                <Input
                                    id="filter_end_date"
                                    type="date"
                                    value={localFilters.end_date || ''}
                                    onChange={(e) => handleFilterChange('end_date', e.target.value)}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {leaveEntries?.data && leaveEntries.data.length > 0 ? (
                    <div className="space-y-4">
                        {leaveEntries.data.map((entry: any) => (
                            <Card key={entry.id}>
                                <CardHeader>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <CardTitle className="text-lg">{entry.employee?.name}</CardTitle>
                                            <p className="text-sm text-muted-foreground mt-1">
                                                {formatDate(entry.start_date)} - {formatDate(entry.end_date)}
                                            </p>
                                        </div>
                                        <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${getLeaveTypeColor(entry.leave_type)}`}>
                                            {entry.leave_type.replace('_', ' ').toUpperCase()}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {entry.leave_type === 'paid' && entry.hours > 0 && (
                                            <div className="text-sm">
                                                <span className="text-muted-foreground">Paid Hours: </span>
                                                <span className="font-medium">{parseFloat(entry.hours).toFixed(2)} hours</span>
                                            </div>
                                        )}
                                        {entry.notes && (
                                            <p className="text-sm text-muted-foreground">{entry.notes}</p>
                                        )}
                                        <div className="flex gap-2 pt-2">
                                            <Link href={`/leave/${entry.id}/edit`}>
                                                <Button variant="outline" size="sm">Edit</Button>
                                            </Link>
                                            <Link
                                                href={`/leave/${entry.id}`}
                                                method="delete"
                                                as="button"
                                                className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3 text-destructive"
                                            >
                                                Delete
                                            </Link>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No leave entries found.</p>
                            <Link href={`/leave/create?business_id=${businessId}`} className="mt-4 inline-block">
                                <Button>Add your first leave entry</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
