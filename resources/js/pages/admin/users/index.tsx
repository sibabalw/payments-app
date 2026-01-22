import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search,
    ChevronLeft,
    ArrowUpDown,
    MoreHorizontal,
    Shield,
    ShieldOff,
    Users,
    UserCheck,
    Mail,
    MailX,
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
import ConfirmationDialog from '@/components/confirmation-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];

interface User {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    businesses_count: number;
    owned_businesses_count: number;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface RoleCounts {
    all: number;
    admin: number;
    user: number;
}

interface Filters {
    role: string;
    verified: string;
    search: string;
    sort: string;
    direction: string;
}

interface AdminUsersProps {
    users: PaginatedUsers;
    roleCounts: RoleCounts;
    filters: Filters;
}

export default function AdminUsers({ users, roleCounts, filters }: AdminUsersProps) {
    const [search, setSearch] = useState(filters.search);
    const [roleFilter, setRoleFilter] = useState(filters.role);
    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [confirmDialogOpen, setConfirmDialogOpen] = useState(false);

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
        router.get('/admin/users', { search, role: roleFilter, verified: filters.verified }, { preserveState: true });
    };

    const handleRoleFilterChange = (value: string) => {
        setRoleFilter(value);
        router.get('/admin/users', { search, role: value, verified: filters.verified }, { preserveState: true });
    };

    const handleSort = (field: string) => {
        const newDirection = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/admin/users', { ...filters, sort: field, direction: newDirection }, { preserveState: true });
    };

    const openToggleAdminDialog = (user: User) => {
        setSelectedUser(user);
        setConfirmDialogOpen(true);
    };

    const handleToggleAdmin = () => {
        if (!selectedUser) return;

        router.post(`/admin/users/${selectedUser.id}/toggle-admin`, {}, {
            onSuccess: () => {
                setConfirmDialogOpen(false);
                setSelectedUser(null);
            },
        });
    };

    const getRoleBadge = (isAdmin: boolean) => {
        if (isAdmin) {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-1 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    <Shield className="h-3 w-3" />
                    Admin
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                <Users className="h-3 w-3" />
                User
            </span>
        );
    };

    const getVerificationBadge = (verifiedAt: string | null) => {
        if (verifiedAt) {
            return (
                <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                    <Mail className="h-3 w-3" />
                    Verified
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                <MailX className="h-3 w-3" />
                Unverified
            </span>
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Manage Users" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Manage Users</h1>
                        <p className="text-sm text-muted-foreground">View and manage all platform users</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {/* Role Filter Tabs */}
                <div className="flex gap-2 border-b pb-2">
                    {(['all', 'admin', 'user'] as const).map((role) => (
                        <Button
                            key={role}
                            variant={roleFilter === role ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => handleRoleFilterChange(role)}
                            className="gap-2"
                        >
                            {role === 'all' && <Users className="h-4 w-4" />}
                            {role === 'admin' && <Shield className="h-4 w-4" />}
                            {role === 'user' && <UserCheck className="h-4 w-4" />}
                            {role === 'all' ? 'All Users' : role.charAt(0).toUpperCase() + role.slice(1) + 's'}
                            <span className="ml-1 rounded-full bg-muted px-2 py-0.5 text-xs">
                                {roleCounts[role]}
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
                                        placeholder="Search by name or email..."
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

                {/* Users Table */}
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
                                                User
                                                <ArrowUpDown className="h-3 w-3" />
                                            </button>
                                        </th>
                                        <th className="pb-3 pr-4">Role</th>
                                        <th className="pb-3 pr-4">Email Status</th>
                                        <th className="pb-3 pr-4">
                                            <button
                                                onClick={() => handleSort('created_at')}
                                                className="flex items-center gap-1 hover:text-foreground"
                                            >
                                                Joined
                                                <ArrowUpDown className="h-3 w-3" />
                                            </button>
                                        </th>
                                        <th className="pb-3 pr-4">Businesses</th>
                                        <th className="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.length > 0 ? (
                                        users.data.map((user) => (
                                            <tr key={user.id} className="border-b last:border-0">
                                                <td className="py-4 pr-4">
                                                    <div className="flex items-center gap-3">
                                                        {user.avatar ? (
                                                            <img
                                                                src={user.avatar}
                                                                alt={user.name}
                                                                className="h-10 w-10 rounded-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-sm font-medium">
                                                                {user.name.charAt(0).toUpperCase()}
                                                            </div>
                                                        )}
                                                        <div>
                                                            <p className="font-medium">{user.name}</p>
                                                            <p className="text-xs text-muted-foreground">{user.email}</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-4 pr-4">
                                                    {getRoleBadge(user.is_admin)}
                                                </td>
                                                <td className="py-4 pr-4">
                                                    {getVerificationBadge(user.email_verified_at)}
                                                </td>
                                                <td className="py-4 pr-4">
                                                    <p className="text-sm">{formatDate(user.created_at)}</p>
                                                </td>
                                                <td className="py-4 pr-4">
                                                    <div className="text-sm">
                                                        <p>{user.owned_businesses_count} owned</p>
                                                        <p className="text-xs text-muted-foreground">{user.businesses_count} associated</p>
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
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => openToggleAdminDialog(user)}>
                                                                {user.is_admin ? (
                                                                    <>
                                                                        <ShieldOff className="mr-2 h-4 w-4 text-red-600" />
                                                                        Remove Admin
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <Shield className="mr-2 h-4 w-4 text-purple-600" />
                                                                        Grant Admin
                                                                    </>
                                                                )}
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={6} className="py-8 text-center text-muted-foreground">
                                                No users found
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {users.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4 pt-4 border-t">
                                <p className="text-sm text-muted-foreground">
                                    Showing {users.data.length} of {users.total} users
                                </p>
                                <div className="flex gap-1">
                                    {users.links.map((link, index) => (
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

                {/* Toggle Admin Confirmation Dialog */}
                <ConfirmationDialog
                    open={confirmDialogOpen}
                    onOpenChange={setConfirmDialogOpen}
                    onConfirm={handleToggleAdmin}
                    title={selectedUser?.is_admin ? 'Remove Admin Privileges' : 'Grant Admin Privileges'}
                    description={
                        selectedUser?.is_admin
                            ? `Are you sure you want to remove admin privileges from ${selectedUser?.name}? They will no longer have access to the admin panel.`
                            : `Are you sure you want to grant admin privileges to ${selectedUser?.name}? They will have full access to the admin panel.`
                    }
                    confirmText={selectedUser?.is_admin ? 'Remove Admin' : 'Grant Admin'}
                    variant={selectedUser?.is_admin ? 'destructive' : 'info'}
                />
            </div>
        </AdminLayout>
    );
}
