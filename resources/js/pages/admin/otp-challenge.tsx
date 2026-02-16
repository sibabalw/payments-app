import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { verify, resend } from '@/routes/admin/otp';
import { Form, Head, router, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';

const OTP_LENGTH = 6;

interface AdminOtpChallengeProps {
    email: string;
    status?: string;
    error?: string;
    errors?: { otp?: string };
}

export default function AdminOtpChallenge({
    email,
    status,
    error,
    errors: propErrors = {},
}: AdminOtpChallengeProps) {
    const [code, setCode] = useState('');
    const { errors: pageErrors } = usePage().props as { errors?: { otp?: string } };
    const errors = pageErrors ?? propErrors;

    return (
        <AuthLayout
            title="Admin verification"
            description={`Enter the 6-digit code we sent to ${email}`}
        >
            <Head title="Admin Verification" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {status}
                </div>
            )}

            {error && (
                <div className="mb-4 text-center text-sm font-medium text-red-600 dark:text-red-400">
                    {error}
                </div>
            )}

            <div className="space-y-6">
                <Form
                    action={verify.url()}
                    method="post"
                    className="space-y-4"
                    resetOnError
                >
                    {({ processing }) => (
                        <>
                            <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        name="otp"
                                        maxLength={OTP_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.otp} />
                                <p className="text-xs text-muted-foreground">
                                    Enter the 6-digit code from your email. It
                                    expires in 10 minutes.
                                </p>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={
                                    processing || code.length !== OTP_LENGTH
                                }
                            >
                                {processing && <Spinner />}
                                Continue
                            </Button>
                        </>
                    )}
                </Form>

                <ResendForm />
            </div>
        </AuthLayout>
    );
}

function ResendForm() {
    const [resending, setResending] = useState(false);

    const handleResend = () => {
        setResending(true);
        router.post(resend.url(), {}, {
            onFinish: () => setResending(false),
        });
    };

    return (
        <div className="text-center text-sm text-muted-foreground">
            <span>Didn&apos;t receive the code? </span>
            <button
                type="button"
                onClick={handleResend}
                disabled={resending}
                className="text-primary hover:underline disabled:opacity-50"
            >
                {resending ? (
                    <>
                        <Spinner className="mr-1 inline h-3 w-3" />
                        Sending…
                    </>
                ) : (
                    'Resend code'
                )}
            </button>
        </div>
    );
}
