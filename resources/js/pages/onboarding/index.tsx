import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Sparkles, Zap, TrendingUp, Shield } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { MultiStepForm } from '@/components/multi-step-form';
import { LogoUpload } from '@/components/logo-upload';

export default function Onboarding() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        logo: null as File | null,
        business_type: '',
        registration_number: '',
        tax_id: '',
        email: '',
        phone: '',
        website: '',
        street_address: '',
        city: '',
        province: '',
        postal_code: '',
        country: '',
        description: '',
        contact_person_name: '',
    });

    const [currentStep, setCurrentStep] = useState(0);

    const handleSubmit = () => {
        post('/onboarding', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const handleSkip = () => {
        router.post('/onboarding/skip');
    };

    const canProceed = () => {
        switch (currentStep) {
            case 0:
                return !!data.name; // Name is required
            case 2:
                return !!(data.email && data.phone && data.contact_person_name); // Contact info required
            case 3:
                return !!(data.city && data.country); // Location required
            default:
                return true; // Other steps are optional
        }
    };

    const steps = [
        {
            title: 'Basic Information',
            description: 'Tell us about your business',
            content: (
                <div className="space-y-6">
                    <div>
                        <Label htmlFor="name" className="text-base font-semibold">
                            Business Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="Enter your business name"
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="business_type" className="text-base font-semibold">
                            Business Type
                        </Label>
                        <Select
                            value={data.business_type}
                            onValueChange={(value) => setData('business_type', value)}
                        >
                            <SelectTrigger className="mt-2 h-12 text-base">
                                <SelectValue placeholder="Select business type (optional)" />
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
                        <InputError message={errors.business_type} className="mt-1" />
                    </div>

                    <LogoUpload
                        value={data.logo}
                        onChange={(file) => setData('logo', file)}
                        error={errors.logo}
                    />
                </div>
            ),
        },
        {
            title: 'Registration & Tax Information',
            description: 'Legal and tax details (optional)',
            content: (
                <div className="space-y-6">
                    <div>
                        <Label htmlFor="registration_number">Registration Number</Label>
                        <Input
                            id="registration_number"
                            value={data.registration_number}
                            onChange={(e) => setData('registration_number', e.target.value)}
                            placeholder="Company registration number"
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.registration_number} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="tax_id">Tax ID / VAT Number</Label>
                        <Input
                            id="tax_id"
                            value={data.tax_id}
                            onChange={(e) => setData('tax_id', e.target.value)}
                            placeholder="Tax identification number"
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.tax_id} className="mt-1" />
                    </div>
                </div>
            ),
        },
        {
            title: 'Contact Information',
            description: 'How can we reach you?',
            content: (
                <div className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="email" className="text-base font-semibold">
                                Email <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                placeholder="business@example.com"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.email} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="phone" className="text-base font-semibold">
                                Phone <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="phone"
                                type="tel"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                required
                                placeholder="+27 12 345 6789"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.phone} className="mt-1" />
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
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.website} className="mt-1" />
                    </div>

                    <div>
                        <Label htmlFor="contact_person_name" className="text-base font-semibold">
                            Contact Person Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="contact_person_name"
                            value={data.contact_person_name}
                            onChange={(e) => setData('contact_person_name', e.target.value)}
                            required
                            placeholder="Primary contact person"
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.contact_person_name} className="mt-1" />
                    </div>
                </div>
            ),
        },
        {
            title: 'Address Information',
            description: 'Business location details',
            content: (
                <div className="space-y-6">
                    <div>
                        <Label htmlFor="street_address">Street Address</Label>
                        <Input
                            id="street_address"
                            value={data.street_address}
                            onChange={(e) => setData('street_address', e.target.value)}
                            placeholder="123 Main Street"
                            className="mt-2 h-12 text-base"
                        />
                        <InputError message={errors.street_address} className="mt-1" />
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="city" className="text-base font-semibold">
                                City <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="city"
                                value={data.city}
                                onChange={(e) => setData('city', e.target.value)}
                                required
                                placeholder="City"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.city} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="province">Province/State</Label>
                            <Input
                                id="province"
                                value={data.province}
                                onChange={(e) => setData('province', e.target.value)}
                                placeholder="e.g., Gauteng, Western Cape"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.province} className="mt-1" />
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="postal_code">Postal Code</Label>
                            <Input
                                id="postal_code"
                                value={data.postal_code}
                                onChange={(e) => setData('postal_code', e.target.value)}
                                placeholder="0000"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.postal_code} className="mt-1" />
                        </div>

                        <div>
                            <Label htmlFor="country" className="text-base font-semibold">
                                Country <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="country"
                                value={data.country}
                                onChange={(e) => setData('country', e.target.value)}
                                required
                                placeholder="South Africa"
                                className="mt-2 h-12 text-base"
                            />
                            <InputError message={errors.country} className="mt-1" />
                        </div>
                    </div>
                </div>
            ),
        },
        {
            title: 'Description',
            description: 'Tell us more about your business',
            content: (
                <div className="space-y-6">
                    <div>
                        <Label htmlFor="description">Description</Label>
                        <textarea
                            id="description"
                            className="mt-2 flex min-h-[200px] w-full rounded-md border border-input bg-background px-4 py-3 text-base shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 resize-none"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Brief description of your business (optional)"
                        />
                        <InputError message={errors.description} className="mt-1" />
                    </div>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Welcome - Get Started" />
            <div className="min-h-screen bg-gradient-to-br from-primary/5 via-background to-primary/10">
                {/* Background decoration */}
                <div className="absolute inset-0 overflow-hidden pointer-events-none">
                    <div className="absolute -top-40 -right-40 w-80 h-80 bg-primary/10 rounded-full blur-3xl"></div>
                    <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-primary/10 rounded-full blur-3xl"></div>
                </div>

                <div className="relative min-h-screen flex items-center justify-center p-4 py-12">
                    <div className="w-full max-w-4xl">
                        {/* Header Section */}
                        <div className="text-center mb-8 space-y-4">
                            <div className="flex justify-center mb-4">
                                <div className="relative">
                                    <div className="absolute inset-0 bg-primary/20 rounded-full blur-xl"></div>
                                    <div className="relative rounded-full bg-gradient-to-br from-primary to-primary/80 p-6 shadow-lg">
                                        <Sparkles className="h-10 w-10 text-white" />
                                    </div>
                                </div>
                            </div>
                            <h1 className="text-4xl md:text-5xl font-bold tracking-tight bg-gradient-to-r from-primary to-primary/60 bg-clip-text text-transparent">
                                Welcome to SwiftPay!
                            </h1>
                            <p className="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto">
                                Let's set up your first business profile. This will only take a minute, and you can always add more later.
                            </p>
                        </div>

                        {/* Features Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                            <div className="flex items-center gap-3 p-4 rounded-lg bg-background/50 backdrop-blur-sm border border-border/50">
                                <div className="rounded-lg bg-primary/10 p-2">
                                    <Zap className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="font-semibold text-sm">Fast Setup</p>
                                    <p className="text-xs text-muted-foreground">Get started in minutes</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 p-4 rounded-lg bg-background/50 backdrop-blur-sm border border-border/50">
                                <div className="rounded-lg bg-primary/10 p-2">
                                    <TrendingUp className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="font-semibold text-sm">Easy Management</p>
                                    <p className="text-xs text-muted-foreground">Streamline your payments</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 p-4 rounded-lg bg-background/50 backdrop-blur-sm border border-border/50">
                                <div className="rounded-lg bg-primary/10 p-2">
                                    <Shield className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="font-semibold text-sm">Secure & Reliable</p>
                                    <p className="text-xs text-muted-foreground">Bank-level security</p>
                                </div>
                            </div>
                        </div>

                        {/* Main Form Card */}
                        <div className="bg-background/80 backdrop-blur-xl rounded-2xl shadow-2xl border border-border/50 overflow-hidden">
                            <div className="bg-gradient-to-r from-primary/10 to-primary/5 p-6 border-b border-border/50">
                                <h2 className="text-2xl font-bold">Add Your Business</h2>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Create your first business profile to start managing payments
                                </p>
                            </div>

                            <div className="p-6 md:p-8">
                                <MultiStepForm
                                    steps={steps}
                                    currentStep={currentStep}
                                    onStepChange={setCurrentStep}
                                    onSubmit={handleSubmit}
                                    canProceed={canProceed()}
                                    showSkip={true}
                                    onSkip={handleSkip}
                                    isSubmitting={processing}
                                />
                            </div>
                        </div>

                        {/* Footer Note */}
                        <div className="text-center mt-6">
                            <p className="text-sm text-muted-foreground">
                                Don't worry, you can always add or manage businesses from your dashboard later.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
