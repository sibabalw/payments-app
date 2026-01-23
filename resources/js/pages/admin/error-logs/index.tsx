import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search,
    ChevronLeft,
    ArrowUpDown,
    AlertTriangle,
    Clock,
    User,
    Globe,
    ChevronDown,
    ChevronUp,
    FileCode,
    ExternalLink,
    Bug,
} from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Error Logs', href: '/admin/error-logs' },
];

interface ErrorLog {
    id: number;
    user_id: number | null;
    type: string;
    level: string;
    message: string;
    exception: string | null;
    trace: string | null;
    file: string | null;
    line: number | null;
    url: string | null;
    method: string | null;
    ip_address: string | null;
    user_agent: string | null;
    context: Record<string, unknown> | null;
    is_admin_error: boolean;
    notified: boolean;
    notified_at: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
}

interface PaginatedLogs {
    data: ErrorLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    level: string;
    type: string;
    error_type: string;
    notified: string;
    date_from: string;
    date_to: string;
    search: string;
    sort: string;
    direction: string;
}

interface Stats {
    total: number;
    critical: number;
    error: number;
    warning: number;
    admin_errors: number;
    user_errors: number;
    notified: number;
}

interface AdminErrorLogsProps {
    logs: PaginatedLogs;
    levels: string[];
    types: string[];
    stats: Stats;
    filters: Filters;
}

