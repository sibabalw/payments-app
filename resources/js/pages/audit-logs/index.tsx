import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Audit Logs', href: '/audit-logs' },
];

export default function AuditLogsIndex({ logs }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Audit Logs</h1>

                <div className="space-y-4">
                    {logs?.data?.map((log: any) => (
                        <Card key={log.id}>
                            <CardHeader>
                                <CardTitle>{log.action}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-sm text-muted-foreground">
                                    <p>User: {log.user?.name || 'System'}</p>
                                    <p>Business: {log.business?.name || 'N/A'}</p>
                                    <p>Time: {new Date(log.created_at).toLocaleString()}</p>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
