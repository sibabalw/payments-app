import { send } from '@/routes/verification';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    CheckCircle2,
    Mail,
    Calendar,
    Shield,
    User,
    Sparkles,
    Edit3,
    Verified,
    Clock,
    AlertCircle,
} from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';

import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import AdminLayout from '@/layouts/admin-layout';
import AdminAccountLayout from '@/layouts/admin/account-layout';

const baseUrl = '/admin/account/profile';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Account', href: '/admin/account/profile' },
    { title: 'Profile', href: baseUrl },
];

function OtpVerificationForm({
    email,
    onCancel,
}: {
    email: string;
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
        router.post(`${baseUrl}/verify-email-otp`, { email, otp }, {
            preserveScroll: true,
            onSuccess: (page) => {
                setProcessing(false);
                const pageErrors = (page as { props?: { errors?: Record<string, string> } })?.props?.errors;
                if (pageErrors?.otp) {
                    setError(pageErrors.otp);
                } else if (pageErrors && Object.keys(pageErrors).length > 0) {
                    setError('Verification failed. Please try again.');
                } else {
                    router.visit(baseUrl, { preserveScroll: true });
                }
            },
            onError: (responseErrors: { otp?: string }) => {
                setProcessing(false);
                setError(responseErrors?.otp ?? 'Invalid or expired OTP code. Please try again.');
            },
        });
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
                        onChange={(e) => {
                            const digitsOnly = e.target.value.replace(/\D/g, '').slice(0, 6);
                            setOtp(digitsOnly);
                            if (error) setError(null);
                        }}
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

export default function AdminAccountProfile({
    mustVerifyEmail,
    status,
    pendingEmail,
    otpSent,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    pendingEmail?: string;
    otpSent?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const [emailVerified, setEmailVerified] = useState(false);
    const [sendingOtp, setSendingOtp] = useState(false);
    const [currentEmail, setCurrentEmail] = useState(auth.user.email);
    const originalEmail = auth.user.email;

    useEffect(() => {
        setCurrentEmail(pendingEmail ?? auth.user.email);
    }, [pendingEmail, auth.user.email]);

    const memberSince = auth.user.created_at
        ? new Date(auth.user.created_at).toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
          })
        : 'Unknown';

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin - Account - Profile" />

            <AdminAccountLayout>
                <div className="space-y-8 pb-8">
                    <div className="relative overflow-hidden rounded-2xl border bg-gradient-to-br from-primary/5 via-primary/3 to-transparent p-8 backdrop-blur-sm dark:from-primary/10 dark:via-primary/5">
                        <div className="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
                        <div className="relative flex flex-col items-center gap-6 md:flex-row md:items-start">
                            <Avatar className="relative h-32 w-32 overflow-hidden rounded-full border-4 border-background shadow-2xl ring-4 ring-primary/20">
                                <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                                <AvatarFallback className="rounded-full bg-gradient-to-br from-primary to-primary/60 text-3xl font-semibold text-white">
                                    {getInitials(auth.user.name)}
                                </AvatarFallback>
                            </Avatar>
                            {auth.user.email_verified_at && (
                                <div className="absolute bottom-0 right-0 rounded-full bg-green-500 p-1.5 ring-4 ring-background">
                                    <Verified className="h-4 w-4 text-white" />
                                </div>
                            )}
                            <div className="flex-1 text-center md:text-left">
                                <div className="mb-2 flex items-center justify-center gap-2 md:justify-start">
                                    <h1 className="text-3xl font-bold tracking-tight">{auth.user.name}</h1>
                                    <Sparkles className="h-5 w-5 text-primary" />
                                </div>
                                <p className="mb-4 text-lg text-muted-foreground">{auth.user.email}</p>
                                <div className="flex flex-wrap items-center justify-center gap-4 md:justify-start">
                                    <div className="flex items-center gap-2 rounded-full bg-background/80 px-4 py-2 text-sm backdrop-blur-sm">
                                        <Calendar className="h-4 w-4 text-primary" />
                                        <span className="font-medium">Joined {memberSince}</span>
                                    </div>
                                    {auth.user.two_factor_enabled && (
                                        <div className="flex items-center gap-2 rounded-full bg-background/80 px-4 py-2 text-sm backdrop-blur-sm">
                                            <Shield className="h-4 w-4 text-green-500" />
                                            <span className="font-medium">2FA Enabled</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                        <div className="border-b bg-gradient-to-r from-primary/5 to-transparent p-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <Edit3 className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <h2 className="text-xl font-semibold">Edit Profile</h2>
                                    <p className="text-sm text-muted-foreground">Update your personal information</p>
                                </div>
                            </div>
                        </div>

                        <div className="p-6">
                            <Form
                                action={baseUrl}
                                method="patch"
                                options={{ preserveScroll: true }}
                                className="space-y-6"
                            >
                                {({ processing, recentlySuccessful, errors }) => (
                                    <>
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="name" className="text-sm font-medium">
                                                    Full Name
                                                </Label>
                                                <Input
                                                    id="name"
                                                    className="h-11"
                                                    defaultValue={auth.user.name}
                                                    name="name"
                                                    required
                                                    autoComplete="name"
                                                    placeholder="Enter your full name"
                                                />
                                                <InputError message={errors.name} />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="email" className="text-sm font-medium">
                                                    Email Address
                                                </Label>
                                                <div className="space-y-2">
                                                    <div className="flex gap-2">
                                                        <Input
                                                            id="email"
                                                            type="email"
                                                            className="h-11"
                                                            value={currentEmail}
                                                            name="email"
                                                            required
                                                            autoComplete="username"
                                                            placeholder="Enter your email"
                                                            onChange={(e) => {
                                                                setCurrentEmail(e.target.value);
                                                                setEmailVerified(false);
                                                            }}
                                                            disabled={otpSent && !emailVerified}
                                                        />
                                                        {currentEmail.toLowerCase() !== originalEmail.toLowerCase() && !otpSent && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                onClick={() => {
                                                                    setSendingOtp(true);
                                                                    router.post(`${baseUrl}/send-email-otp`, { email: currentEmail }, {
                                                                        preserveScroll: true,
                                                                        onFinish: () => setSendingOtp(false),
                                                                    });
                                                                }}
                                                                disabled={sendingOtp}
                                                                className="whitespace-nowrap"
                                                            >
                                                                {sendingOtp && <Spinner className="mr-2 h-4 w-4" />}
                                                                Send OTP
                                                            </Button>
                                                        )}
                                                        {currentEmail.toLowerCase() === originalEmail.toLowerCase() && (
                                                            <div className="flex items-center gap-2 px-3 text-sm text-muted-foreground">
                                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                                <span>Current</span>
                                                            </div>
                                                        )}
                                                    </div>
                                                    {otpSent && pendingEmail && (
                                                        <OtpVerificationForm
                                                            email={pendingEmail}
                                                            onCancel={() => {
                                                                setCurrentEmail(originalEmail);
                                                                setEmailVerified(false);
                                                                router.post(`${baseUrl}/cancel-email-otp`, {}, {
                                                                    preserveScroll: true,
                                                                    onSuccess: () => router.reload(),
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
                                                    {currentEmail.toLowerCase() !== originalEmail.toLowerCase() && !emailVerified && !otpSent && (
                                                        <div className="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-2">
                                                            <AlertCircle className="h-4 w-4" />
                                                            <span>Please verify your new email address before saving changes.</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <InputError message={errors.email} />
                                            </div>
                                        </div>

                                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                                            <div className="rounded-xl border border-amber-200/50 bg-gradient-to-r from-amber-50/50 to-amber-50/30 p-5 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-amber-950/10">
                                                <div className="flex items-start gap-3">
                                                    <AlertCircle className="mt-0.5 h-5 w-5 text-amber-600 dark:text-amber-400" />
                                                    <div className="flex-1">
                                                        <p className="mb-2 text-sm font-medium text-amber-900 dark:text-amber-100">
                                                            Email verification required
                                                        </p>
                                                        <Link
                                                            href={send()}
                                                            as="button"
                                                            className="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
                                                        >
                                                            <Mail className="h-4 w-4" />
                                                            Resend verification email
                                                        </Link>
                                                    </div>
                                                </div>
                                                {status === 'verification-link-sent' && (
                                                    <div className="mt-4 flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950/30 dark:text-green-200">
                                                        <CheckCircle2 className="h-4 w-4" />
                                                        A new verification link has been sent to your email address.
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        <div className="flex items-center justify-between border-t pt-6">
                                            <Transition
                                                show={recentlySuccessful}
                                                enter="transition ease-in-out"
                                                enterFrom="opacity-0 translate-x-2"
                                                enterTo="opacity-100 translate-x-0"
                                                leave="transition ease-in-out"
                                                leaveFrom="opacity-100 translate-x-0"
                                                leaveTo="opacity-0 translate-x-2"
                                            >
                                                <div className="flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2 text-sm font-medium text-green-700 dark:bg-green-950/30 dark:text-green-300">
                                                    <CheckCircle2 className="h-4 w-4" />
                                                    Changes saved successfully
                                                </div>
                                            </Transition>

                                            <Button
                                                disabled={processing || (currentEmail.toLowerCase() !== originalEmail.toLowerCase() && !emailVerified && !otpSent)}
                                                size="lg"
                                                className="min-w-[140px]"
                                            >
                                                {processing ? (
                                                    <>
                                                        <span className="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                                        Saving...
                                                    </>
                                                ) : (
                                                    <>
                                                        <CheckCircle2 className="mr-2 h-4 w-4" />
                                                        Save Changes
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>
                    </div>

                    <DeleteUser formAction="/admin/account/profile" />
                </div>
            </AdminAccountLayout>
        </AdminLayout>
    );
}
