import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface SignInProps {
    status?: string;
    error?: string;
    otpSent?: boolean;
    email?: string;
    errors?: {
        email?: string;
        otp?: string;
    };
}

export default function EmployeeSignIn({
    status,
    error,
    otpSent = false,
    email: initialEmail = '',
    errors: propErrors = {},
}: SignInProps) {
    const { errors: pageErrors } = usePage().props as { errors?: { email?: string; otp?: string } };
    const [email, setEmail] = useState(initialEmail);
    const [processing, setProcessing] = useState(false);
    
    // Combine prop errors with page errors (page errors take priority)
    const errors = pageErrors || propErrors;

    const handleSendOtp = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            '/employee/send-otp',
            { email },
            {
                onSuccess: () => {
                    // Email will be set in session by backend
                    setProcessing(false);
                },
                onError: () => {
                    setProcessing(false);
                },
                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    const handleVerifyOtp = (otp: string, setProcessing: (value: boolean) => void) => {
        setProcessing(true);
        router.post(
            '/employee/verify-otp',
            {
                email,
                otp,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <AuthLayout
            title="Employee Sign-In"
            description="Enter your email to receive a verification code"
        >
            <Head title="Employee Sign-In" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {error && (
                <div className="mb-4 text-center text-sm font-medium text-red-600">
                    {error}
                </div>
            )}

            {!otpSent ? (
                <div className="space-y-6">
                    <form onSubmit={handleSendOtp} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="email@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button type="submit" className="w-full" disabled={processing}>
                            {processing && <Spinner />}
                            Send Verification Code
                        </Button>
                    </form>

                    <div className="text-center text-sm text-muted-foreground">
                        <span>Are you a business owner? </span>
                        <TextLink href="/employees">
                            View employees
                        </TextLink>
                    </div>
                </div>
            ) : (
                <OtpForm email={email} onVerify={(otp, setProcessing) => handleVerifyOtp(otp, setProcessing)} errors={errors} />
            )}
        </AuthLayout>
    );
}

function OtpForm({
    email,
    onVerify,
    errors: propErrors = {},
}: {
    email: string;
    onVerify: (otp: string, setProcessing: (value: boolean) => void) => void;
    errors?: { otp?: string };
}) {
    const [otp, setOtp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{ otp?: string }>({});
    
    // Combine prop errors with local errors (prop errors take priority)
    const displayErrors = propErrors.otp ? propErrors : errors;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});

        if (otp.length !== 6 || !/^\d{6}$/.test(otp)) {
            setErrors({ otp: 'Please enter a valid 6-digit code.' });
            return;
        }

        onVerify(otp, setProcessing);
    };

    const handleOtpChange = (value: string) => {
        // Only allow digits and limit to 6 characters
        const digitsOnly = value.replace(/\D/g, '').slice(0, 6);
        setOtp(digitsOnly);
        // Clear errors when user starts typing
        if (errors.otp || propErrors.otp) {
            setErrors({});
        }
    };

    return (
        <div className="space-y-6">
            <div className="text-center text-sm text-muted-foreground">
                We've sent a 6-digit verification code to{' '}
                <span className="font-medium text-foreground">{email}</span>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid gap-2">
                    <Label htmlFor="otp">Verification Code</Label>
                    <Input
                        id="otp"
                        type="text"
                        name="otp"
                        value={otp}
                        onChange={(e) => handleOtpChange(e.target.value)}
                        required
                        autoFocus
                        autoComplete="one-time-code"
                        placeholder="000000"
                        maxLength={6}
                        className="text-center text-2xl font-mono tracking-widest"
                    />
                    <InputError message={displayErrors.otp} />
                    <p className="text-xs text-muted-foreground">
                        Enter the 6-digit code from your email
                    </p>
                </div>

                <Button
                    type="submit"
                    className="w-full"
                    disabled={processing || otp.length !== 6}
                >
                    {processing && <Spinner />}
                    Verify Code
                </Button>
            </form>

            <div className="text-center text-sm text-muted-foreground">
                Didn't receive the code?{' '}
                <button
                    type="button"
                    onClick={() => router.reload()}
                    className="text-primary hover:underline"
                >
                    Request a new one
                </button>
            </div>
        </div>
    );
}
