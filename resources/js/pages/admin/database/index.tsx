import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    Database as DatabaseIcon,
    RefreshCw,
    Settings,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Database Management', href: '/admin/database' },
];

interface DatabaseProps {
    dbConfig: {
        default: string;
        driver: string;
        database: string;
        host: string;
    };
    dbSize: string | null;
    tableCount: number;
}

export default function Database({ dbConfig, dbSize, tableCount }: DatabaseProps) {
    const [migrateDialogOpen, setMigrateDialogOpen] = useState(false);
    const [optimizeDialogOpen, setOptimizeDialogOpen] = useState(false);

    const handleMigrate = () => {
        router.post('/admin/database/migrate', {}, {
            onSuccess: () => {
                setMigrateDialogOpen(false);
            },
        });
    };

    const handleOptimize = () => {
        router.post('/admin/database/optimize', {}, {
            onSuccess: () => {
                setOptimizeDialogOpen(false);
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Database Management" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Database Management</h1>
                        <p className="text-sm text-muted-foreground">Manage database operations</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Database Configuration */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DatabaseIcon className="h-5 w-5" />
                                Database Configuration
                            </CardTitle>
                            <CardDescription>Connection settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Default Connection</span>
                                <span className="font-mono">{dbConfig.default}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Driver</span>
                                <span className="font-mono">{dbConfig.driver}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Database</span>
                                <span className="font-mono">{dbConfig.database}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Host</span>
                                <span className="font-mono">{dbConfig.host}</span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Database Statistics */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Database Statistics
                            </CardTitle>
                            <CardDescription>Current database information</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {dbSize && (
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Database Size</span>
                                    <span className="font-mono">{dbSize}</span>
                                </div>
                            )}
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Total Tables</span>
                                <span className="font-mono">{tableCount}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Database Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Database Actions</CardTitle>
                        <CardDescription>Perform database operations</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between p-4 border rounded-lg">
                            <div>
                                <p className="font-medium mb-1">Run Migrations</p>
                                <p className="text-sm text-muted-foreground">
                                    Execute pending database migrations. This will update your database schema.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setMigrateDialogOpen(true)}
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Run Migrations
                            </Button>
                        </div>

                        <div className="flex items-center justify-between p-4 border rounded-lg">
                            <div>
                                <p className="font-medium mb-1">Optimize Database</p>
                                <p className="text-sm text-muted-foreground">
                                    Clear caches and optimize database performance. This may take a few moments.
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setOptimizeDialogOpen(true)}
                            >
                                <Settings className="mr-2 h-4 w-4" />
                                Optimize
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationDialog
                    open={migrateDialogOpen}
                    onOpenChange={setMigrateDialogOpen}
                    onConfirm={handleMigrate}
                    title="Run Database Migrations"
                    description="Are you sure you want to run database migrations? This will update your database schema."
                    confirmText="Run Migrations"
                    variant="default"
                />

                <ConfirmationDialog
                    open={optimizeDialogOpen}
                    onOpenChange={setOptimizeDialogOpen}
                    onConfirm={handleOptimize}
                    title="Optimize Database"
                    description="This will clear caches and optimize database performance. The operation may take a few moments."
                    confirmText="Optimize"
                    variant="default"
                />
            </div>
        </AdminLayout>
    );
}
