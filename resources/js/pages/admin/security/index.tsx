import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ChevronLeft,
    Shield,
    AlertTriangle,
    Users,
    Lock,
    Activity,
    Ban,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Security Management', href: '/admin/security' },
];

interface SecurityProps {
    rateLimitConfig: Record<
        string,
        {
            max_attempts: number;
            decay_minutes: number;
            description: string;
        }
    >;
    failedLogins: Array<{
        id: number;
        user_id: number | null;
        ip_address: string | null;
        created_at: string;
        email: string | null;
    }>;
    securityEvents: Record<
        string,
        Array<{
            date: string;
            count: number;
        }>
    >;
    activeSessions: number;
    suspiciousIPs: Array<{
        ip_address: string;
        attempt_count: number;
    }>;
    twoFactorStats: {
        enabled: number;
        disabled: number;
    };
}

export default function Security({
    rateLimitConfig,
    failedLogins,
    securityEvents,
    activeSessions,
    suspiciousIPs,
    twoFactorStats,
}: SecurityProps) {
    const formatDate = (date: string) => {
        return new Date(date).toLocaleString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Security Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Security Management</h1>
                        <p className="text-sm text-muted-foreground">Monitor security events and manage rate limits</p>
                    </div>
                    <Link href="/admin">
                        <button className="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </button>
                    </Link>
                </div>

                {/* Security Overview */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Sessions</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{activeSessions}</div>
                            <p className="text-xs text-muted-foreground">Currently active</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Failed Logins (24h)</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{failedLogins.length}</div>
                            <p className="text-xs text-muted-foreground">Recent failed attempts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">2FA Enabled</CardTitle>
                            <Lock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{twoFactorStats.enabled}</div>
                            <p className="text-xs text-muted-foreground">Users with 2FA</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Rate Limit Configuration */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Rate Limit Configuration
                        </CardTitle>
                        <CardDescription>Current rate limiting settings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {Object.entries(rateLimitConfig).map(([key, config]) => (
                                <div key={key} className="border rounded-lg p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium capitalize">{key.replace('_', ' ')}</span>
                                        <Badge variant="outline">
                                            {config.max_attempts} attempts
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">{config.description}</p>
                                    <div className="flex justify-between text-xs text-muted-foreground">
                                        <span>Decay: {config.decay_minutes} minutes</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Suspicious IPs */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Ban className="h-5 w-5" />
                            Suspicious IP Addresses
                        </CardTitle>
                        <CardDescription>IPs with multiple failed login attempts (7 days)</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {suspiciousIPs.length > 0 ? (
                            <div className="space-y-3">
                                {suspiciousIPs.map((ip) => (
                                    <div
                                        key={ip.ip_address}
                                        className="flex items-center justify-between border-b pb-3 last:border-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium font-mono">{ip.ip_address}</p>
                                        </div>
                                        <Badge
                                            className={
                                                ip.attempt_count > 10
                                                    ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                    : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                                            }
                                        >
                                            {ip.attempt_count} attempts
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-4">No suspicious IPs found</p>
                        )}
                    </CardContent>
                </Card>

                {/* Failed Login Attempts */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-red-600" />
                            Recent Failed Login Attempts
                        </CardTitle>
                        <CardDescription>Failed login attempts in the last 24 hours</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {failedLogins.length > 0 ? (
                            <div className="space-y-3">
                                {failedLogins.slice(0, 20).map((login) => (
                                    <div
                                        key={login.id}
                                        className="flex items-center justify-between border-b pb-3 last:border-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {login.email || `User ID: ${login.user_id || 'Unknown'}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {login.ip_address && (
                                                    <span className="font-mono">{login.ip_address}</span>
                                                )}
                                                {' • '}
                                                {formatDate(login.created_at)}
                                            </p>
                                        </div>
                                        <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Failed
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-4">No failed login attempts</p>
                        )}
                    </CardContent>
                </Card>

                {/* Security Events Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5" />
                            Security Events Summary (7 Days)
                        </CardTitle>
                        <CardDescription>Security-related events by type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {Object.entries(securityEvents).map(([action, events]) => {
                                const totalCount = events.reduce((sum, e) => sum + e.count, 0);
                                return (
                                    <div key={action} className="border rounded-lg p-4">
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="font-medium capitalize">
                                                {action.replace(/\./g, ' ').replace(/_/g, ' ')}
                                            </span>
                                            <Badge variant="secondary">{totalCount}</Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {events.length} day{events.length !== 1 ? 's' : ''} with activity
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
