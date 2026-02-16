import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payroll', href: '/payroll' },
    { title: 'Benefits & Deductions', href: '/benefits' },
    { title: 'Add Deduction or Benefit', href: '#' },
];

export default function BenefitsCreate({ businesses, selectedBusinessId }: any) {
    const urlParams = new URLSearchParams(window.location.search);
    const businessIdFromUrl = urlParams.get('business_id');

    const { data, setData, post, processing, errors } = useForm({
        business_id: selectedBusinessId || businessIdFromUrl || businesses[0]?.id || '',
        name: '',
        type: 'fixed',
        amount: '',
        adjustment_type: 'deduction',
        is_active: true,
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/benefits');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Deduction or Benefit" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Add Deduction or Benefit</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Add a recurring deduction or benefit that applies to all employees every month
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
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

                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Medical Aid, Pension Fund, UIF"
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
                                        <SelectItem value="fixed">Fixed amount per month</SelectItem>
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
                                    placeholder={data.type === 'percentage' ? 'e.g., 5' : 'e.g., 500'}
                                    required
                                />
                                <InputError message={errors.amount} />
                                {data.type === 'percentage' && (
                                    <p className="text-xs text-muted-foreground">
                                        This will be calculated as a percentage of each employee's gross salary
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="adjustment_type">This is a</Label>
                                <Select
                                    value={data.adjustment_type}
                                    onValueChange={(value) => setData('adjustment_type', value as 'deduction' | 'addition')}
                                >
                                    <SelectTrigger id="adjustment_type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="deduction">Deduction (reduces net salary)</SelectItem>
                                        <SelectItem value="addition">Addition (increases net salary)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.adjustment_type} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Add any notes about this benefit"
                                    rows={3}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <p className="text-sm text-blue-900 dark:text-blue-100">
                                    <strong>Note:</strong> This will automatically apply to all employees every month until you deactivate it. Choose "Deduction" to reduce net salary or "Addition" to increase it.
                                </p>
                            </div>

                            <div className="flex gap-2">
                                <Link href="/benefits">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
