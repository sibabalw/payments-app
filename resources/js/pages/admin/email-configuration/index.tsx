import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    Mail,
    Send,
    Settings,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Email Configuration', href: '/admin/email-configuration' },
];

interface EmailConfigurationProps {
    mailConfig: {
        default: string;
        mailer: string;
        host: string;
        port: number;
        from_address: string;
        from_name: string;
    };
}

export default function EmailConfiguration({ mailConfig }: EmailConfigurationProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const handleTestEmail = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/email-configuration/test');
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Email Configuration" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Email Configuration</h1>
                        <p className="text-sm text-muted-foreground">Manage email settings and test configuration</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Email Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Email Configuration
                            </CardTitle>
                            <CardDescription>Current email settings (read-only)</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Mailer</span>
                                <span className="font-mono">{mailConfig.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Transport</span>
                                <span className="font-mono">{mailConfig.mailer}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Host</span>
                                <span className="font-mono">{mailConfig.host}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Port</span>
                                <span className="font-mono">{mailConfig.port}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">From Address</span>
                                <span className="font-mono text-xs">{mailConfig.from_address}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">From Name</span>
                                <span className="font-mono">{mailConfig.from_name}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Test Email */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Send className="h-5 w-5" />
                                Test Email
                            </CardTitle>
                            <CardDescription>Send a test email to verify configuration</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleTestEmail} className="space-y-4">
                                <div>
                                    <Label htmlFor="email">Email Address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="test@example.com"
                                        required
                                    />
                                    {errors.email && (
                                        <p className="text-sm text-red-600 mt-1">{errors.email}</p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <Mail className="mr-2 h-4 w-4" />
                                    {processing ? 'Sending...' : 'Send Test Email'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
