import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Bonuses', href: '/payroll/bonuses' },
];

export default function PaymentsIndex({ payments, businesses, selectedBusinessId, filters }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses?.[0]?.id || '');
    const [filter, setFilter] = useState(filters?.filter || 'all');
    // Convert empty string to 'all' for the Select component
    const [period, setPeriod] = useState(filters?.period ? filters.period : 'all');

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        const periodValue = period === 'all' ? '' : period;
        router.get('/payroll/bonuses', { business_id: value, filter, period: periodValue }, { preserveState: true });
    };

    const handleFilterChange = (value: string) => {
        setFilter(value);
        const periodValue = period === 'all' ? '' : period;
        router.get('/payroll/bonuses', { business_id: businessId, filter: value, period: periodValue }, { preserveState: true });
    };

    const handlePeriodChange = (value: string) => {
        setPeriod(value);
        // Convert 'all' back to empty string for the API
        const periodValue = value === 'all' ? '' : value;
        router.get('/payroll/bonuses', { business_id: businessId, filter, period: periodValue }, { preserveState: true });
    };

    // Group payments by type
    const paymentsData = payments?.data || [];
    const companyPayments = paymentsData.filter((p: any) => !p.employee_id) || [];
    const employeePayments = paymentsData.filter((p: any) => p.employee_id) || [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bonuses & One-Off Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Bonuses & One-Off Payments</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            One-time bonuses, allowances, and special payments for employees
                        </p>
                    </div>
                    <Link href={`/payroll/bonuses/create?business_id=${businessId}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Bonus
                        </Button>
                    </Link>
                </div>

                <div className="flex gap-4">
                    {businesses && Array.isArray(businesses) && businesses.length > 0 && (
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

                    <div className="max-w-xs">
                        <label className="text-sm font-medium mb-2 block">Filter</label>
                        <Select value={filter} onValueChange={handleFilterChange}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Bonuses</SelectItem>
                                <SelectItem value="company">Company Bonuses</SelectItem>
                                <SelectItem value="employee">Employee Bonuses</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="max-w-xs">
                        <label className="text-sm font-medium mb-2 block">Period</label>
                        <Select value={period || 'all'} onValueChange={handlePeriodChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="All periods" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All periods</SelectItem>
                                <SelectItem value={new Date().toISOString().slice(0, 7)}>
                                    {new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                                </SelectItem>
                                <SelectItem value={new Date(Date.now() + 30*24*60*60*1000).toISOString().slice(0, 7)}>
                                    {new Date(Date.now() + 30*24*60*60*1000).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {paymentsData && paymentsData.length > 0 ? (
                    <div className="space-y-6">
                        {companyPayments.length > 0 && (
                            <div>
                                <h2 className="text-lg font-semibold mb-4">Company Bonuses</h2>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {companyPayments.map((payment: any) => (
                                        <Card key={payment.id}>
                                            <CardHeader>
                                                <CardTitle>{payment.name}</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-2">
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Amount: </span>
                                                        <span className="font-medium">
                                                            {payment.type === 'percentage' 
                                                                ? `${payment.amount}%`
                                                                : `ZAR ${parseFloat(payment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                            }
                                                        </span>
                                                    </div>
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">For: </span>
                                                        <span className="font-medium">All employees</span>
                                                    </div>
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Period: </span>
                                                        <span className="font-medium">
                                                            {new Date(payment.period_start).toLocaleDateString()} - {new Date(payment.period_end).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Type: </span>
                                                        <span className={`font-medium capitalize ${
                                                            payment.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                        }`}>
                                                            {payment.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                        </span>
                                                    </div>
                                                    <div className="flex gap-2 mt-4">
                                                        <Link href={`/payroll/bonuses/${payment.id}/edit`} className="flex-1">
                                                            <Button variant="outline" size="sm" className="w-full">
                                                                Edit
                                                            </Button>
                                                        </Link>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </div>
                        )}

                        {employeePayments.length > 0 && (
                            <div>
                                <h2 className="text-lg font-semibold mb-4">Employee Bonuses</h2>
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    {employeePayments.map((payment: any) => (
                                        <Card key={payment.id}>
                                            <CardHeader>
                                                <CardTitle>{payment.employee?.name || 'Employee'} - {payment.name}</CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="space-y-2">
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Amount: </span>
                                                        <span className="font-medium">
                                                            {payment.type === 'percentage' 
                                                                ? `${payment.amount}%`
                                                                : `ZAR ${parseFloat(payment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                            }
                                                        </span>
                                                    </div>
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Period: </span>
                                                        <span className="font-medium">
                                                            {new Date(payment.period_start).toLocaleDateString()} - {new Date(payment.period_end).toLocaleDateString()}
                                                        </span>
                                                    </div>
                                                    <div className="text-sm">
                                                        <span className="text-muted-foreground">Type: </span>
                                                        <span className={`font-medium capitalize ${
                                                            payment.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                        }`}>
                                                            {payment.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                        </span>
                                                    </div>
                                                    <div className="flex gap-2 mt-4">
                                                        <Link href={`/payroll/bonuses/${payment.id}/edit`} className="flex-1">
                                                            <Button variant="outline" size="sm" className="w-full">
                                                                Edit
                                                            </Button>
                                                        </Link>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No bonuses found.</p>
                            <p className="text-sm text-muted-foreground mt-2">
                                Bonuses are one-time payments for specific periods.
                            </p>
                            <Link href={`/payroll/bonuses/create?business_id=${businessId}`} className="mt-4 inline-block">
                                <Button>Add your first bonus</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
