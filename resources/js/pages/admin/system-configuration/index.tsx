import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    Settings,
    Database,
    Mail,
    HardDrive,
    Globe,
    AlertTriangle,
    CheckCircle,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Configuration', href: '/admin/system-configuration' },
];

interface SystemConfigurationProps {
    config: {
        app: {
            name: string;
            url: string;
            env: string;
            debug: boolean;
            timezone: string;
            locale: string;
        };
        database: {
            default: string;
            driver: string;
        };
        cache: {
            default: string;
        };
        queue: {
            default: string;
            driver: string;
        };
        mail: {
            default: string;
            mailer: string;
        };
        session: {
            driver: string;
            lifetime: number;
        };
        maintenance_mode: boolean;
    };
}

export default function SystemConfiguration({ config }: SystemConfigurationProps) {
    const [maintenanceDialogOpen, setMaintenanceDialogOpen] = useState(false);
    const { post, processing } = useForm({});

    const handleToggleMaintenance = () => {
        post('/admin/system-configuration/maintenance', {
            enabled: !config.maintenance_mode,
        }, {
            onSuccess: () => {
                setMaintenanceDialogOpen(false);
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - System Configuration" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">System Configuration</h1>
                        <p className="text-sm text-muted-foreground">Manage system-wide settings</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Application Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Globe className="h-5 w-5" />
                                Application
                            </CardTitle>
                            <CardDescription>Application-level settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Name</span>
                                <span className="font-medium">{config.app.name}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">URL</span>
                                <span className="font-mono text-xs">{config.app.url}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Environment</span>
                                <Badge variant={config.app.env === 'production' ? 'default' : 'secondary'}>
                                    {config.app.env}
                                </Badge>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Debug Mode</span>
                                {config.app.debug ? (
                                    <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <AlertTriangle className="mr-1 h-3 w-3" />
                                        Enabled
                                    </Badge>
                                ) : (
                                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <CheckCircle className="mr-1 h-3 w-3" />
                                        Disabled
                                    </Badge>
                                )}
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Timezone</span>
                                <span className="font-mono">{config.app.timezone}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Locale</span>
                                <span className="font-mono">{config.app.locale}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Database Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Database
                            </CardTitle>
                            <CardDescription>Database connection settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Connection</span>
                                <span className="font-mono">{config.database.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{config.database.driver}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Cache Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HardDrive className="h-5 w-5" />
                                Cache
                            </CardTitle>
                            <CardDescription>Cache driver settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Driver</span>
                                <span className="font-mono">{config.cache.default}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Queue Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Queue
                            </CardTitle>
                            <CardDescription>Queue connection settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Connection</span>
                                <span className="font-mono">{config.queue.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{config.queue.driver}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Mail Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-5 w-5" />
                                Mail
                            </CardTitle>
                            <CardDescription>Email configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Mailer</span>
                                <span className="font-mono">{config.mail.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Transport</span>
                                <span className="font-mono">{config.mail.mailer}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Session Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Session
                            </CardTitle>
                            <CardDescription>Session configuration</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{config.session.driver}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Lifetime</span>
                                <span className="font-mono">{config.session.lifetime} minutes</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Maintenance Mode */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5" />
                            Maintenance Mode
                        </CardTitle>
                        <CardDescription>Control application maintenance mode</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium mb-1">
                                    {config.maintenance_mode ? 'Maintenance mode is enabled' : 'Maintenance mode is disabled'}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {config.maintenance_mode
                                        ? 'The application is currently in maintenance mode. Users will see a maintenance page.'
                                        : 'Enable maintenance mode to perform system updates or maintenance.'}
                                </p>
                            </div>
                            <Button
                                variant={config.maintenance_mode ? 'default' : 'destructive'}
                                onClick={() => setMaintenanceDialogOpen(true)}
                                disabled={processing}
                            >
                                {config.maintenance_mode ? 'Disable' : 'Enable'} Maintenance
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationDialog
                    open={maintenanceDialogOpen}
                    onOpenChange={setMaintenanceDialogOpen}
                    onConfirm={handleToggleMaintenance}
                    title={config.maintenance_mode ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode'}
                    description={
                        config.maintenance_mode
                            ? 'Are you sure you want to disable maintenance mode? Users will be able to access the application again.'
                            : 'Are you sure you want to enable maintenance mode? Users will not be able to access the application until maintenance mode is disabled.'
                    }
                    confirmText={config.maintenance_mode ? 'Disable' : 'Enable'}
                    variant={config.maintenance_mode ? 'default' : 'destructive'}
                />
            </div>
        </AdminLayout>
    );
}
