import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    Folder,
    LayoutGrid,
    Shield,
    Users,
    Wallet,
    Settings,
    UserCircle,
    Activity,
    FileText,
    Server,
    Database,
    Mail,
    HardDrive,
    Monitor,
    AlertTriangle,
} from 'lucide-react';
import AppearanceToggleDropdown from './appearance-dropdown';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin',
        icon: LayoutGrid,
    },
    {
        title: 'Businesses',
        href: '/admin/businesses',
        icon: Building2,
    },
    {
        title: 'Escrow Management',
        href: '/admin/escrow',
        icon: Wallet,
    },
    {
        title: 'Escrow Balances',
        href: '/admin/escrow/balances',
        icon: Activity,
    },
    {
        title: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Audit Logs',
        href: '/admin/audit-logs',
        icon: FileText,
    },
    {
        title: 'Error Logs',
        href: '/admin/error-logs',
        icon: AlertTriangle,
    },
    {
        title: 'Account',
        href: '/admin/account/profile',
        icon: UserCircle,
    },
    {
        title: 'Settings',
        href: '/admin/settings',
        icon: Settings,
    },
    {
        title: 'System Health',
        href: '/admin/system-health',
        icon: Monitor,
    },
    {
        title: 'System Configuration',
        href: '/admin/system-configuration',
        icon: Server,
    },
    {
        title: 'Logs',
        href: '/admin/logs',
        icon: FileText,
    },
    {
        title: 'Queue Management',
        href: '/admin/queue',
        icon: Activity,
    },
    {
        title: 'Database',
        href: '/admin/database',
        icon: Database,
    },
    {
        title: 'Email Configuration',
        href: '/admin/email-configuration',
        icon: Mail,
    },
];

function AdminNavMain({ items = [] }: { items: NavItem[] }) {
    const { urlIsActive } = useActiveUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Admin Panel</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={urlIsActive(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href}>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

export function AdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <div className="flex items-center justify-between gap-2">
                    <SidebarMenu className="flex-1">
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href="/admin" className="flex items-center gap-2">
                                    <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                        <Shield className="size-4" />
                                    </div>
                                    <div className="grid flex-1 text-left text-sm leading-tight">
                                        <span className="truncate font-semibold">Admin Panel</span>
                                        <span className="truncate text-xs text-muted-foreground">Swift Pay</span>
                                    </div>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                    <AppearanceToggleDropdown />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <AdminNavMain items={adminNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
