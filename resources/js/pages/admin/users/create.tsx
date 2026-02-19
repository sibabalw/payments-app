import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, UserPlus } from 'lucide-react';
import { Form } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: 'Add Admin', href: '/admin/users/create' },
];

export default function AdminUsersCreate() {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Add Administrator" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Add Administrator</h1>
                        <p className="text-sm text-muted-foreground">
                            Create a new admin user. They will receive an email notification.
                        </p>
                    </div>
                    <Link href="/admin/users">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Users
                        </Button>
                    </Link>
                </div>

                <div className="max-w-xl">
                    <Form
                        action="/admin/users"
                        method="post"
                        className="space-y-6 rounded-xl border bg-card p-6 shadow-sm"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            type="text"
                                            required
                                            autoComplete="name"
                                            placeholder="Full name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="password">Password</Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            placeholder="••••••••"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="password_confirmation">Confirm Password</Label>
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                            placeholder="••••••••"
                                        />
                                    </div>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing}>
                                        <UserPlus className="mr-2 h-4 w-4" />
                                        {processing ? 'Adding...' : 'Add Administrator'}
                                    </Button>
                                    <Link href="/admin/users">
                                        <Button type="button" variant="outline">
                                            Cancel
                                        </Button>
                                    </Link>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </AdminLayout>
    );
}
