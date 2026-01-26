import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';

interface ReportGeneration {
    id: number;
    status: string;
    report_type: string;
    format: string;
    download_url: string;
    sse_url: string;
}

interface Props {
    reportGeneration: ReportGeneration;
}

export default function DownloadWait({ reportGeneration }: Props) {
    const [status, setStatus] = useState(reportGeneration.status);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [downloadUrl, setDownloadUrl] = useState<string | null>(null);

    useEffect(() => {
        // Set up Server-Sent Events connection
        const eventSource = new EventSource(reportGeneration.sse_url);
        let heartbeatTimeout: NodeJS.Timeout | null = null;
        let lastHeartbeat = Date.now();

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
                    setDownloadUrl(data.download_url);
                    // Auto-trigger download
                    if (data.download_url) {
                        window.location.href = data.download_url;
                    }
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
            if (timeSinceLastHeartbeat > 90000 && (status === 'pending' || status === 'processing')) {
                // No heartbeat for 90 seconds, connection likely dead
                console.warn('SSE heartbeat timeout detected');
                setErrorMessage('Connection lost. Please refresh the page.');
                eventSource.close();
            }
        };

        // Check heartbeat every 30 seconds
        heartbeatTimeout = setInterval(checkHeartbeat, 30000);

        // Cleanup on unmount
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
                return 'Your report is queued for generation...';
            case 'processing':
                return 'Your report is being generated...';
            case 'completed':
                return 'Report ready! Download starting...';
            case 'failed':
                return 'Report generation failed';
            default:
                return 'Preparing your report...';
        }
    };

    const reportTypeName = reportGeneration.report_type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

    return (
        <AppLayout>
            <Head title="Generating Report" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-6 p-8">
                <div className="flex flex-col items-center gap-4 text-center">
                    <Loader2 className="h-12 w-12 animate-spin text-primary" />
                    <div>
                        <h1 className="text-2xl font-bold">Generating Report</h1>
                        <p className="mt-2 text-muted-foreground">
                            {reportTypeName} ({reportGeneration.format.toUpperCase()})
                        </p>
                    </div>
                    <div className="mt-4">
                        <p className="text-lg">{getStatusMessage()}</p>
                        {errorMessage && (
                            <p className="mt-2 text-sm text-destructive">{errorMessage}</p>
                        )}
                    </div>
                    {status === 'completed' && downloadUrl && (
                        <div className="mt-4">
                            <a
                                href={downloadUrl}
                                className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                Download Report
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
