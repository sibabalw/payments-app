import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    badge?: string | number | null;
    /** Child links shown in a collapsible section (closed by default). */
    items?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    businessesCount?: number;
    currentBusiness?: { id: number; name: string; status: string; logo?: string | null } | null;
    userBusinesses?: Array<{ id: number; name: string; status: string; logo?: string | null }>;
    hasCompletedDashboardTour?: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    is_admin?: boolean;
    has_completed_dashboard_tour?: boolean;
    dashboard_tour_completed_at?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface PaymentSchedule {
    id: number;
    business_id: number;
    type: 'generic' | 'payroll';
    name: string;
    frequency: string;
    amount: string;
    currency: string;
    status: 'active' | 'paused' | 'cancelled';
    schedule_type: 'one_time' | 'recurring';
    next_run_at: string | null;
    last_run_at: string | null;
    created_at: string;
    updated_at: string;
    receivers?: Array<{ id: number; name: string }>;
    recipients?: Array<{ id: number; name: string }>;
}

export interface Recipient {
    id: number;
    business_id: number;
    name: string;
    email: string | null;
    bank_account_details: Record<string, any> | null;
    payout_method: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface Employee {
    id: number;
    business_id: number;
    name: string;
    email: string | null;
    id_number: string | null;
    tax_number: string | null;
    employment_type: 'full_time' | 'part_time' | 'contract';
    department: string | null;
    start_date: string | null;
    gross_salary: string;
    bank_account_details: Record<string, any> | null;
    tax_status: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface PayrollSchedule {
    id: number;
    business_id: number;
    name: string;
    frequency: string;
    schedule_type: 'one_time' | 'recurring';
    status: 'active' | 'paused' | 'cancelled';
    next_run_at: string | null;
    last_run_at: string | null;
    created_at: string;
    updated_at: string;
    employees?: Array<{ id: number; name: string; gross_salary: string }>;
}

export interface PayrollJob {
    id: number;
    payroll_schedule_id: number;
    employee_id: number;
    gross_salary: string;
    paye_amount: string;
    uif_amount: string;
    sdl_amount: string;
    net_salary: string;
    currency: string;
    status: 'pending' | 'processing' | 'succeeded' | 'failed';
    error_message: string | null;
    processed_at: string | null;
    transaction_id: string | null;
    fee: string | null;
    escrow_deposit_id: number | null;
    fee_released_manually_at: string | null;
    funds_returned_manually_at: string | null;
    released_by: number | null;
    pay_period_start: string | null;
    pay_period_end: string | null;
    created_at: string;
    updated_at: string;
    employee?: Employee;
    payrollSchedule?: PayrollSchedule;
}

export interface PaymentJob {
    id: number;
    payment_schedule_id: number;
    receiver_id?: number;
    recipient_id?: number;
    amount: string;
    currency: string;
    status: 'pending' | 'processing' | 'succeeded' | 'failed';
    error_message: string | null;
    processed_at: string | null;
    transaction_id: string | null;
    fee: string | null;
    escrow_deposit_id: number | null;
    created_at: string;
    updated_at: string;
    receiver?: { id: number; name: string };
    recipient?: Recipient;
}
