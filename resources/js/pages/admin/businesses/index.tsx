import ConfirmationDialog from '@/components/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Building2,
    Search,
    CheckCircle,
    AlertTriangle,
    Ban,
    Users,
    Calendar,
    DollarSign,
    ChevronLeft,
    ChevronRight,
    ArrowUpDown,
    MoreHorizontal,
    Eye,
} from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Businesses', href: '/admin/businesses' },
];

interface Business {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: 'active' | 'suspended' | 'banned';
    status_reason: string | null;
    status_changed_at: string | null;
    created_at: string;
    business_type: string | null;
    registration_number: string | null;
    escrow_balance: number | string;
    owner: { id: number; name: string; email: string } | null;
    employees_count: number;
    payment_schedules_count: number;
    payroll_schedules_count: number;
}

interface PaginatedBusinesses {
    data: Business[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface StatusCounts {
    all: number;
    active: number;
    suspended: number;
    banned: number;
}

interface Filters {
    status: string;
    search: string;
    sort: string;
    direction: string;
}

interface AdminBusinessesProps {
    businesses: PaginatedBusinesses;
    statusCounts: StatusCounts;
    filters: Filters;
}

export default function AdminBusinesses({ businesses, statusCounts, filters }: AdminBusinessesProps) {
    const [search, setSearch] = useState(filters.search);
    const [statusFilter, setStatusFilter] = useState(filters.status);
    const [selectedBusiness, setSelectedBusiness] = useState<Business | null>(null);
    const [statusDialogOpen, setStatusDialogOpen] = useState(false);
    const [newStatus, setNewStatus] = useState<'active' | 'suspended' | 'banned'>('active');
    const [detailsDialogOpen, setDetailsDialogOpen] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        status: 'active' as 'active' | 'suspended' | 'banned',
        reason: '',
    });

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-ZA', {
            style: 'currency',
            currency: 'ZAR',
        }).format(Number(amount));
    };

    const formatDate = (date: string | null) => {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-ZA', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/admin/businesses', { search, status: statusFilter }, { preserveState: true });
    };

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);
        router.get('/admin/businesses', { search, status: value }, { preserveState: true });
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/admin/businesses', { ...filters, sort: field, direction: newDirection }, { preserveState: true });
    };

    const openStatusDialog = (business: Business, status: 'active' | 'suspended' | 'banned') => {
        setSelectedBusiness(business);
        setNewStatus(status);
        setData({ status, reason: '' });
        setStatusDialogOpen(true);
    };

    const handleStatusChange = () => {
        if (!selectedBusiness) return;

        post(`/admin/businesses/${selectedBusiness.id}/status`, {
            onSuccess: () => {
                setStatusDialogOpen(false);
                setSelectedBusiness(null);
                reset();
            },
        });
    };

    const openDetailsDialog = (business: Business) => {
        setSelectedBusiness(business);
        setDetailsDialogOpen(true);
    };

    const getStatusBadge = (status: string) => {
        const styles = {
            active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            banned: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
        const icons = {
            active: <CheckCircle className="h-3 w-3" />,
            suspended: <AlertTriangle className="h-3 w-3" />,
            banned: <Ban className="h-3 w-3" />,
        };
        return (
            <span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ${styles[status as keyof typeof styles] || ''}`}>
                {icons[status as keyof typeof icons]}
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const getStatusActionLabel = (status: 'active' | 'suspended' | 'banned') => {
        const labels = {
            active: 'Activate',
            suspended: 'Suspend',
            banned: 'Ban',
        };
        return labels[status];
    };

    const getStatusDialogDescription = (status: 'active' | 'suspended' | 'banned') => {
        const descriptions = {
            active: 'This will reactivate the business and allow them to perform all operations.',
            suspended: 'This will temporarily suspend the business. They will not be able to perform any operations until reactivated.',
            banned: 'This will permanently ban the business. This action should be used for serious violations.',
        };
        return descriptions[status];
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Manage Businesses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Manage Businesses</h1>
                        <p className="text-sm text-muted-foreground">View and manage all businesses on the platform</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Status Filter Tabs */}
                <div className="flex gap-2 border-b pb-2">
                    {(['all', 'active', 'suspended', 'banned'] as const).map((status) => (
                        <Button
                            key={status}
                            variant={statusFilter === status ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handleStatusFilterChange(status)}
                            className="gap-2"
                        >
                            {status === 'all' && <Building2 className="h-4 w-4" />}
                            {status === 'active' && <CheckCircle className="h-4 w-4" />}
                            {status === 'suspended' && <AlertTriangle className="h-4 w-4" />}
                            {status === 'banned' && <Ban className="h-4 w-4" />}
                            {status.charAt(0).toUpperCase() + status.slice(1)}
                            <span className="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs">
                                {statusCounts[status]}
                            </span>
                        </Button>
                    ))}
                </div>

                {/* Search */}
                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSearch} className="flex gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search by name, email, or registration number..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <Button type="submit">Search</Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Businesses Table */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b text-left text-sm text-muted-foreground">
                                        <th className="pb-3 pr-4">
                                            <button
                                                onClick={() => handleSort('name')}
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Business
                                                <ArrowUpDown className="h-3 w-3" />
                                            </button>
                                        </th>
                                        <th className="pb-3 pr-4">Owner</th>
                                        <th className="pb-3 pr-4">Status</th>
                                        <th className="pb-3 pr-4">
                                            <button
                                                onClick={() => handleSort('created_at')}
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Created
                                                <ArrowUpDown className="h-3 w-3" />
                                            </button>
                                        </th>
                                        <th className="pb-3 pr-4">Stats</th>
                                        <th className="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {businesses.data.length > 0 ? (
                                        businesses.data.map((business) => (
                                            <tr key={business.id} className="border-b last:border-0">
                                                <td className="py-4 pr-4">
                                                    <div>
                                                        <p className="font-medium">{business.name}</p>
                                                        <p className="text-xs text-muted-foreground">{business.email || 'No email'}</p>
                                                        {business.registration_number && (
                                                            <p className="text-xs text-muted-foreground">Reg: {business.registration_number}</p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-4 pr-4">
                                                    <div>
                                                        <p className="text-sm">{business.owner?.name || 'Unknown'}</p>
                                                        <p className="text-xs text-muted-foreground">{business.owner?.email || ''}</p>
                                                    </div>
                                                </td>
                                                <td className="py-4 pr-4">
                                                    {getStatusBadge(business.status)}
                                                    {business.status_reason && (
                                                        <p className="text-xs text-muted-foreground mt-1 max-w-[150px] truncate" title={business.status_reason}>
                                                            {business.status_reason}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="py-4 pr-4">
                                                    <p className="text-sm">{formatDate(business.created_at)}</p>
                                                </td>
                                                <td className="py-4 pr-4">
                                                    <div className="flex gap-3 text-xs text-muted-foreground">
                                                        <span className="flex items-center gap-1" title="Employees">
                                                            <Users className="h-3 w-3" />
                                                            {business.employees_count}
                                                        </span>
                                                        <span className="flex items-center gap-1" title="Payment Schedules">
                                                            <DollarSign className="h-3 w-3" />
                                                            {business.payment_schedules_count}
                                                        </span>
                                                        <span className="flex items-center gap-1" title="Payroll Schedules">
                                                            <Calendar className="h-3 w-3" />
                                                            {business.payroll_schedules_count}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-4">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuItem onClick={() => openDetailsDialog(business)}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuLabel>Change Status</DropdownMenuLabel>
                                                            {business.status !== 'active' && (
                                                                <DropdownMenuItem onClick={() => openStatusDialog(business, 'active')}>
                                                                    <CheckCircle className="mr-2 h-4 w-4 text-green-600" />
                                                                    Activate
                                                                </DropdownMenuItem>
                                                            )}
                                                            {business.status !== 'suspended' && (
                                                                <DropdownMenuItem onClick={() => openStatusDialog(business, 'suspended')}>
                                                                    <AlertTriangle className="mr-2 h-4 w-4 text-yellow-600" />
                                                                    Suspend
                                                                </DropdownMenuItem>
                                                            )}
                                                            {business.status !== 'banned' && (
                                                                <DropdownMenuItem onClick={() => openStatusDialog(business, 'banned')}>
                                                                    <Ban className="mr-2 h-4 w-4 text-red-600" />
                                                                    Ban
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={6} className="py-8 text-center text-muted-foreground">
                                                No businesses found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {businesses.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Showing {businesses.data.length} of {businesses.total} businesses
                                </p>
                                <div className="flex gap-1">
                                    {businesses.links.map((link, index) => (
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

                {/* Status Change Dialog */}
                <Dialog open={statusDialogOpen} onOpenChange={setStatusDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {getStatusActionLabel(newStatus)} Business
                            </DialogTitle>
                            <DialogDescription>
                                {getStatusDialogDescription(newStatus)}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div>
                                <p className="text-sm font-medium">Business: {selectedBusiness?.name}</p>
                                <p className="text-xs text-muted-foreground">Current status: {selectedBusiness?.status}</p>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="reason">Reason (optional)</Label>
                                <Textarea
                                    id="reason"
                                    placeholder="Provide a reason for this status change..."
                                    value={data.reason}
                                    onChange={(e) => setData('reason', e.target.value)}
                                    rows={3}
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setStatusDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleStatusChange}
                                disabled={processing}
                                variant={newStatus === 'banned' ? 'destructive' : newStatus === 'suspended' ? 'secondary' : 'default'}
                            >
                                {processing ? 'Updating...' : getStatusActionLabel(newStatus)}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Business Details Dialog */}
                <Dialog open={detailsDialogOpen} onOpenChange={setDetailsDialogOpen}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Business Details</DialogTitle>
                        </DialogHeader>
                        {selectedBusiness && (
                            <div className="space-y-4 py-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Business Name</Label>
                                        <p className="font-medium">{selectedBusiness.name}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Status</Label>
                                        <div>{getStatusBadge(selectedBusiness.status)}</div>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Email</Label>
                                        <p>{selectedBusiness.email || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Phone</Label>
                                        <p>{selectedBusiness.phone || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Business Type</Label>
                                        <p>{selectedBusiness.business_type || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Registration Number</Label>
                                        <p>{selectedBusiness.registration_number || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Owner</Label>
                                        <p>{selectedBusiness.owner?.name || 'Unknown'}</p>
                                        <p className="text-xs text-muted-foreground">{selectedBusiness.owner?.email}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Created</Label>
                                        <p>{formatDate(selectedBusiness.created_at)}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Escrow Balance</Label>
                                        <p className="font-medium text-primary">{formatCurrency(selectedBusiness.escrow_balance)}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Statistics</Label>
                                        <div className="flex gap-4 text-sm">
                                            <span>{selectedBusiness.employees_count} employees</span>
                                            <span>{selectedBusiness.payment_schedules_count} payments</span>
                                            <span>{selectedBusiness.payroll_schedules_count} payroll</span>
                                        </div>
                                    </div>
                                </div>
                                {selectedBusiness.status_reason && (
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Status Reason</Label>
                                        <p className="text-sm">{selectedBusiness.status_reason}</p>
                                    </div>
                                )}
                                {selectedBusiness.status_changed_at && (
                                    <div>
                                        <Label className="text-xs text-muted-foreground">Status Changed</Label>
                                        <p className="text-sm">{formatDate(selectedBusiness.status_changed_at)}</p>
                                    </div>
                                )}
                            </div>
                        )}
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDetailsDialogOpen(false)}>
                                Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}
