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
    FileText,
    Clock,
    User,
    Building2,
    Globe,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Audit Logs', href: '/admin/audit-logs' },
];

interface AuditLog {
    id: number;
    user_id: number | null;
    business_id: number | null;
    action: string;
    model_type: string | null;
    model_id: number | null;
    changes: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
    business: { id: number; name: string } | null;
}

interface PaginatedLogs {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
    action: string;
    user_id: string;
    business_id: string;
    date_from: string;
    date_to: string;
    search: string;
    sort: string;
    direction: string;
}

interface AdminAuditLogsProps {
    logs: PaginatedLogs;
    actionTypes: string[];
    filters: Filters;
}

export default function AdminAuditLogs({ logs, actionTypes, filters }: AdminAuditLogsProps) {
    const [search, setSearch] = useState(filters.search);
    const [actionFilter, setActionFilter] = useState(filters.action);
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
        router.get('/admin/audit-logs', {
            search,
            action: actionFilter,
            date_from: dateFrom,
            date_to: dateTo,
        }, { preserveState: true });
    };

    const handleActionFilterChange = (value: string) => {
        setActionFilter(value);
        router.get('/admin/audit-logs', {
            search,
            action: value,
            date_from: dateFrom,
            date_to: dateTo,
        }, { preserveState: true });
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/admin/audit-logs', { ...filters, sort: field, direction: newDirection }, { preserveState: true });
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

    const getActionBadgeColor = (action: string) => {
        if (action.includes('create') || action.includes('added')) {
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        }
        if (action.includes('delete') || action.includes('removed') || action.includes('banned')) {
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        }
        if (action.includes('update') || action.includes('changed')) {
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        }
        return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    };

    const formatModelType = (modelType: string | null) => {
        if (!modelType) return 'N/A';
        // Extract class name from full namespace
        return modelType.split('\\').pop() || modelType;
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Audit Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Audit Logs</h1>
                        <p className="text-sm text-muted-foreground">View all system activity and changes</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
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
                                            placeholder="Search actions, models, or IP addresses..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            className="pl-10"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="action" className="sr-only">Action Type</Label>
                                    <Select value={actionFilter} onValueChange={handleActionFilterChange}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All Actions" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Actions</SelectItem>
                                            {actionTypes.map((action) => (
                                                <SelectItem key={action} value={action}>
                                                    {action}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit">Apply Filters</Button>
                            </div>
                            <div className="grid gap-4 md:grid-cols-4">
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
                                <div className="col-span-3">
                                    <button
                                        onClick={() => handleSort('created_at')}
                                        className="flex items-center gap-1 hover:text-foreground"
                                    >
                                        Time
                                        <ArrowUpDown className="h-3 w-3" />
                                    </button>
                                </div>
                                <div className="col-span-3">Action</div>
                                <div className="col-span-2">User</div>
                                <div className="col-span-2">Business</div>
                                <div className="col-span-2">Details</div>
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
                                            <div className="col-span-3 flex items-center gap-2">
                                                <Clock className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-xs">{formatDateTime(log.created_at)}</span>
                                            </div>
                                            <div className="col-span-3">
                                                <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${getActionBadgeColor(log.action)}`}>
                                                    <FileText className="h-3 w-3" />
                                                    {log.action}
                                                </span>
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
                                                    <span className="text-muted-foreground">System</span>
                                                )}
                                            </div>
                                            <div className="col-span-2 flex items-center gap-2">
                                                {log.business ? (
                                                    <>
                                                        <Building2 className="h-4 w-4 text-muted-foreground" />
                                                        <span className="truncate">{log.business.name}</span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">-</span>
                                                )}
                                            </div>
                                            <div className="col-span-2 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    {log.ip_address && (
                                                        <span className="flex items-center gap-1 text-xs text-muted-foreground" title="IP Address">
                                                            <Globe className="h-3 w-3" />
                                                            {log.ip_address}
                                                        </span>
                                                    )}
                                                </div>
                                                {(log.changes || log.model_type) && (
                                                    <CollapsibleTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            {expandedLogs.has(log.id) ? (
                                                                <ChevronUp className="h-4 w-4" />
                                                            ) : (
                                                                <ChevronDown className="h-4 w-4" />
                                                            )}
                                                        </Button>
                                                    </CollapsibleTrigger>
                                                )}
                                            </div>
                                        </div>
                                        <CollapsibleContent>
                                            <div className="bg-muted/50 rounded-lg p-4 mb-2 space-y-3">
                                                {log.model_type && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">Model:</span>
                                                        <span className="ml-2 text-sm">{formatModelType(log.model_type)} #{log.model_id}</span>
                                                    </div>
                                                )}
                                                {log.changes && Object.keys(log.changes).length > 0 && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">Changes:</span>
                                                        <pre className="mt-1 text-xs bg-background p-2 rounded overflow-x-auto">
                                                            {JSON.stringify(log.changes, null, 2)}
                                                        </pre>
                                                    </div>
                                                )}
                                                {log.user_agent && (
                                                    <div>
                                                        <span className="text-xs font-medium text-muted-foreground">User Agent:</span>
                                                        <p className="text-xs text-muted-foreground mt-1 break-all">{log.user_agent}</p>
                                                    </div>
                                                )}
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                ))
                            ) : (
                                <div className="py-8 text-center text-muted-foreground">
                                    No audit logs found
                                </div>
                            )}
                        </div>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Showing {logs.data.length} of {logs.total} logs
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
