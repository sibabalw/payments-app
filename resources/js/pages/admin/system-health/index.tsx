import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ChevronLeft,
    Activity,
    Database,
    HardDrive,
    Server,
    CheckCircle,
    XCircle,
    AlertTriangle,
    Clock,
    Globe,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'System Health', href: '/admin/system-health' },
];

interface SystemHealthProps {
    dbStatus: string;
    cacheStatus: string;
    queueStatus: string;
    systemMetrics: {
        php_version: string;
        laravel_version: string;
        memory_limit: string;
        max_execution_time: string;
        timezone: string;
        environment: string;
        debug_mode: boolean;
    };
    dbMetrics: {
        connection: string;
        driver: string;
        response_time_ms: number | null;
    };
    cacheMetrics: {
        driver: string;
        status: string;
    };
    queueMetrics: {
        connection: string;
        driver: string;
        status: string;
    };
    logSize: string;
}

export default function SystemHealth({
    dbStatus,
    cacheStatus,
    queueStatus,
    systemMetrics,
    dbMetrics,
    cacheMetrics,
    queueMetrics,
    logSize,
}: SystemHealthProps) {
    const getStatusBadge = (status: string) => {
        if (status === 'healthy') {
            return (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Healthy
                </Badge>
            );
        }
        return (
            <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                <XCircle className="mr-1 h-3 w-3" />
                Unhealthy
            </Badge>
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - System Health" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">System Health</h1>
                        <p className="text-sm text-muted-foreground">Monitor system status and performance</p>
                    </div>
                    <Link href="/admin">
                        <button className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </button>
                    </Link>
                </div>

                {/* Status Overview */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Database</CardTitle>
                            <Database className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                {getStatusBadge(dbStatus)}
                                {dbMetrics.response_time_ms !== null && (
                                    <span className="text-xs text-muted-foreground">
                                        {dbMetrics.response_time_ms}ms
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Cache</CardTitle>
                            <HardDrive className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {getStatusBadge(cacheStatus)}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Queue</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            {getStatusBadge(queueStatus)}
                        </CardContent>
                    </Card>
                </div>

                {/* System Metrics */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            System Information
                        </CardTitle>
                        <CardDescription>Application and server configuration</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">PHP Version</span>
                                    <span className="font-mono">{systemMetrics.php_version}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Laravel Version</span>
                                    <span className="font-mono">{systemMetrics.laravel_version}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Memory Limit</span>
                                    <span className="font-mono">{systemMetrics.memory_limit}</span>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Max Execution Time</span>
                                    <span className="font-mono">{systemMetrics.max_execution_time}s</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Timezone</span>
                                    <span className="font-mono">{systemMetrics.timezone}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Environment</span>
                                    <Badge variant={systemMetrics.environment === 'production' ? 'default' : 'secondary'}>
                                        {systemMetrics.environment}
                                    </Badge>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Debug Mode</span>
                                    {systemMetrics.debug_mode ? (
                                        <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            <AlertTriangle className="mr-1 h-3 w-3" />
                                            Enabled
                                        </Badge>
                                    ) : (
                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Disabled
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Service Details */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="h-5 w-5" />
                                Database
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Connection</span>
                                <span className="font-mono">{dbMetrics.connection}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{dbMetrics.driver}</span>
                            </div>
                            {dbMetrics.response_time_ms !== null && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Response Time</span>
                                    <span className="font-mono flex items-center gap-1">
                                        <Clock className="h-3 w-3" />
                                        {dbMetrics.response_time_ms}ms
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HardDrive className="h-5 w-5" />
                                Cache & Queue
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <div className="text-xs text-muted-foreground mb-1">Cache Driver</div>
                                <div className="font-mono text-sm">{cacheMetrics.driver}</div>
                            </div>
                            <div>
                                <div className="text-xs text-muted-foreground mb-1">Queue Connection</div>
                                <div className="font-mono text-sm">{queueMetrics.connection}</div>
                            </div>
                            <div>
                                <div className="text-xs text-muted-foreground mb-1">Queue Driver</div>
                                <div className="font-mono text-sm">{queueMetrics.driver}</div>
                            </div>
                            <div>
                                <div className="text-xs text-muted-foreground mb-1">Log File Size</div>
                                <div className="font-mono text-sm">{logSize}</div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
