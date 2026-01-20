import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Custom Deductions', href: '/deductions' },
];

export default function DeductionsIndex({ deductions, businesses, selectedBusinessId }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses[0]?.id || '');

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/deductions', { business_id: value }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Custom Deductions" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Custom Deductions</h1>
                    <Link href={`/deductions/create?business_id=${businessId}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Deduction
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

                {deductions?.data && deductions.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {deductions.data.map((deduction: any) => (
                            <Card key={deduction.id}>
                                <CardHeader>
                                    <CardTitle>{deduction.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Type: </span>
                                            <span className="font-medium capitalize">{deduction.type}</span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Amount: </span>
                                            <span className="font-medium">
                                                {deduction.type === 'percentage' 
                                                    ? `${deduction.amount}%`
                                                    : `ZAR ${parseFloat(deduction.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                }
                                            </span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Status: </span>
                                            <span className={`font-medium ${deduction.is_active ? 'text-green-600' : 'text-gray-500'}`}>
                                                {deduction.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        {deduction.description && (
                                            <p className="text-xs text-muted-foreground mt-2">{deduction.description}</p>
                                        )}
                                        <div className="flex gap-2 mt-4">
                                            <Link href={`/deductions/${deduction.id}/edit`} className="flex-1">
                                                <Button variant="outline" size="sm" className="w-full">
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Link
                                                href={`/deductions/${deduction.id}`}
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
                            <p className="text-muted-foreground">No custom deductions found.</p>
                            <Link href={`/deductions/create?business_id=${businessId}`} className="mt-4 inline-block">
                                <Button>Add your first deduction</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
