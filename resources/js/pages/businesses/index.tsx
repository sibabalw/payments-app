import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, XCircle, Users, CreditCard, Receipt, UserCheck, Wallet, Mail, Phone, Building2, Pencil } from 'lucide-react';

// Helper function to get business initials
const getBusinessInitials = (name: string): string => {
    if (!name) return '?';
    
    const words = name.trim().split(/\s+/);
    if (words.length === 1) {
        // Single word: take first 2 letters
        return name.substring(0, 2).toUpperCase();
    }
    // Multiple words: take first letter of first two words
    return (words[0][0] + words[1][0]).toUpperCase();
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Businesses', href: '/businesses' },
];

export default function BusinessesIndex({ businesses }: any) {
    const getStatusBadge = (status: string) => {
        const statusConfig = {
            active: { label: 'Active', variant: 'default' as const, icon: CheckCircle2, className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
            suspended: { label: 'Suspended', variant: 'outline' as const, icon: AlertCircle, className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
            banned: { label: 'Banned', variant: 'destructive' as const, icon: XCircle, className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.active;
        const Icon = config.icon;

        return (
            <Badge className={config.className}>
                <Icon className="mr-1 h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(amount);
    };

    const formatBusinessType = (type: string | null) => {
        if (!type) return 'N/A';
        return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Businesses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Businesses</h1>
                    <Link href="/businesses/create">
                        <Button>Create New Business</Button>
                    </Link>
                </div>

                {businesses && businesses.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {businesses.map((business: any) => (
                            <Card key={business.id} className={business.status !== 'active' ? 'opacity-75' : ''}>
                                <CardHeader>
                                    <div className="flex items-center gap-3 mb-3">
                                        {business.logo && business.logo.trim() !== '' ? (
                                            <div className="flex aspect-square size-12 items-center justify-center rounded-md overflow-hidden flex-shrink-0 border border-border bg-muted">
                                                <img 
                                                    src={business.logo} 
                                                    alt={business.name}
                                                    className="w-full h-full object-cover"
                                                    onError={(e) => {
                                                        // Fallback to initials if image fails to load
                                                        const target = e.target as HTMLImageElement;
                                                        const parent = target.parentElement;
                                                        if (parent) {
                                                            target.style.display = 'none';
                                                            const initialsSpan = document.createElement('span');
                                                            initialsSpan.className = 'text-sm font-semibold text-foreground';
                                                            initialsSpan.textContent = getBusinessInitials(business.name);
                                                            parent.appendChild(initialsSpan);
                                                        }
                                                    }}
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex aspect-square size-12 items-center justify-center rounded-md bg-primary text-primary-foreground flex-shrink-0">
                                                <span className="text-sm font-semibold">
                                                    {getBusinessInitials(business.name)}
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <CardTitle className="flex-1 min-w-0 truncate" title={business.name}>
                                                    {business.name}
                                                </CardTitle>
                                        {getStatusBadge(business.status || 'active')}
                                            </div>
                                        </div>
                                    </div>
                                    {business.status_reason && (
                                        <p className="text-xs text-muted-foreground mt-2">
                                            Reason: {business.status_reason}
                                        </p>
                                    )}
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Escrow Balance */}
                                    <div className="flex items-center justify-between p-2 rounded-lg bg-primary/5">
                                        <div className="flex items-center gap-2">
                                            <Wallet className="h-4 w-4 text-primary" />
                                            <span className="text-sm font-medium">Escrow Balance</span>
                                        </div>
                                        <span className="text-sm font-semibold text-primary">
                                            {formatCurrency(business.escrow_balance || 0)}
                                        </span>
                                    </div>

                                    {/* Statistics Grid */}
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="flex items-center gap-2 text-sm">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Employees:</span>
                                            <span className="font-semibold">{business.employees_count || 0}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Payments:</span>
                                            <span className="font-semibold">{business.payment_schedules_count || 0}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <Receipt className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Payroll:</span>
                                            <span className="font-semibold">{business.payroll_schedules_count || 0}</span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm">
                                            <UserCheck className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Recipients:</span>
                                            <span className="font-semibold">{business.recipients_count || 0}</span>
                                        </div>
                                    </div>

                                    {/* Business Type */}
                                    {business.business_type && (
                                        <div className="flex items-center gap-2 text-sm pt-2 border-t">
                                            <Building2 className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Type:</span>
                                            <span className="font-medium">{formatBusinessType(business.business_type)}</span>
                                        </div>
                                    )}

                                    {/* Contact Information */}
                                    <div className="space-y-1.5 pt-2 border-t">
                                        {business.email && (
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Mail className="h-3.5 w-3.5 flex-shrink-0" />
                                                <span className="truncate" title={business.email}>{business.email}</span>
                                            </div>
                                        )}
                                        {business.phone && (
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Phone className="h-3.5 w-3.5 flex-shrink-0" />
                                                <span className="truncate">{business.phone}</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Created Date */}
                                    {business.created_at && (
                                        <div className="text-xs text-muted-foreground pt-2 border-t">
                                            Created: {new Date(business.created_at).toLocaleDateString('en-ZA', {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric'
                                            })}
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="pt-2 border-t">
                                        <Link href={`/businesses/${business.id}/edit`}>
                                            <Button variant="outline" size="sm" className="w-full">
                                                <Pencil className="mr-2 h-4 w-4" />
                                                Edit Business
                                            </Button>
                                        </Link>
                                    </div>

                                    {business.status !== 'active' && (
                                        <div className="pt-2 border-t">
                                        <p className="text-sm text-muted-foreground text-center py-2">
                                            Business is {business.status}. Cannot switch.
                                        </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No businesses found.</p>
                            <Link href="/businesses/create" className="mt-4 inline-block">
                                <Button>Create your first business</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
