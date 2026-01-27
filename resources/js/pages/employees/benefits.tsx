import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import InputError from '@/components/input-error';
import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Edit, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: '/employees' },
    { title: 'Benefits', href: '#' },
];

export default function EmployeeBenefits({ employee, companyBenefits, benefitsWithOverrides, employeeOnlyBenefits }: any) {
    const [overrideDialogOpen, setOverrideDialogOpen] = useState(false);
    const [selectedBenefit, setSelectedBenefit] = useState<any>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        benefit_id: '',
        amount: '',
        period_start: '',
        period_end: '',
        description: '',
    });

    const handleOverrideClick = (benefit: any) => {
        setSelectedBenefit(benefit);
        const benefitToUse = benefit.company_benefit;
        setData({
            benefit_id: benefitToUse.id,
            amount: benefit.override ? benefit.override.amount : benefitToUse.amount,
            period_start: '',
            period_end: '',
            description: '',
        });
        setOverrideDialogOpen(true);
    };

    const handleSubmitOverride = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/employees/${employee.id}/benefits/override`, {
            onSuccess: () => {
                setOverrideDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${employee.name}'s Benefits`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{employee.name}'s Benefits</h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            Company benefits and employee-specific overrides
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Dialog open={overrideDialogOpen} onOpenChange={setOverrideDialogOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Override Benefit
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Override Benefit</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={handleSubmitOverride} className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="benefit_id">Benefit to Override</Label>
                                        <Select
                                            value={String(data.benefit_id)}
                                            onValueChange={(value) => {
                                                setData('benefit_id', value);
                                                const benefit = companyBenefits.find((b: any) => b.id === parseInt(value));
                                                if (benefit) {
                                                    setData('amount', benefit.amount);
                                                    // Find if there's already an override
                                                    const override = benefitsWithOverrides.find((item: any) => item.company_benefit.id === parseInt(value));
                                                    if (override?.override) {
                                                        setSelectedBenefit(override);
                                                    } else {
                                                        setSelectedBenefit({ company_benefit: benefit });
                                                    }
                                                }
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a benefit" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {companyBenefits.map((benefit: any) => (
                                                    <SelectItem key={benefit.id} value={String(benefit.id)}>
                                                        {benefit.name} ({benefit.type === 'percentage' ? `${benefit.amount}%` : `ZAR ${benefit.amount}`})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.benefit_id} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="amount">
                                            New Amount
                                            {(() => {
                                                const benefit = companyBenefits.find((b: any) => b.id === parseInt(data.benefit_id));
                                                return benefit?.type === 'percentage' ? ' (0-100)' : '';
                                            })()}
                                        </Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max={(() => {
                                                const benefit = companyBenefits.find((b: any) => b.id === parseInt(data.benefit_id));
                                                return benefit?.type === 'percentage' ? '100' : undefined;
                                            })()}
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Applies</Label>
                                        <Select
                                            value={data.period_start ? 'specific' : 'forever'}
                                            onValueChange={(value) => {
                                                if (value === 'forever') {
                                                    setData('period_start', '');
                                                    setData('period_end', '');
                                                }
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="forever">Forever</SelectItem>
                                                <SelectItem value="specific">Specific Period</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {data.period_start && (
                                            <div className="grid grid-cols-2 gap-2 mt-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="period_start">From</Label>
                                                    <Input
                                                        id="period_start"
                                                        type="date"
                                                        value={data.period_start}
                                                        onChange={(e) => setData('period_start', e.target.value)}
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label htmlFor="period_end">To</Label>
                                                    <Input
                                                        id="period_end"
                                                        type="date"
                                                        value={data.period_end}
                                                        onChange={(e) => setData('period_end', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex gap-2">
                                        <Button type="button" variant="outline" onClick={() => setOverrideDialogOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Creating...' : 'Create Override'}
                                        </Button>
                                    </div>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Link href={`/payroll/bonuses/create?employee_id=${employee.id}`}>
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Bonus
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="space-y-6">
                    <div>
                        <h2 className="text-lg font-semibold mb-4">Company Benefits (applied to everyone)</h2>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {benefitsWithOverrides.map((item: any) => (
                                <Card key={item.company_benefit.id}>
                                    <CardHeader>
                                        <CardTitle className="text-lg">{item.company_benefit.name}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <div className="text-sm">
                                                <span className="text-muted-foreground">Company Rate: </span>
                                                <span className="font-medium">
                                                    {item.company_benefit.type === 'percentage' 
                                                        ? `${item.company_benefit.amount}%`
                                                        : `ZAR ${parseFloat(item.company_benefit.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                    }
                                                </span>
                                            </div>
                                            {item.has_override && (
                                                <div className="text-sm">
                                                    <span className="text-muted-foreground">Employee Rate: </span>
                                                    <span className="font-medium text-blue-600">
                                                        {item.company_benefit.type === 'percentage' 
                                                            ? `${item.effective_amount}%`
                                                            : `ZAR ${parseFloat(item.effective_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                        }
                                                        <span className="text-xs text-muted-foreground ml-1">(override)</span>
                                                    </span>
                                                </div>
                                            )}
                                            <div className="text-sm">
                                                <span className="text-muted-foreground">Type: </span>
                                                <span className={`font-medium capitalize ${
                                                    item.company_benefit.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                }`}>
                                                    {item.company_benefit.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                </span>
                                            </div>
                                            <div className="flex gap-2 mt-4">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="flex-1"
                                                    onClick={() => handleOverrideClick(item)}
                                                >
                                                    {item.has_override ? <Edit className="mr-2 h-3 w-3" /> : <Plus className="mr-2 h-3 w-3" />}
                                                    {item.has_override ? 'Edit Override' : 'Override'}
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>

                    {employeeOnlyBenefits && employeeOnlyBenefits.length > 0 && (
                        <div>
                            <h2 className="text-lg font-semibold mb-4">Employee-Specific Benefits</h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {employeeOnlyBenefits.map((benefit: any) => (
                                    <Card key={benefit.id}>
                                        <CardHeader>
                                            <CardTitle className="text-lg">{benefit.name}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-2">
                                                <div className="text-sm">
                                                    <span className="text-muted-foreground">Amount: </span>
                                                    <span className="font-medium">
                                                        {benefit.type === 'percentage' 
                                                            ? `${benefit.amount}%`
                                                            : `ZAR ${parseFloat(benefit.amount).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                                                        }
                                                    </span>
                                                </div>
                                                <div className="text-sm">
                                                    <span className="text-muted-foreground">Type: </span>
                                                    <span className={`font-medium capitalize ${
                                                        benefit.adjustment_type === 'deduction' ? 'text-red-600' : 'text-green-600'
                                                    }`}>
                                                        {benefit.adjustment_type === 'deduction' ? 'Deduction' : 'Addition'}
                                                    </span>
                                                </div>
                                                <Link
                                                    href={`/employees/${employee.id}/benefits/${benefit.id}/remove`}
                                                    method="delete"
                                                    as="button"
                                                    className="mt-4 w-full"
                                                >
                                                    <Button variant="outline" size="sm" className="w-full">
                                                        <X className="mr-2 h-3 w-3" />
                                                        Remove Override
                                                    </Button>
                                                </Link>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
