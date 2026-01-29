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
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useActiveUrl } from '@/hooks/use-active-url';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    Building2,
    ChevronDown,
    LayoutGrid,
    Server,
    Settings,
    Shield,
    UserCircle,
    Users,
    Wallet,
    FileText,
    Flag,
    Lock,
    Gauge,
    HardDrive,
    CreditCard,
    BarChart3,
    MessageSquare,
} from 'lucide-react';
import AppearanceToggleDropdown from './appearance-dropdown';

const footerNavItems: NavItem[] = [];

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
        title: 'Escrow',
        icon: Wallet,
        items: [
            { title: 'Overview', href: '/admin/escrow' },
            { title: 'Balances', href: '/admin/escrow/balances' },
        ],
    },
    {
        title: 'Users',
        icon: Users,
        items: [
            { title: 'All Users', href: '/admin/users' },
            { title: 'Create User', href: '/admin/users/create' },
        ],
    },
    {
        title: 'Tickets',
        href: '/admin/tickets',
        icon: MessageSquare,
    },
    {
        title: 'Logs & Queue',
        icon: FileText,
        items: [
            { title: 'Audit Logs', href: '/admin/audit-logs' },
            { title: 'Error Logs', href: '/admin/error-logs' },
            { title: 'Logs', href: '/admin/logs' },
            { title: 'Queue Management', href: '/admin/queue' },
        ],
    },
    {
        title: 'Account',
        icon: UserCircle,
        items: [
            { title: 'Profile', href: '/admin/account/profile' },
            { title: 'Password', href: '/admin/account/password' },
            { title: 'Appearance', href: '/admin/account/appearance' },
            { title: 'Two-Factor', href: '/admin/account/two-factor' },
        ],
    },
    {
        title: 'Configuration',
        icon: Settings,
        items: [
            { title: 'Settings', href: '/admin/settings' },
            { title: 'System Configuration', href: '/admin/system-configuration' },
            { title: 'Email Configuration', href: '/admin/email-configuration' },
        ],
    },
    {
        title: 'System',
        icon: Server,
        items: [
            { title: 'System Health', href: '/admin/system-health' },
            { title: 'Database', href: '/admin/database' },
            { title: 'Performance', href: '/admin/performance' },
            { title: 'Storage', href: '/admin/storage' },
        ],
    },
    {
        title: 'Security',
        href: '/admin/security',
        icon: Lock,
    },
    {
        title: 'Feature Flags',
        href: '/admin/feature-flags',
        icon: Flag,
    },
    {
        title: 'Subscriptions',
        href: '/admin/subscriptions',
        icon: CreditCard,
    },
    {
        title: 'System Reports',
        href: '/admin/system-reports',
        icon: BarChart3,
    },
];

function AdminNavMain({ items = [] }: { items: NavItem[] }) {
    const { urlIsActive } = useActiveUrl();
    // All items with children are closed by default
    const [openItems, setOpenItems] = useState<Record<string, boolean>>({});

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Admin Panel</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const hasChildren = item.items && item.items.length > 0;
                    const isParentActive =
                        hasChildren &&
                        item.items!.some((child) => child.href && urlIsActive(child.href));

                    if (hasChildren) {
                        const firstChildHref = item.items![0]?.href;
                        // Open if explicitly set, or if a child is currently active
                        const isOpen = openItems[item.title] ?? isParentActive;

                        return (
                            <Collapsible
                                key={item.title}
                                open={isOpen}
                                onOpenChange={(open) => {
                                    setOpenItems((prev) => ({
                                        ...prev,
                                        [item.title]: open,
                                    }));
                                }}
                                className="group/collapsible"
                            >
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isParentActive}
                                        tooltip={{ children: item.title }}
                                    >
                                        <Link
                                            href={firstChildHref || '#'}
                                            onClick={(e) => {
                                                // Expand the collapsible if closed, or toggle if open
                                                const newOpenState = !isOpen;
                                                setOpenItems((prev) => ({
                                                    ...prev,
                                                    [item.title]: newOpenState,
                                                }));
                                                
                                                // If already on a child page, prevent navigation and just toggle
                                                if (isParentActive) {
                                                    e.preventDefault();
                                                }
                                            }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <ChevronDown className="ml-auto size-4 shrink-0 transition-transform group-data-[state=closed]/collapsible:rotate-[-90deg]" />
                                        </Link>
                                    </SidebarMenuButton>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items!.map((child) => (
                                                <SidebarMenuSubItem key={child.title}>
                                                    <SidebarMenuSubButton
                                                        asChild
                                                        isActive={
                                                            child.href
                                                                ? urlIsActive(child.href)
                                                                : false
                                                        }
                                                    >
                                                        <Link href={child.href!}>
                                                            <span>{child.title}</span>
                                                        </Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        );
                    }

                    if (!item.href) {
                        return null;
                    }

                    return (
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
                    );
                })}
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
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
