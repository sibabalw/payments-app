import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import AdminLayout from '@/layouts/admin-layout';
import AdminAccountLayout from '@/layouts/admin/account-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Account', href: '/admin/account/profile' },
    { title: 'Appearance', href: '/admin/account/appearance' },
];

export default function AdminAccountAppearance() {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Account - Appearance" />

            <AdminAccountLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Appearance settings"
                        description="Update your account's appearance settings"
                    />
                    <AppearanceTabs />
                </div>
            </AdminAccountLayout>
        </AdminLayout>
    );
}
