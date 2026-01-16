import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Recipients', href: '/recipients' },
];

export default function RecipientsIndex({ recipients }: any) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recipients" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Recipients</h1>
                    <Link href="/recipients/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Recipient
                        </Button>
                    </Link>
                </div>

                {recipients?.data && recipients.data.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {recipients.data.map((recipient: any) => (
                            <Card key={recipient.id}>
                                <CardHeader>
                                    <CardTitle>{recipient.name}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">{recipient.email}</p>
                                    <Link href={`/recipients/${recipient.id}/edit`} className="mt-4 inline-block">
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
                            <p className="text-muted-foreground">No recipients found.</p>
                            <Link href="/recipients/create" className="mt-4 inline-block">
                                <Button>Add your first recipient</Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
