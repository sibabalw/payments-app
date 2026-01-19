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
import { BookOpen, Folder, LayoutGrid, CreditCard, Users, FileText, Building2, DollarSign, UserCheck, Receipt, Clock, ReceiptText } from 'lucide-react';
import { BusinessSwitcher } from './business-switcher';

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
            title: 'Deductions',
            href: '/deductions',
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
            title: 'Businesses',
            href: '/businesses',
            icon: Building2,
            badge: businessesCount === 0 ? '0' : undefined,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <BusinessSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
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
