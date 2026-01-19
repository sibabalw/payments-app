import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router } from '@inertiajs/react';
import { Clock, LogIn, LogOut } from 'lucide-react';
import { useState, useEffect } from 'react';

interface TimeTrackingProps {
    employee: {
        id: number;
        name: string;
        email: string;
    };
    isSignedIn: boolean;
    signInTime?: string;
    hoursWorked: number;
    today: string;
    status?: string;
    error?: string;
}

export default function EmployeeTimeTracking({
    employee,
    isSignedIn,
    signInTime,
    hoursWorked,
    today,
    status,
    error,
}: TimeTrackingProps) {
    const [processing, setProcessing] = useState(false);
    const [currentDuration, setCurrentDuration] = useState(hoursWorked);

    // Update duration in real-time when signed in
    useEffect(() => {
        // Update state when props change
        if (isSignedIn && signInTime) {
            setCurrentDuration(hoursWorked);
        } else {
            setCurrentDuration(0);
        }

        if (!isSignedIn || !signInTime) {
            return;
        }

        // Calculate initial duration
        const calculateDuration = () => {
            try {
                // Parse the sign-in time with today's date
                const [hours, minutes, seconds] = signInTime.split(':');
                const signInDate = new Date(today);
                signInDate.setHours(parseInt(hours, 10), parseInt(minutes, 10), parseInt(seconds || '0', 10), 0);
                
                const now = new Date();
                const diffMs = now.getTime() - signInDate.getTime();
                
                // Only update if the difference is positive (sign-in time is in the past)
                if (diffMs > 0) {
                    const diffHours = diffMs / (1000 * 60 * 60);
                    setCurrentDuration(Math.max(0, diffHours));
                }
            } catch (error) {
                console.error('Error calculating duration:', error);
            }
        };

        // Calculate immediately
        calculateDuration();

        // Update every 10 seconds for better UX
        const interval = setInterval(calculateDuration, 10000);

        return () => clearInterval(interval);
    }, [isSignedIn, signInTime, today, hoursWorked]);

    const handleSignIn = () => {
        setProcessing(true);
        router.post('/employee/time-tracking/sign-in', {}, {
            preserveScroll: false,
            onSuccess: () => {
                setProcessing(false);
                // Data will be refreshed automatically by Inertia
            },
            onError: () => {
                setProcessing(false);
                // Errors will be shown via status/error props
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const handleSignOut = () => {
        setProcessing(true);
        router.post('/employee/time-tracking/sign-out', {}, {
            preserveScroll: false,
            onSuccess: () => {
                setProcessing(false);
                // Data will be refreshed automatically by Inertia
            },
            onError: () => {
                setProcessing(false);
                // Errors will be shown via status/error props
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    const formatTime = (timeString?: string) => {
        if (!timeString) return '';
        const [hours, minutes, seconds] = timeString.split(':');
        return `${hours}:${minutes}`;
    };

    const formatHours = (hours: number) => {
        const h = Math.floor(hours);
        const m = Math.floor((hours - h) * 60);
        return `${h}h ${m}m`;
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-ZA', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    return (
        <AuthLayout
            title="Time Tracking"
            description="Sign in and out for your work hours"
        >
            <Head title="Time Tracking" />

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

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Welcome, {employee.name}</CardTitle>
                        <p className="text-sm text-muted-foreground">{employee.email}</p>
                        <p className="text-sm text-muted-foreground">{formatDate(today)}</p>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="flex items-center gap-3">
                                {isSignedIn ? (
                                    <>
                                        <LogIn className="h-5 w-5 text-green-600" />
                                        <div>
                                            <p className="font-medium text-green-600">Signed In</p>
                                            {signInTime && (
                                                <p className="text-sm text-muted-foreground">
                                                    Since {formatTime(signInTime)}
                                                </p>
                                            )}
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <LogOut className="h-5 w-5 text-gray-400" />
                                        <div>
                                            <p className="font-medium text-muted-foreground">Signed Out</p>
                                            <p className="text-sm text-muted-foreground">
                                                Not currently signed in
                                            </p>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>

                        {isSignedIn && signInTime && (
                            <div className="flex items-center gap-2 rounded-lg border p-4 bg-primary/5">
                                <Clock className="h-5 w-5 text-primary" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-muted-foreground">Time Signed In</p>
                                    <p className="text-2xl font-bold text-primary">
                                        {currentDuration > 0 ? formatHours(currentDuration) : '0h 0m'}
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Started at {formatTime(signInTime)}
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="pt-4">
                            {isSignedIn ? (
                                <Button
                                    onClick={handleSignOut}
                                    variant="destructive"
                                    className="w-full"
                                    size="lg"
                                    disabled={processing}
                                >
                                    {processing && <Spinner className="mr-2 h-5 w-5" />}
                                    <LogOut className="mr-2 h-5 w-5" />
                                    Sign Out
                                </Button>
                            ) : (
                                <Button
                                    onClick={handleSignIn}
                                    className="w-full"
                                    size="lg"
                                    disabled={processing}
                                >
                                    {processing && <Spinner className="mr-2 h-5 w-5" />}
                                    <LogIn className="mr-2 h-5 w-5" />
                                    Sign In
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="text-center text-xs text-muted-foreground">
                    <p>Your session will expire in 24 hours.</p>
                    <button
                        type="button"
                        onClick={() => {
                            router.post('/employee/sign-out-session');
                        }}
                        className="mt-2 text-primary hover:underline"
                    >
                        Sign out and return to login
                    </button>
                </div>
            </div>
        </AuthLayout>
    );
}
