import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    FileText,
    Trash2,
    RefreshCw,
} from 'lucide-react';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Logs', href: '/admin/logs' },
];

interface LogsProps {
    logExists: boolean;
    logSize: string;
    logContent: string[];
    lines: number;
}

export default function Logs({ logExists, logSize, logContent, lines }: LogsProps) {
    const [clearDialogOpen, setClearDialogOpen] = useState(false);
    const [selectedLines, setSelectedLines] = useState(lines.toString());

    const handleLinesChange = (value: string) => {
        setSelectedLines(value);
        router.get('/admin/logs', { lines: parseInt(value) }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClear = () => {
        router.post('/admin/logs/clear', {}, {
            onSuccess: () => {
                setClearDialogOpen(false);
                router.reload();
            },
        });
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Logs" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Application Logs</h1>
                        <p className="text-sm text-muted-foreground">View and manage application logs</p>
                    </div>
                    <Link href="/admin">
                        <Button variant="outline">
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back to Dashboard
                        </Button>
                    </Link>
                </div>

                {!logExists ? (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <FileText className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground">No log file found</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Log File
                                    </CardTitle>
                                    <CardDescription>
                                        Size: {logSize} | Showing last {logContent.length} lines
                                    </CardDescription>
                                </div>
                                <div className="flex gap-2">
                                    <Select value={selectedLines} onValueChange={handleLinesChange}>
                                        <SelectTrigger className="w-[120px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="50">50 lines</SelectItem>
                                            <SelectItem value="100">100 lines</SelectItem>
                                            <SelectItem value="200">200 lines</SelectItem>
                                            <SelectItem value="500">500 lines</SelectItem>
                                            <SelectItem value="1000">1000 lines</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        variant="outline"
                                        onClick={() => router.reload()}
                                    >
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Refresh
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => setClearDialogOpen(true)}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Clear Logs
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="bg-slate-950 dark:bg-slate-900 rounded-md p-4 font-mono text-xs overflow-x-auto max-h-[600px] overflow-y-auto">
                                    {logContent.length > 0 ? (
                                        logContent.map((line, index) => (
                                            <div key={index} className="text-slate-300 whitespace-pre-wrap break-words">
                                                {line}
                                            </div>
                                        ))
                                    ) : (
                                        <div className="text-slate-500">No log entries</div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}

                <ConfirmationDialog
                    open={clearDialogOpen}
                    onOpenChange={setClearDialogOpen}
                    onConfirm={handleClear}
                    title="Clear Log File"
                    description="Are you sure you want to clear the log file? This action cannot be undone."
                    confirmText="Clear"
                    variant="destructive"
                />
            </div>
        </AdminLayout>
    );
}
