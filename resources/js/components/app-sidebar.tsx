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
import { Bot, LayoutGrid, CreditCard, Users, FileText, Building2, DollarSign, UserCheck, Receipt, Clock, ReceiptText, Palette, Shield } from 'lucide-react';
import { BusinessSwitcher } from './business-switcher';
import AppearanceToggleDropdown from './appearance-dropdown';

const footerNavItems: NavItem[] = [];

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
            icon: CreditCard,
            items: [
                { title: 'Schedules', href: '/payments' },
                { title: 'Payment Jobs', href: '/payments/jobs' },
            ],
        },
        {
            title: 'Payroll',
            icon: DollarSign,
            items: [
                { title: 'Schedules', href: '/payroll' },
                { title: 'Payroll Jobs', href: '/payroll/jobs' },
            ],
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
            icon: Users,
            items: [
                { title: 'All Recipients', href: '/recipients' },
                { title: 'Create Recipient', href: '/recipients/create' },
            ],
        },
        {
            title: 'Employees',
            icon: UserCheck,
            items: [
                { title: 'All Employees', href: '/employees' },
                { title: 'Create Employee', href: '/employees/create' },
            ],
        },
        {
            title: 'Adjustments',
            icon: Receipt,
            items: [
                { title: 'List', href: '/adjustments' },
                { title: 'Create Adjustment', href: '/adjustments/create' },
            ],
        },
        {
            title: 'Time Tracking',
            icon: Clock,
            items: [
                { title: 'Entries', href: '/time-tracking' },
                { title: 'Manual Entry', href: '/time-tracking/manual' },
            ],
        },
        {
            title: 'Leave',
            icon: FileText,
            items: [
                { title: 'List', href: '/leave' },
                { title: 'Create Leave', href: '/leave/create' },
            ],
        },
        {
            title: 'Audit Logs',
            href: '/audit-logs',
            icon: FileText,
        },
        {
            title: 'Compliance',
            icon: Shield,
            items: [
                { title: 'Overview', href: '/compliance' },
                { title: 'UIF', href: '/compliance/uif' },
                { title: 'EMP201', href: '/compliance/emp201' },
                { title: 'IRP5', href: '/compliance/irp5' },
                { title: 'SARS Export', href: '/compliance/sars-export' },
            ],
        },
        {
            title: 'Templates',
            href: '/templates',
            icon: Palette,
        },
        {
            title: 'Businesses',
            icon: Building2,
            badge: businessesCount === 0 ? '0' : undefined,
            items: [
                { title: 'All Businesses', href: '/businesses' },
                { title: 'Create Business', href: '/businesses/create' },
            ],
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
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
