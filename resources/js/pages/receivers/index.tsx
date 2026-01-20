import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receivers', href: '/receivers' },
];

export default function ReceiversIndex({ receivers }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Receivers" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Receivers</h1>
                    <Link href="/receivers/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Receiver
                        </Button>
                    </Link>
                </div>

                {receivers?.data && receivers.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {receivers.data.map((receiver: any) => (
                            <Card key={receiver.id}>
                                <CardHeader>
                                    <CardTitle>{receiver.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">{receiver.email}</p>
                                    <Link href={`/receivers/${receiver.id}/edit`} className="mt-4 inline-block">
                                        <Button variant="outline" size="sm">
                                            Edit
                                        </Button>
                                    </Link>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No receivers found.</p>
                            <Link href="/receivers/create" className="mt-4 inline-block">
                                <Button>Add your first receiver</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
