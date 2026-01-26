import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Loader2, Mail, CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ReportGeneration {
    id: number;
    status: string;
    report_type: string;
    format: string;
    sse_url: string;
}

interface Props {
    reportGeneration: ReportGeneration;
}

const formatLabels: Record<string, string> = {
    csv: 'CSV',
    excel: 'Excel',
    pdf: 'PDF',
};

const reportTypeLabels: Record<string, string> = {
    payroll_summary: 'Payroll Summary',
    payroll_by_employee: 'Payroll by Employee',
    tax_summary: 'Tax Summary',
    deductions_summary: 'Deductions Summary',
    payment_summary: 'Payment Summary',
    employee_earnings: 'Employee Earnings',
};

export default function EmailWait({ reportGeneration }: Props) {
    const [status, setStatus] = useState(reportGeneration.status);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    let heartbeatTimeout: NodeJS.Timeout | null = null;
    let lastHeartbeat = Date.now();

    useEffect(() => {
        // Set up Server-Sent Events connection
        const eventSource = new EventSource(reportGeneration.sse_url);

        // Handle connection established
        eventSource.addEventListener('connected', () => {
            console.log('SSE connection established');
            lastHeartbeat = Date.now();
        });

        // Handle heartbeat events
        eventSource.addEventListener('heartbeat', (event) => {
            try {
                const data = JSON.parse(event.data);
                lastHeartbeat = Date.now();
                console.debug('SSE heartbeat received', data.timestamp);
            } catch (e) {
                console.error('Error parsing heartbeat:', e);
            }
        });

        // Handle close event
        eventSource.addEventListener('close', () => {
            console.log('SSE connection closed by server');
            eventSource.close();
        });

        // Handle regular messages
        eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                lastHeartbeat = Date.now();

                setStatus(data.status);

                if (data.status === 'completed') {
                    eventSource.close();
                } else if (data.status === 'failed') {
                    setErrorMessage(data.error_message || 'Report generation failed');
                    eventSource.close();
                } else if (data.status === 'timeout') {
                    setErrorMessage('Report generation timed out. Please try again.');
                    eventSource.close();
                }
            } catch (e) {
                console.error('Error parsing SSE data:', e);
            }
        };

        // Monitor for connection issues
        eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);

            // Check if we haven't received a heartbeat in 60 seconds
            const timeSinceLastHeartbeat = Date.now() - lastHeartbeat;
            if (timeSinceLastHeartbeat > 60000) {
                setErrorMessage('Connection lost. Please refresh the page.');
                eventSource.close();
            } else {
                // Connection might be temporarily interrupted, wait a bit
                setTimeout(() => {
                    if (eventSource.readyState === EventSource.CLOSED) {
                        // If still closed after wait, reload
                        if (status === 'pending' || status === 'processing') {
                            window.location.reload();
                        }
                    }
                }, 5000);
            }
        };

        // Heartbeat monitoring - detect if server stops sending heartbeats
        const checkHeartbeat = () => {
            const timeSinceLastHeartbeat = Date.now() - lastHeartbeat;
            if (timeSinceLastHeartbeat > 60000 && (status === 'pending' || status === 'processing')) {
                setErrorMessage('Connection timeout. Please refresh the page.');
                eventSource.close();
            }
        };

        heartbeatTimeout = setInterval(checkHeartbeat, 10000);

        return () => {
            if (heartbeatTimeout) {
                clearInterval(heartbeatTimeout);
            }
            eventSource.close();
        };
    }, [reportGeneration.sse_url, status]);

    const getStatusMessage = () => {
        switch (status) {
            case 'pending':
                return 'Queuing report generation...';
            case 'processing':
                return 'Generating your report...';
            case 'completed':
                return 'Report generated successfully! Check your email for the download link.';
            case 'failed':
                return errorMessage || 'Report generation failed.';
            default:
                return 'Processing...';
        }
    };

    const getStatusIcon = () => {
        switch (status) {
            case 'pending':
            case 'processing':
                return <Loader2 className="h-8 w-8 animate-spin text-primary" />;
            case 'completed':
                return <CheckCircle2 className="h-8 w-8 text-green-600" />;
            case 'failed':
                return <XCircle className="h-8 w-8 text-red-600" />;
            default:
                return <Loader2 className="h-8 w-8 animate-spin text-primary" />;
        }
    };

    return (
        <AppLayout>
            <Head title="Generating Report" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Card className="mx-auto max-w-2xl">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Mail className="h-5 w-5" />
                            Sending Report via Email
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-6 py-12">
                        <div className="flex flex-col items-center gap-4">
                            {getStatusIcon()}
                            <div className="text-center">
                                <p className="text-lg font-medium">{getStatusMessage()}</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {reportTypeLabels[reportGeneration.report_type] || reportGeneration.report_type} -{' '}
                                    {formatLabels[reportGeneration.format] || reportGeneration.format.toUpperCase()}
                                </p>
                            </div>
                        </div>

                        {(status === 'pending' || status === 'processing') && (
                            <div className="w-full max-w-md">
                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all duration-300"
                                        style={{
                                            width: status === 'pending' ? '30%' : status === 'processing' ? '70%' : '100%',
                                        }}
                                    />
                                </div>
                            </div>
                        )}

                        {status === 'completed' && (
                            <div className="flex flex-col gap-4">
                                <p className="text-center text-sm text-muted-foreground">
                                    Your report has been generated and sent to your email address. Please check your inbox.
                                </p>
                                <Button onClick={() => router.visit('/reports')} className="w-full">
                                    Back to Reports
                                </Button>
                            </div>
                        )}

                        {status === 'failed' && (
                            <div className="flex flex-col gap-4">
                                <p className="text-center text-sm text-red-600">{errorMessage}</p>
                                <div className="flex gap-2">
                                    <Button variant="outline" onClick={() => router.visit('/reports')} className="flex-1">
                                        Back to Reports
                                    </Button>
                                    <Button onClick={() => window.location.reload()} className="flex-1">
                                        Try Again
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
