import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, XCircle } from 'lucide-react';

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
                                    <div className="flex items-center justify-between">
                                        <CardTitle>{business.name}</CardTitle>
                                        {getStatusBadge(business.status || 'active')}
                                    </div>
                                    {business.status_reason && (
                                        <p className="text-xs text-muted-foreground mt-2">
                                            Reason: {business.status_reason}
                                        </p>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {business.status !== 'active' && (
                                        <p className="text-sm text-muted-foreground text-center py-2">
                                            Business is {business.status}. Cannot switch.
                                        </p>
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
