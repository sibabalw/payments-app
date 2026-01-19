import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Clock, LogIn, LogOut } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Time Tracking', href: '/time-tracking' },
];

export default function TimeTrackingIndex({ employees, businesses, selectedBusinessId, today }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses[0]?.id || '');
    const [filter, setFilter] = useState<'all' | 'signed_in' | 'signed_out'>('all');

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/time-tracking', { business_id: value }, { preserveState: true });
    };

    const handleSignIn = (employeeId: number) => {
        router.post(`/time-tracking/${employeeId}/sign-in`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['employees'] });
            },
        });
    };

    const handleSignOut = (employeeId: number) => {
        router.post(`/time-tracking/${employeeId}/sign-out`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['employees'] });
            },
        });
    };

    const filteredEmployees = employees.filter((emp: any) => {
        if (filter === 'signed_in') return emp.is_signed_in;
        if (filter === 'signed_out') return !emp.is_signed_in;
        return true;
    });

    const formatTime = (dateString: string | null) => {
        if (!dateString) return '';
        return new Date(dateString).toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
    };

    const formatHours = (hours: number) => {
        const h = Math.floor(hours);
        const m = Math.floor((hours - h) * 60);
        return `${h}h ${m}m`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Time Tracking" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Time Tracking</h1>
                    <div className="flex gap-2">
                        <Link href={`/time-tracking/manual?business_id=${businessId}`}>
                            <Button variant="outline">Manual Entry</Button>
                        </Link>
                    </div>
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

                <div className="flex gap-2">
                    <Button
                        variant={filter === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('all')}
                    >
                        All ({employees.length})
                    </Button>
                    <Button
                        variant={filter === 'signed_in' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('signed_in')}
                    >
                        Signed In ({employees.filter((e: any) => e.is_signed_in).length})
                    </Button>
                    <Button
                        variant={filter === 'signed_out' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('signed_out')}
                    >
                        Signed Out ({employees.filter((e: any) => !e.is_signed_in).length})
                    </Button>
                </div>

                {filteredEmployees && filteredEmployees.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {filteredEmployees.map((employee: any) => (
                            <Card key={employee.id} className={employee.is_signed_in ? 'border-green-500' : ''}>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg">{employee.name}</CardTitle>
                                    {employee.email && (
                                        <p className="text-xs text-muted-foreground">{employee.email}</p>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        {employee.is_signed_in ? (
                                            <>
                                                <LogIn className="h-4 w-4 text-green-600" />
                                                <span className="text-sm font-medium text-green-600">Signed In</span>
                                            </>
                                        ) : (
                                            <>
                                                <LogOut className="h-4 w-4 text-gray-400" />
                                                <span className="text-sm text-muted-foreground">Signed Out</span>
                                            </>
                                        )}
                                    </div>

                                    {employee.is_signed_in && employee.sign_in_time && (
                                        <div className="text-xs text-muted-foreground">
                                            <div>In: {formatTime(employee.sign_in_time)}</div>
                                            <div>Hours: {formatHours(employee.today_hours)}</div>
                                        </div>
                                    )}

                                    <div className="pt-2">
                                        {employee.is_signed_in ? (
                                            <Button
                                                onClick={() => handleSignOut(employee.id)}
                                                variant="destructive"
                                                className="w-full"
                                                size="sm"
                                            >
                                                <LogOut className="mr-2 h-4 w-4" />
                                                Sign Out
                                            </Button>
                                        ) : (
                                            <Button
                                                onClick={() => handleSignIn(employee.id)}
                                                className="w-full"
                                                size="sm"
                                            >
                                                <LogIn className="mr-2 h-4 w-4" />
                                                Sign In
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No employees found.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
