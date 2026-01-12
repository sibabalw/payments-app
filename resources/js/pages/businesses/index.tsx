import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, XCircle } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Businesses', href: '/businesses' },
];

export default function BusinessesIndex({ businesses }: any) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        business_type: '',
        registration_number: '',
        tax_id: '',
        email: '',
        phone: '',
        website: '',
        street_address: '',
        city: '',
        postal_code: '',
        country: '',
        description: '',
        contact_person_name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/businesses');
    };

    const switchBusiness = (id: number) => {
        router.post(`/businesses/${id}/switch`);
    };

    const updateBusinessStatus = (businessId: number, status: string, reason?: string) => {
        router.post(`/businesses/${businessId}/status`, {
            status,
            status_reason: reason || '',
        });
    };

    const getStatusBadge = (status: string) => {
        const statusConfig = {
            active: { label: 'Active', variant: 'default' as const, icon: CheckCircle2, className: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
            suspended: { label: 'Suspended', variant: 'outline' as const, icon: AlertCircle, className: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' },
            banned: { label: 'Banned', variant: 'destructive' as const, icon: XCircle, className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' },
        };

        const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.active;
        const Icon = config.icon;

        return (
            <Badge className={config.className}>
                <Icon className="mr-1 h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Businesses" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <h1 className="text-2xl font-bold">Businesses</h1>

                <Card>
                    <CardHeader>
                        <CardTitle>Create New Business</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Basic Information</h3>
                                
                                <div>
                                    <Label htmlFor="name">Business Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                        placeholder="Enter business name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div>
                                    <Label htmlFor="business_type">Business Type</Label>
                                    <Select
                                        value={data.business_type}
                                        onValueChange={(value) => setData('business_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select business type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="small_business">Small Business</SelectItem>
                                            <SelectItem value="medium_business">Medium Business</SelectItem>
                                            <SelectItem value="large_business">Large Business</SelectItem>
                                            <SelectItem value="sole_proprietorship">Sole Proprietorship</SelectItem>
                                            <SelectItem value="partnership">Partnership</SelectItem>
                                            <SelectItem value="corporation">Corporation</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.business_type} />
                                </div>

                                <div>
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        className="flex min-h-[100px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Brief description of your business"
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>

                            {/* Registration & Tax Information */}
                            <div className="space-y-4 border-t pt-6">
                                <h3 className="text-lg font-semibold">Registration & Tax Information</h3>
                                
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="registration_number">Registration Number</Label>
                                        <Input
                                            id="registration_number"
                                            value={data.registration_number}
                                            onChange={(e) => setData('registration_number', e.target.value)}
                                            placeholder="Company registration number"
                                        />
                                        <InputError message={errors.registration_number} />
                                    </div>

                                    <div>
                                        <Label htmlFor="tax_id">Tax ID / VAT Number</Label>
                                        <Input
                                            id="tax_id"
                                            value={data.tax_id}
                                            onChange={(e) => setData('tax_id', e.target.value)}
                                            placeholder="Tax identification number"
                                        />
                                        <InputError message={errors.tax_id} />
                                    </div>
                                </div>
                            </div>

                            {/* Contact Information */}
                            <div className="space-y-4 border-t pt-6">
                                <h3 className="text-lg font-semibold">Contact Information</h3>
                                
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="business@example.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div>
                                        <Label htmlFor="phone">Phone</Label>
                                        <Input
                                            id="phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            placeholder="+27 12 345 6789"
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="website">Website</Label>
                                    <Input
                                        id="website"
                                        type="url"
                                        value={data.website}
                                        onChange={(e) => setData('website', e.target.value)}
                                        placeholder="https://www.example.com"
                                    />
                                    <InputError message={errors.website} />
                                </div>

                                <div>
                                    <Label htmlFor="contact_person_name">Contact Person Name</Label>
                                    <Input
                                        id="contact_person_name"
                                        value={data.contact_person_name}
                                        onChange={(e) => setData('contact_person_name', e.target.value)}
                                        placeholder="Primary contact person"
                                    />
                                    <InputError message={errors.contact_person_name} />
                                </div>
                            </div>

                            {/* Address Information */}
                            <div className="space-y-4 border-t pt-6">
                                <h3 className="text-lg font-semibold">Address Information</h3>
                                
                                <div>
                                    <Label htmlFor="street_address">Street Address</Label>
                                    <Input
                                        id="street_address"
                                        value={data.street_address}
                                        onChange={(e) => setData('street_address', e.target.value)}
                                        placeholder="123 Main Street"
                                    />
                                    <InputError message={errors.street_address} />
                                </div>

                                <div className="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            value={data.city}
                                            onChange={(e) => setData('city', e.target.value)}
                                            placeholder="City"
                                        />
                                        <InputError message={errors.city} />
                                    </div>

                                    <div>
                                        <Label htmlFor="postal_code">Postal Code</Label>
                                        <Input
                                            id="postal_code"
                                            value={data.postal_code}
                                            onChange={(e) => setData('postal_code', e.target.value)}
                                            placeholder="0000"
                                        />
                                        <InputError message={errors.postal_code} />
                                    </div>

                                    <div>
                                        <Label htmlFor="country">Country</Label>
                                        <Input
                                            id="country"
                                            value={data.country}
                                            onChange={(e) => setData('country', e.target.value)}
                                            placeholder="South Africa"
                                        />
                                        <InputError message={errors.country} />
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Create Business
                                </Button>
                                <Link href="/businesses">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {businesses?.map((business: any) => (
                        <Card key={business.id} className={business.status !== 'active' ? 'opacity-75' : ''}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>{business.name}</CardTitle>
                                    {getStatusBadge(business.status || 'active')}
                                </div>
                                {business.status_reason && (
                                    <p className="text-xs text-muted-foreground mt-2">
                                        Reason: {business.status_reason}
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {business.status === 'active' ? (
                                    <Button 
                                        onClick={() => switchBusiness(business.id)} 
                                        variant="outline" 
                                        size="sm"
                                        className="w-full"
                                    >
                                        Switch to this business
                                    </Button>
                                ) : (
                                    <p className="text-sm text-muted-foreground text-center py-2">
                                        Business is {business.status}. Cannot switch.
                                    </p>
                                )}
                                
                                {/* Admin Status Controls */}
                                <div className="border-t pt-3 space-y-2">
                                    <Label className="text-xs text-muted-foreground">Admin: Change Status</Label>
                                    <div className="flex gap-1">
                                        <Button
                                            onClick={() => updateBusinessStatus(business.id, 'active')}
                                            variant={business.status === 'active' ? 'default' : 'outline'}
                                            size="sm"
                                            className="flex-1 text-xs"
                                            disabled={business.status === 'active'}
                                        >
                                            Activate
                                        </Button>
                                        <Button
                                            onClick={() => updateBusinessStatus(business.id, 'suspended', 'Suspended by admin')}
                                            variant={business.status === 'suspended' ? 'default' : 'outline'}
                                            size="sm"
                                            className="flex-1 text-xs"
                                            disabled={business.status === 'suspended'}
                                        >
                                            Suspend
                                        </Button>
                                        <Button
                                            onClick={() => updateBusinessStatus(business.id, 'banned', 'Banned for fraud')}
                                            variant={business.status === 'banned' ? 'default' : 'outline'}
                                            size="sm"
                                            className="flex-1 text-xs"
                                            disabled={business.status === 'banned'}
                                        >
                                            Ban
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
