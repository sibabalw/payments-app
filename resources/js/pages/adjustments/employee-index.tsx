import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus, ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: '/employees' },
    { title: 'Adjustments', href: '#' },
];

export default function AdjustmentsEmployeeIndex({ employee, adjustments }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Adjustments - ${employee.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={`/employees/${employee.id}/edit`}>
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Employee
                            </Button>
                        </Link>
                        <h1 className="text-2xl font-bold">Adjustments for {employee.name}</h1>
                    </div>
                    <Link href={`/adjustments/create?business_id=${employee.business_id}&employee_id=${employee.id}`}>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Adjustment
                        </Button>
                    </Link>
                </div>

                {adjustments && adjustments.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {adjustments.map((adjustment: any) => (
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
                                            <span className="text-muted-foreground">Scope: </span>
                                            <span className="font-medium">
                                                {adjustment.employee_id === null ? 'Company-wide' : 'Employee-specific'}
                                            </span>
                                        </div>
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
                            <p className="text-muted-foreground">No adjustments found for this employee.</p>
                            <Link href={`/adjustments/create?business_id=${employee.business_id}&employee_id=${employee.id}`} className="mt-4 inline-block">
                                <Button>Add your first adjustment</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
