import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    Settings,
    Database,
    Wallet,
    Globe,
    RefreshCw,
    AlertTriangle,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
];

interface SettingsData {
    escrow_account_number: string;
    escrow_fee_percentage: number;
    maintenance_mode: boolean;
    app_name: string;
    app_url: string;
}

interface AdminSettingsProps {
    settings: SettingsData;
}

export default function AdminSettings({ settings }: AdminSettingsProps) {
    const [clearCacheDialogOpen, setClearCacheDialogOpen] = useState(false);

    const { data, setData, post, processing } = useForm({
        escrow_fee_percentage: settings.escrow_fee_percentage.toString(),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/settings');
    };

    const handleClearCache = () => {
        router.post('/admin/settings/clear-cache', {}, {
            onSuccess: () => {
                setClearCacheDialogOpen(false);
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Admin Settings</h1>
                        <p className="text-sm text-muted-foreground">Manage application configuration</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Application Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="h-5 w-5" />
                                Application Info
                            </CardTitle>
                            <CardDescription>Current application configuration (read-only)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label className="text-xs text-muted-foreground">Application Name</Label>
                                <p className="font-medium">{settings.app_name}</p>
                            </div>
                            <div>
                                <Label className="text-xs text-muted-foreground">Application URL</Label>
                                <p className="font-medium">{settings.app_url}</p>
                            </div>
                            <div>
                                <Label className="text-xs text-muted-foreground">Maintenance Mode</Label>
                                <div className="flex items-center gap-2">
                                    {settings.maintenance_mode ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <AlertTriangle className="h-3 w-3" />
                                            Enabled
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Disabled
                                        </span>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Escrow Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Wallet className="h-5 w-5" />
                                Escrow Settings
                            </CardTitle>
                            <CardDescription>Escrow account configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label className="text-xs text-muted-foreground">Escrow Account Number</Label>
                                <p className="font-mono">{settings.escrow_account_number || 'Not configured'}</p>
                            </div>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="escrow_fee_percentage">Fee Percentage (%)</Label>
                                    <Input
                                        id="escrow_fee_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.escrow_fee_percentage}
                                        onChange={(e) => setData('escrow_fee_percentage', e.target.value)}
                                    />
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Current: {settings.escrow_fee_percentage}%
                                    </p>
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <Settings className="mr-2 h-4 w-4" />
                                    Update Settings
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Cache Management */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Cache Management
                            </CardTitle>
                            <CardDescription>Clear application cache to refresh data</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground mb-4">
                                Clearing the cache will remove all cached data and may temporarily slow down the application
                                while caches are rebuilt.
                            </p>
                            <Button
                                variant="outline"
                                onClick={() => setClearCacheDialogOpen(true)}
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Clear Cache
                            </Button>
                        </CardContent>
                    </Card>

                    {/* System Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                System Information
                            </CardTitle>
                            <CardDescription>Technical details about the application</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Laravel Version</span>
                                <span className="font-mono">12.x</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">PHP Version</span>
                                <span className="font-mono">8.4.x</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Environment</span>
                                <span className="font-mono">{import.meta.env.MODE}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Clear Cache Confirmation */}
                <ConfirmationDialog
                    open={clearCacheDialogOpen}
                    onOpenChange={setClearCacheDialogOpen}
                    onConfirm={handleClearCache}
                    title="Clear Application Cache"
                    description="Are you sure you want to clear the application cache? This may temporarily slow down the application."
                    confirmText="Clear Cache"
                    variant="info"
                />
            </div>
        </AdminLayout>
    );
}
