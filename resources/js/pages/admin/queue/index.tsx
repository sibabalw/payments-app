import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    Activity,
    RefreshCw,
    Trash2,
    Database,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Queue Management', href: '/admin/queue' },
];

interface QueueProps {
    queueConfig: {
        default: string;
        driver: string;
        connection: string;
    };
    queueStats: {
        failed_jobs_count: number;
        pending_jobs_count: number;
    };
}

export default function Queue({ queueConfig, queueStats }: QueueProps) {
    const [restartDialogOpen, setRestartDialogOpen] = useState(false);
    const [clearFailedDialogOpen, setClearFailedDialogOpen] = useState(false);

    const handleRestart = () => {
        router.post('/admin/queue/restart', {}, {
            onSuccess: () => {
                setRestartDialogOpen(false);
            },
        });
    };

    const handleClearFailed = () => {
        router.post('/admin/queue/clear-failed', {}, {
            onSuccess: () => {
                setClearFailedDialogOpen(false);
                router.reload();
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Queue Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Queue Management</h1>
                        <p className="text-sm text-muted-foreground">Monitor and manage queue workers</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Queue Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Activity className="h-5 w-5" />
                                Queue Configuration
                            </CardTitle>
                            <CardDescription>Current queue settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Connection</span>
                                <span className="font-mono">{queueConfig.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{queueConfig.driver}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Connection</span>
                                <span className="font-mono">{queueConfig.connection}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Queue Statistics */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Queue Statistics
                            </CardTitle>
                            <CardDescription>Current queue status</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-muted-foreground">Pending Jobs</span>
                                <Badge variant="secondary">{queueStats.pending_jobs_count}</Badge>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-muted-foreground">Failed Jobs</span>
                                <Badge className={queueStats.failed_jobs_count > 0 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : ''}>
                                    {queueStats.failed_jobs_count}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Queue Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Queue Actions</CardTitle>
                        <CardDescription>Manage queue workers and jobs</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between p-4 border rounded-lg">
                            <div>
                                <p className="font-medium mb-1">Restart Queue Workers</p>
                                <p className="text-sm text-muted-foreground">
                                    Restart all queue workers. They will finish processing current jobs before restarting.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setRestartDialogOpen(true)}
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Restart Workers
                            </Button>
                        </div>

                        <div className="flex items-center justify-between p-4 border rounded-lg">
                            <div>
                                <p className="font-medium mb-1">Clear Failed Jobs</p>
                                <p className="text-sm text-muted-foreground">
                                    Remove all failed jobs from the queue. This action cannot be undone.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                onClick={() => setClearFailedDialogOpen(true)}
                                disabled={queueStats.failed_jobs_count === 0}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Clear Failed Jobs
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationDialog
                    open={restartDialogOpen}
                    onOpenChange={setRestartDialogOpen}
                    onConfirm={handleRestart}
                    title="Restart Queue Workers"
                    description="Queue workers will finish processing current jobs and then restart. This may cause a brief delay in job processing."
                    confirmText="Restart"
                    variant="default"
                />

                <ConfirmationDialog
                    open={clearFailedDialogOpen}
                    onOpenChange={setClearFailedDialogOpen}
                    onConfirm={handleClearFailed}
                    title="Clear Failed Jobs"
                    description="Are you sure you want to clear all failed jobs? This action cannot be undone."
                    confirmText="Clear"
                    variant="destructive"
                />
            </div>
        </AdminLayout>
    );
}
