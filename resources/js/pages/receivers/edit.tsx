import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Receivers', href: '/receivers' },
    { title: 'Edit', href: '#' },
];

export default function ReceiversEdit({ receiver, businesses }: any) {
    const { data, setData, put, processing, errors } = useForm({
        business_id: receiver.business_id,
        name: receiver.name,
        email: receiver.email || '',
        bank_account_details: receiver.bank_account_details || {},
        payout_method: receiver.payout_method || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/receivers/${receiver.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Receiver" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Receiver</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="business_id">Business</Label>
                                <Select
                                    value={String(data.business_id)}
                                    onValueChange={(value) => setData('business_id', Number(value))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {businesses.map((business: any) => (
                                            <SelectItem key={business.id} value={String(business.id)}>
                                                {business.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.business_id} />
                            </div>

                            <div>
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div>
                                <Label htmlFor="payout_method">Payout Method</Label>
                                <Input
                                    id="payout_method"
                                    value={data.payout_method}
                                    onChange={(e) => setData('payout_method', e.target.value)}
                                />
                                <InputError message={errors.payout_method} />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    Update Receiver
                                </Button>
                                <Link href="/receivers">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
