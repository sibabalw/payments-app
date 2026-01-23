import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Adjustments', href: '/adjustments' },
];

export default function AdjustmentsIndex({ adjustments, businesses, selectedBusinessId }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses[0]?.id || '');

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/adjustments', { business_id: value }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Adjustments" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Adjustments</h1>
                    <Link href={`/adjustments/create?business_id=${businessId}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Adjustment
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

                {adjustments?.data && adjustments.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {adjustments.data.map((adjustment: any) => (
                            <Card key={adjustment.id}>
                                <CardHeader>
                                    <CardTitle>{adjustment.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Type: </span>
                                            <span className="font-medium capitalize">{adjustment.type}</span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Adjustment: </span>
                                            <span className={`font-medium capitalize ${
                                                adjustment.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                            }`}>
                                                {adjustment.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                            </span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Amount: </span>
                                            <span className="font-medium">
                                                {adjustment.type === 'percentage' 
                                                    ? `${adjustment.amount}%`
                                                    : `ZAR ${parseFloat(adjustment.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                }
                                            </span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Frequency: </span>
                                            <span className="font-medium capitalize">
                                                {adjustment.is_recurring ? 'Recurring' : 'Once-off'}
                                            </span>
                                        </div>
                                        {!adjustment.is_recurring && adjustment.payroll_period_start && (
                                            <div className="text-sm">
                                                <span className="text-muted-foreground">Period: </span>
                                                <span className="font-medium">
                                                    {new Date(adjustment.payroll_period_start).toLocaleDateString()} - {new Date(adjustment.payroll_period_end).toLocaleDateString()}
                                                </span>
                                            </div>
                                        )}
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Status: </span>
                                            <span className={`font-medium ${adjustment.is_active ? 'text-green-600' : 'text-gray-500'}`}>
                                                {adjustment.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        {adjustment.description && (
                                            <p className="text-xs text-muted-foreground mt-2">{adjustment.description}</p>
                                        )}
                                        <div className="flex gap-2 mt-4">
                                            <Link href={`/adjustments/${adjustment.id}/edit`} className="flex-1">
                                                <Button variant="outline" size="sm" className="w-full">
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Link
                                                href={`/adjustments/${adjustment.id}`}
                                                method="delete"
                                                as="button"
                                                className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 px-3"
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
                            <p className="text-muted-foreground">No adjustments found.</p>
                            <Link href={`/adjustments/create?business_id=${businessId}`} className="mt-4 inline-block">
                                <Button>Add your first adjustment</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
