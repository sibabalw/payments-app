import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Edit, Settings } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Benefits & Deductions', href: '/benefits' },
];

export default function BenefitsIndex({ benefits, businesses, selectedBusinessId }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses?.[0]?.id || '');

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/benefits', { business_id: value }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Company Benefits" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Benefits & Deductions</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Recurring benefits and deductions that apply to all employees every month
                        </p>
                    </div>
                    <Link href={`/benefits/create?business_id=${businessId}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Deduction or Benefit
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

                {benefits?.data && benefits.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {benefits.data.map((benefit: any) => (
                            <Card key={benefit.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <CardTitle className="text-lg">{benefit.name}</CardTitle>
                                        <span className={`text-xs px-2 py-1 rounded ${
                                            benefit.is_active 
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                        }`}>
                                            {benefit.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Amount: </span>
                                            <span className="font-medium">
                                                {benefit.type === 'percentage' 
                                                    ? `${benefit.amount}% of salary`
                                                    : `ZAR ${parseFloat(benefit.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} per month`
                                                }
                                            </span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Type: </span>
                                            <span className={`font-medium capitalize ${
                                                benefit.adjustment_type === 'deduction' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'
                                            }`}>
                                                {benefit.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                            </span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Applies to: </span>
                                            <span className="font-medium">All employees</span>
                                        </div>
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Frequency: </span>
                                            <span className="font-medium">Every month</span>
                                        </div>
                                        {benefit.description && (
                                            <p className="text-xs text-muted-foreground mt-2 pt-2 border-t">
                                                {benefit.description}
                                            </p>
                                        )}
                                        <div className="flex gap-2 mt-4">
                                            <Link href={`/benefits/${benefit.id}/edit`} className="flex-1">
                                                <Button variant="outline" size="sm" className="w-full">
                                                    <Edit className="mr-2 h-3 w-3" />
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Link href={`/benefits/${benefit.id}/temporarily-change`} className="flex-1">
                                                <Button variant="outline" size="sm" className="w-full">
                                                    <Settings className="mr-2 h-3 w-3" />
                                                    Temporarily Change
                                                </Button>
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
                        <p className="text-muted-foreground">No benefits or deductions set up yet.</p>
                        <p className="text-sm text-muted-foreground mt-2">
                            Benefits and deductions apply to all employees automatically every month.
                        </p>
                            <Link href={`/benefits/create?business_id=${businessId}`} className="mt-4 inline-block">
                                <Button>Add your first deduction or benefit</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
