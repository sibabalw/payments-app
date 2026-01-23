import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { BookOpen, Bot, Folder, LayoutGrid, CreditCard, Users, FileText, Building2, DollarSign, UserCheck, Receipt, Clock, ReceiptText, Palette, Shield } from 'lucide-react';
import { BusinessSwitcher } from './business-switcher';
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

export function AppSidebar() {
    const { businessesCount = 0 } = usePage<SharedData>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'AI Assistant',
            href: '/chat',
            icon: Bot,
        },
        {
            title: 'Payments',
            href: '/payments',
            icon: CreditCard,
        },
        {
            title: 'Payroll',
            href: '/payroll',
            icon: DollarSign,
        },
        {
            title: 'Payslips',
            href: '/payslips',
            icon: ReceiptText,
        },
        {
            title: 'Reports',
            href: '/reports',
            icon: FileText,
        },
        {
            title: 'Recipients',
            href: '/recipients',
            icon: Users,
        },
        {
            title: 'Employees',
            href: '/employees',
            icon: UserCheck,
        },
        {
            title: 'Adjustments',
            href: '/adjustments',
            icon: Receipt,
        },
        {
            title: 'Time Tracking',
            href: '/time-tracking',
            icon: Clock,
        },
        {
            title: 'Leave',
            href: '/leave',
            icon: FileText,
        },
        {
            title: 'Audit Logs',
            href: '/audit-logs',
            icon: FileText,
        },
        {
            title: 'Compliance',
            href: '/compliance',
            icon: Shield,
        },
        {
            title: 'Templates',
            href: '/templates',
            icon: Palette,
        },
        {
            title: 'Businesses',
            href: '/businesses',
            icon: Building2,
            badge: businessesCount === 0 ? '0' : undefined,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <div className="flex items-center justify-between gap-2">
                    <SidebarMenu className="flex-1">
                        <SidebarMenuItem>
                            <BusinessSwitcher />
                        </SidebarMenuItem>
                    </SidebarMenu>
                    <AppearanceToggleDropdown />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
