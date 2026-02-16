import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    HardDrive,
    Database,
    FileText,
    Trash2,
    RefreshCw,
    Folder,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Storage Management', href: '/admin/storage' },
];

interface StorageProps {
    cache: {
        driver: string;
        config: Record<string, unknown>;
        size: string;
        size_bytes: number;
        file_count: number;
    };
    sessions: {
        driver: string;
        lifetime: number;
        size: string;
        size_bytes: number;
        count: number;
    };
    logs: Array<{
        name: string;
        size: number;
        size_formatted: string;
        modified: string;
    }>;
    storage_disks: Array<{
        name: string;
        driver: string;
        size: string;
        size_bytes: number;
        file_count: number;
    }>;
    total_storage: string;
    total_storage_bytes: number;
}

export default function Storage({
    cache,
    sessions,
    logs,
    storage_disks,
    total_storage,
}: StorageProps) {
    const [clearCacheDialogOpen, setClearCacheDialogOpen] = useState(false);
    const [clearSessionsDialogOpen, setClearSessionsDialogOpen] = useState(false);

    const handleClearCache = () => {
        router.post('/admin/storage/clear-cache', {}, {
            onSuccess: () => {
                setClearCacheDialogOpen(false);
                router.reload();
            },
        });
    };

    const handleClearSessions = () => {
        router.post('/admin/storage/clear-sessions', {}, {
            onSuccess: () => {
                setClearSessionsDialogOpen(false);
                router.reload();
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Storage Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Storage Management</h1>
                        <p className="text-sm text-muted-foreground">Monitor and manage storage usage</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Total Storage Overview */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <HardDrive className="h-5 w-5" />
                            Total Storage Usage
                        </CardTitle>
                        <CardDescription>Combined storage across all systems</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">{total_storage}</div>
                    </CardContent>
                </Card>

                {/* Cache and Sessions */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Cache Storage
                            </CardTitle>
                            <CardDescription>Application cache information</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Driver</span>
                                    <span className="font-mono">{cache.driver}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Size</span>
                                    <span className="font-medium">{cache.size}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">File Count</span>
                                    <Badge variant="secondary">{cache.file_count}</Badge>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setClearCacheDialogOpen(true)}
                                className="w-full"
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Clear Cache
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Session Storage
                            </CardTitle>
                            <CardDescription>Active session information</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Driver</span>
                                    <span className="font-mono">{sessions.driver}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Lifetime</span>
                                    <span className="font-mono">{sessions.lifetime} minutes</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Size</span>
                                    <span className="font-medium">{sessions.size}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Active Sessions</span>
                                    <Badge variant="secondary">{sessions.count}</Badge>
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setClearSessionsDialogOpen(true)}
                                className="w-full"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Clear Sessions
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Storage Disks */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Folder className="h-5 w-5" />
                            Storage Disks
                        </CardTitle>
                        <CardDescription>File storage disk information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {storage_disks.map((disk) => (
                                <div key={disk.name} className="border rounded-lg p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium">{disk.name}</span>
                                        <Badge variant="outline">{disk.driver}</Badge>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Size</span>
                                        <span className="font-medium">{disk.size}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-muted-foreground">Files</span>
                                        <Badge variant="secondary">{disk.file_count}</Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Log Files */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            Log Files
                        </CardTitle>
                        <CardDescription>Application log file sizes</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {logs.length > 0 ? (
                            <div className="space-y-3">
                                {logs.map((log) => (
                                    <div
                                        key={log.name}
                                        className="flex items-center justify-between border-b pb-3 last:border-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">{log.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                Modified: {new Date(log.modified).toLocaleString('en-ZA')}
                                            </p>
                                        </div>
                                        <Badge variant="secondary">{log.size_formatted}</Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-4">No log files found</p>
                        )}
                    </CardContent>
                </Card>

                <ConfirmationDialog
                    open={clearCacheDialogOpen}
                    onOpenChange={setClearCacheDialogOpen}
                    onConfirm={handleClearCache}
                    title="Clear Cache"
                    description="Are you sure you want to clear all application cache? This will clear config, route, view, and application cache."
                    confirmText="Clear Cache"
                    variant="default"
                />

                <ConfirmationDialog
                    open={clearSessionsDialogOpen}
                    onOpenChange={setClearSessionsDialogOpen}
                    onConfirm={handleClearSessions}
                    title="Clear Sessions"
                    description="Are you sure you want to clear all active sessions? All users will be logged out."
                    confirmText="Clear Sessions"
                    variant="destructive"
                />
            </div>
        </AdminLayout>
    );
}