export default function AdminErrorLogs({ logs, levels, types, stats, filters }: AdminErrorLogsProps) {
    const [search, setSearch] = useState(filters.search);
    const [levelFilter, setLevelFilter] = useState(filters.level);
    const [typeFilter, setTypeFilter] = useState(filters.type);
    const [errorTypeFilter, setErrorTypeFilter] = useState(filters.error_type);
    const [notifiedFilter, setNotifiedFilter] = useState(filters.notified);
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [expandedLogs, setExpandedLogs] = useState<Set<number>>(new Set());

    const formatDateTime = (date: string) => {
        return new Date(date).toLocaleString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/error-logs', {
            search,
            level: levelFilter,
            type: typeFilter,
            error_type: errorTypeFilter,
            notified: notifiedFilter,
            date_from: dateFrom,
            date_to: dateTo,
        }, { preserveState: true });
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get('/admin/error-logs', {
            ...filters,
            [key]: value,
        }, { preserveState: true });
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/admin/error-logs', { ...filters, sort: field, direction: newDirection }, { preserveState: true });
    };

    const toggleExpanded = (logId: number) => {
        const newExpanded = new Set(expandedLogs);
        if (newExpanded.has(logId)) {
            newExpanded.delete(logId);
        } else {
            newExpanded.add(logId);
        }
        setExpandedLogs(newExpanded);
    };

    const getLevelBadgeColor = (level: string) => {
        switch (level) {
            case 'critical':
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            case 'error':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            case 'warning':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            case 'info':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        }
    };

    const truncateMessage = (message: string, maxLength: number = 100) => {
        if (message.length <= maxLength) return message;
        return message.substring(0, maxLength) + '...';
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Error Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Error Logs</h1>
                        <p className="text-sm text-muted-foreground">View and monitor application errors</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-4 lg:grid-cols-7">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-xs text-muted-foreground">Total Errors</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold text-red-600">{stats.critical}</div>
                            <p className="text-xs text-muted-foreground">Critical</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold text-orange-600">{stats.error}</div>
                            <p className="text-xs text-muted-foreground">Errors</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold text-yellow-600">{stats.warning}</div>
                            <p className="text-xs text-muted-foreground">Warnings</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold">{stats.admin_errors}</div>
                            <p className="text-xs text-muted-foreground">Admin Errors</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold">{stats.user_errors}</div>
                            <p className="text-xs text-muted-foreground">User Errors</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-2xl font-bold">{stats.notified}</div>
                            <p className="text-xs text-muted-foreground">Notified</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSearch} className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                <div className="md:col-span-2">
                                    <Label htmlFor="search" className="sr-only">Search</Label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            placeholder="Search messages, exceptions, files, or URLs..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            className="pl-10"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="level" className="sr-only">Level</Label>
                                    <Select value={levelFilter} onValueChange={(value) => {
                                        setLevelFilter(value);
                                        handleFilterChange('level', value);
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Levels" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Levels</SelectItem>
                                            {levels.map((level) => (
                                                <SelectItem key={level} value={level}>
                                                    {level}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit">Apply Filters</Button>
                            </div>
                            <div className="grid gap-4 md:grid-cols-5">
                                <div>
                                    <Label htmlFor="type">Type</Label>
                                    <Select value={typeFilter} onValueChange={(value) => {
                                        setTypeFilter(value);
                                        handleFilterChange('type', value);
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Types</SelectItem>
                                            {types.map((type) => (
                                                <SelectItem key={type} value={type}>
                                                    {type}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="error_type">Error Type</Label>
                                    <Select value={errorTypeFilter} onValueChange={(value) => {
                                        setErrorTypeFilter(value);
                                        handleFilterChange('error_type', value);
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All</SelectItem>
                                            <SelectItem value="admin">Admin Errors</SelectItem>
                                            <SelectItem value="user">User Errors</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="notified">Notification</Label>
                                    <Select value={notifiedFilter} onValueChange={(value) => {
                                        setNotifiedFilter(value);
                                        handleFilterChange('notified', value);
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All</SelectItem>
                                            <SelectItem value="yes">Notified</SelectItem>
                                            <SelectItem value="no">Not Notified</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="date_from">From Date</Label>
                                    <Input
                                        id="date_from"
                                        type="date"
                                        value={dateFrom}
                                        onChange={(e) => setDateFrom(e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="date_to">To Date</Label>
                                    <Input
                                        id="date_to"
                                        type="date"
                                        value={dateTo}
                                        onChange={(e) => setDateTo(e.target.value)}
                                    />
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Logs Table */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="space-y-2">
                            {/* Header */}
                            <div className="grid grid-cols-12 gap-4 border-b pb-2 text-sm font-medium text-muted-foreground">
                                <div className="col-span-2">
                                    <button
                                        onClick={() => handleSort('created_at')}
                                        className="flex items-center gap-1 hover:text-foreground"
                                    >
                                        Time
                                        <ArrowUpDown className="h-3 w-3" />
                                    </button>
                                </div>
                                <div className="col-span-1">
                                    <button
                                        onClick={() => handleSort('level')}
                                        className="flex items-center gap-1 hover:text-foreground"
                                    >
                                        Level
                                        <ArrowUpDown className="h-3 w-3" />
                                    </button>
                                </div>
                                <div className="col-span-4">Message</div>
                                <div className="col-span-2">User</div>
                                <div className="col-span-2">Details</div>
                                <div className="col-span-1">Actions</div>
                            </div>

                            {/* Logs */}
                            {logs.data.length > 0 ? (
                                logs.data.map((log) => (
                                    <Collapsible
                                        key={log.id}
                                        open={expandedLogs.has(log.id)}
                                        onOpenChange={() => toggleExpanded(log.id)}
                                    >
                                        <div className="grid grid-cols-12 gap-4 border-b py-3 text-sm last:border-0">
                                            <div className="col-span-2 flex items-center gap-2">
                                                <Clock className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-xs">{formatDateTime(log.created_at)}</span>
                                            </div>
                                            <div className="col-span-1">
                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${getLevelBadgeColor(log.level)}`}>
                                                    <AlertTriangle className="h-3 w-3" />
                                                    {log.level}
                                                </span>
                                            </div>
                                            <div className="col-span-4">
                                                <div className="flex items-center gap-2">
                                                    {log.is_admin_error && (
                                                        <span className="text-xs font-medium text-purple-600">Admin</span>
                                                    )}
                                                    <span className="truncate" title={log.message}>
                                                        {truncateMessage(log.message)}
                                                    </span>
                                                </div>
                                                {log.exception && (
                                                    <div className="text-xs text-muted-foreground mt-1 truncate">
                                                        {log.exception}
                                                    </div>
                                                )}
                                            </div>
                                            <div className="col-span-2 flex items-center gap-2">
                                                {log.user ? (
                                                    <>
                                                        <User className="h-4 w-4 text-muted-foreground" />
                                                        <span className="truncate" title={log.user.email}>
                                                            {log.user.name}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </div>
                                            <div className="col-span-2 flex items-center gap-2">
                                                {log.url && (
                                                    <span className="flex items-center gap-1 text-xs text-muted-foreground truncate" title={log.url}>
                                                        <Globe className="h-3 w-3" />
                                                        {log.method} {log.url.substring(0, 30)}...
                                                    </span>
                                                )}
                                            </div>
                                            <div className="col-span-1 flex items-center justify-end gap-2">
                                                <Link href={`/admin/error-logs/${log.id}`}>
                                                    <Button variant="ghost" size="sm">
                                                        <ExternalLink className="h-4 w-4" />
                                                    </Button>
                                                </Link>
                                                <CollapsibleTrigger asChild>
                                                    <Button variant="ghost" size="sm">
                                                        {expandedLogs.has(log.id) ? (
                                                            <ChevronUp className="h-4 w-4" />
                                                        ) : (
                                                            <ChevronDown className="h-4 w-4" />
                                                        )}
                                                    </Button>
                                                </CollapsibleTrigger>
                                            </div>
                                        </div>
                                        <CollapsibleContent>
                                            <div className="bg-muted/50 rounded-lg p-4 mb-2 space-y-3">
                                                {log.file && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">File:</span>
                                                        <span className="ml-2 text-sm font-mono">{log.file}:{log.line}</span>
                                                    </div>
                                                )}
                                                {log.trace && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">Stack Trace:</span>
                                                        <pre className="mt-1 text-xs bg-background p-2 rounded overflow-x-auto max-h-60">
                                                            {log.trace.substring(0, 2000)}{log.trace.length > 2000 ? '...' : ''}
                                                        </pre>
                                                    </div>
                                                )}
                                                {log.context && Object.keys(log.context).length > 0 && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">Context:</span>
                                                        <pre className="mt-1 text-xs bg-background p-2 rounded overflow-x-auto">
                                                            {JSON.stringify(log.context, null, 2)}
                                                        </pre>
                                                    </div>
                                                )}
                                                {log.user_agent && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">User Agent:</span>
                                                        <p className="text-xs text-muted-foreground mt-1 break-all">{log.user_agent}</p>
                                                    </div>
                                                )}
                                                {log.ip_address && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">IP Address:</span>
                                                        <span className="ml-2 text-xs">{log.ip_address}</span>
                                                    </div>
                                                )}
                                                {log.notified && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">Notified:</span>
                                                        <span className="ml-2 text-xs">
                                                            {log.notified_at ? formatDateTime(log.notified_at) : 'Yes'}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                ))
                            ) : (
                                <div className="py-8 text-center text-muted-foreground">
                                    No error logs found
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Showing {logs.data.length} of {logs.total} errors
                                </p>
                                <div className="flex gap-1">
                                    {logs.links.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url)}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
