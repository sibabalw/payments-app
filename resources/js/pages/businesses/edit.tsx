import { useState, useEffect } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { MultiStepForm } from '@/components/multi-step-form';
import { LogoUpload } from '@/components/logo-upload';
import { Spinner } from '@/components/ui/spinner';
import { Mail, CheckCircle2, AlertCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Businesses', href: '/businesses' },
    { title: 'Edit', href: '#' },
];

interface Business {
    id: number;
    name: string;
    logo: string | null;
    business_type: string;
    registration_number: string;
    tax_id: string;
    email: string;
    phone: string;
    website: string;
    street_address: string;
    city: string;
    province: string;
    postal_code: string;
    country: string;
    description: string;
    contact_person_name: string;
}

interface EditBusinessProps {
    business: Business;
    pendingEmail?: string;
    otpSent?: boolean;
    emailVerified?: boolean;
    status?: string;
    error?: string;
}

function OtpVerificationForm({
    email,
    businessId,
    onCancel,
}: {
    email: string;
    businessId: number;
    onCancel: () => void;
}) {
    const [otp, setOtp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleVerify = () => {
        setError(null);

        if (otp.length !== 6 || !/^\d{6}$/.test(otp)) {
            setError('Please enter a valid 6-digit code.');
            return;
        }

        setProcessing(true);
        router.post(
            `/businesses/${businessId}/verify-email-otp`,
            { email: email.toLowerCase().trim(), otp },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setProcessing(false);
                    const pageErrors = (page as any).props?.errors;
                    if (pageErrors?.otp) {
                        setError(pageErrors.otp);
                    } else if (Object.keys(pageErrors || {}).length > 0) {
                        setError('Verification failed. Please try again.');
                    } else {
                        router.visit(`/businesses/${businessId}/edit`, { preserveScroll: true });
                    }
                },
                onError: (errors) => {
                    setProcessing(false);
                    if (errors.otp) {
                        setError(errors.otp as string);
                    } else {
                        setError('Invalid or expired OTP code. Please try again.');
                    }
                },
            }
        );
    };

    const handleOtpChange = (value: string) => {
        const digitsOnly = value.replace(/\D/g, '').slice(0, 6);
        setOtp(digitsOnly);
        if (error) {
            setError(null);
        }
    };

    return (
        <div className="space-y-4 p-4 border rounded-lg bg-muted/50">
            <div className="flex items-center gap-2 text-sm">
                <Mail className="h-4 w-4 text-primary" />
                <span className="font-medium">Email Verification Required</span>
            </div>
            <p className="text-sm text-muted-foreground">
                We've sent a 6-digit verification code to <span className="font-medium text-foreground">{email}</span>.
                Please enter it below to verify your new email address.
            </p>
            <div className="space-y-4">
                <div className="grid gap-2">
                    <Label htmlFor="otp">Verification Code</Label>
                    <Input
                        id="otp"
                        type="text"
                        value={otp}
                        onChange={(e) => handleOtpChange(e.target.value)}
                        autoFocus
                        autoComplete="one-time-code"
                        placeholder="000000"
                        maxLength={6}
                        className="text-center text-2xl font-mono tracking-widest"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                handleVerify();
                            }
                        }}
                    />
                    <InputError message={error ?? undefined} />
                </div>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={onCancel} className="flex-1">
                        Cancel
                    </Button>
                    <Button type="button" onClick={handleVerify} className="flex-1" disabled={processing || otp.length !== 6}>
                        {processing && <Spinner className="mr-2 h-4 w-4" />}
                        Verify
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function BusinessesEdit({ business, pendingEmail, otpSent, emailVerified: initialEmailVerified = false, status, error }: EditBusinessProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [emailVerified, setEmailVerified] = useState(initialEmailVerified);
    const [sendingOtp, setSendingOtp] = useState(false);
    const originalEmail = business.email;
    const emailChanged = pendingEmail && pendingEmail !== originalEmail.toLowerCase();

    // Sync emailVerified state with prop when it changes (after OTP verification)
    useEffect(() => {
        if (initialEmailVerified) {
            setEmailVerified(true);
        }
    }, [initialEmailVerified]);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: business.name,
        logo: null as File | null,
        business_type: business.business_type || '',
        registration_number: business.registration_number || '',
        tax_id: business.tax_id || '',
        email: business.email,
        phone: business.phone,
        website: business.website || '',
        street_address: business.street_address || '',
        city: business.city,
        province: business.province || '',
        postal_code: business.postal_code || '',
        country: business.country,
        description: business.description || '',
        contact_person_name: business.contact_person_name,
    });

    // Sync form email with business email when it changes (after verification updates it)
    useEffect(() => {
        if (business.email !== data.email && !otpSent) {
            setData('email', business.email);
        }
    }, [business.email]);

    const handleSendOtp = () => {
        const emailToVerify = data.email.toLowerCase().trim();
        if (emailToVerify === originalEmail.toLowerCase()) {
            return;
        }

        setSendingOtp(true);
        router.post(
            `/businesses/${business.id}/send-email-otp`,
            { email: emailToVerify },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSendingOtp(false);
                    // Reload to get updated pendingEmail and otpSent
                    router.reload({ only: ['pendingEmail', 'otpSent', 'emailVerified'] });
                },
                onError: () => {
                    setSendingOtp(false);
                },
                onFinish: () => {
                    setSendingOtp(false);
                },
            }
        );
    };

    const handleSubmit = () => {
        // Check if email changed and not verified
        if (data.email.toLowerCase() !== originalEmail.toLowerCase() && !emailVerified && !otpSent) {
            return;
        }

        post(`/businesses/${business.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const canProceed = () => {
        switch (currentStep) {
            case 0:
                return !!data.name;
            case 2:
                // Check if all required fields are filled
                const hasRequiredFields = !!(data.email && data.phone && data.contact_person_name);
                
                // If email changed, must be verified before proceeding
                const currentEmailLower = data.email.toLowerCase().trim();
                const emailChanged = currentEmailLower !== originalEmail.toLowerCase();
                
                if (emailChanged) {
                    // Email changed - must be verified
                    // Check if email matches pending email and is verified
                    const emailMatchesPending = pendingEmail && pendingEmail === currentEmailLower;
                    const isEmailVerified = emailVerified && emailMatchesPending;
                    
                    if (!isEmailVerified) {
                        return false; // Cannot proceed without verification
                    }
                    
                    return hasRequiredFields && isEmailVerified;
                }
                
                // Email not changed, just check required fields
                return hasRequiredFields;
            case 3:
                return !!(data.city && data.country);
            default:
                return true;
        }
    };

    const steps = [
        {
            title: 'Basic Information',
            description: 'Update your business details',
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
                        value={data.logo || business.logo}
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
                            <div className="mt-2 space-y-2">
                                <div className="flex gap-2">
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => {
                                            setData('email', e.target.value);
                                            setEmailVerified(false);
                                        }}
                                        required
                                        placeholder="business@example.com"
                                        className="h-12 text-base"
                                        disabled={otpSent && !emailVerified}
                                    />
                                    {data.email.toLowerCase() !== originalEmail.toLowerCase() && !otpSent && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleSendOtp}
                                            disabled={sendingOtp}
                                            className="whitespace-nowrap"
                                        >
                                            {sendingOtp && <Spinner className="mr-2 h-4 w-4" />}
                                            Send OTP
                                        </Button>
                                    )}
                                    {data.email.toLowerCase() === originalEmail.toLowerCase() && (
                                        <div className="flex items-center gap-2 px-3 text-sm text-muted-foreground">
                                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                                            <span>Current</span>
                                        </div>
                                    )}
                                </div>
                                {otpSent && pendingEmail && (
                                    <OtpVerificationForm
                                        email={pendingEmail}
                                        businessId={business.id}
                                        onCancel={() => {
                                            router.post(`/businesses/${business.id}/cancel-email-otp`, {}, {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    router.reload({ only: ['pendingEmail', 'otpSent', 'emailVerified'] });
                                                },
                                            });
                                        }}
                                    />
                                )}
                                {emailVerified && (
                                    <div className="flex items-center gap-2 text-sm text-green-600">
                                        <CheckCircle2 className="h-4 w-4" />
                                        <span>Email verified successfully</span>
                                    </div>
                                )}
                                {data.email.toLowerCase() !== originalEmail.toLowerCase() && !emailVerified && !otpSent && (
                                    <div className="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-2">
                                        <AlertCircle className="h-4 w-4" />
                                        <span>Please verify your new email address before proceeding to the next step.</span>
                                    </div>
                                )}
                            </div>
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

    const canSubmit = () => {
        // If email changed, must be verified
        if (data.email.toLowerCase() !== originalEmail.toLowerCase()) {
            return emailVerified || (otpSent && pendingEmail === data.email.toLowerCase());
        }
        return canProceed();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Business" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {status && (
                    <div className="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 text-sm text-green-800 dark:text-green-200">
                        {status}
                    </div>
                )}
                {error && (
                    <div className="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 text-sm text-red-800 dark:text-red-200">
                        {error}
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Business</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <MultiStepForm
                            steps={steps}
                            currentStep={currentStep}
                            onStepChange={setCurrentStep}
                            onSubmit={handleSubmit}
                            canProceed={canProceed()}
                            isSubmitting={processing}
                        />
                        <div className="mt-4 pt-4 border-t">
                            <Link href="/businesses">
                                <Button type="button" variant="outline" disabled={processing}>
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
