import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { MessageSquare, ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Support Tickets', href: '/tickets' },
    { title: 'Ticket Details', href: '#' },
];

const statusColors: Record<string, string> = {
    open: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    closed: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
};

const priorityColors: Record<string, string> = {
    low: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    medium: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    high: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
};

export default function TicketsShow({ ticket: initialTicket }: any) {
    // Use state to manage ticket and messages for real-time updates
    const [ticket, setTicket] = useState(initialTicket);
    const [messages, setMessages] = useState(initialTicket.messages || []);

    // Sync state when props change
    useEffect(() => {
        setTicket(initialTicket);
        setMessages(initialTicket.messages || []);
    }, [initialTicket.id, initialTicket.updated_at]);

    const { data, setData, post, processing, errors, reset } = useForm({
        message: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/tickets/${ticket.id}/reply`, {
            onSuccess: () => {
                reset();
                // Message will be added via WebSocket, no need to reload
            },
        });
    };

    // Real-time updates via WebSockets (Pusher)
    useEffect(() => {
        if (ticket.status === 'closed') {
            return;
        }

        const initWebSocket = () => {
            try {
                if (!window.Echo) {
                    return;
                }
                const echo = window.Echo;

                const handleMessageCreated = (data: any) => {
                    if (data.message) {
                        setMessages((prev: any) => {
                            // Check if message already exists to avoid duplicates
                            if (prev.find((m: any) => m.id === data.message.id)) {
                                return prev;
                            }
                            return [...prev, data.message];
                        });
                    }
                };

                const handleTicketUpdated = (data: any) => {
                    setTicket((prev: any) => ({
                        ...prev,
                        status: data.status || prev.status,
                    }));
                };

                // Listen to public tickets channel for this specific ticket
                echo.channel('tickets')
                    .listen('.ticket.updated', (data: any) => {
                        if (data.ticket_id === ticket.id) {
                            handleTicketUpdated(data);
                        }
                    })
                    .listen('.ticket.message.created', (data: any) => {
                        if (data.ticket_id === ticket.id) {
                            handleMessageCreated(data);
                        }
                    });

                // Also listen to private ticket channel
                echo.private(`ticket.${ticket.id}`)
                    .listen('.ticket.updated', handleTicketUpdated)
                    .listen('.ticket.message.created', handleMessageCreated);
            } catch (e) {
                console.error('Failed to initialize WebSocket:', e);
            }
        };

        initWebSocket();

        return () => {
            if (window.Echo) {
                try {
                    window.Echo.leave('tickets');
                    window.Echo.leaveChannel(`private-ticket.${ticket.id}`);
                } catch (e) {
                    console.error('WebSocket: Error cleaning up channels', e);
                }
            }
        };
    }, [ticket.id, ticket.status]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket: ${ticket.subject}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Link href="/tickets">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Tickets
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <CardTitle className="mb-2">{ticket.subject}</CardTitle>
                                <div className="flex gap-2 flex-wrap">
                                    <Badge className={statusColors[ticket.status]}>
                                        {ticket.status.replace('_', ' ')}
                                    </Badge>
                                    <Badge className={priorityColors[ticket.priority]}>
                                        {ticket.priority}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <p className="text-sm font-medium mb-1">Description</p>
                                <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                    {ticket.description}
                                </p>
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Created by {ticket.user?.name} on{' '}
                                {new Date(ticket.created_at).toLocaleString()}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Messages</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {messages && messages.length > 0 ? (
                                messages.map((message: any) => (
                                    <div
                                        key={message.id}
                                        className={`p-4 rounded-lg ${
                                            message.is_admin
                                                ? 'bg-blue-50 dark:bg-blue-900/20'
                                                : 'bg-gray-50 dark:bg-gray-900/20'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-sm font-medium">
                                                {message.user?.name}
                                                {message.is_admin && (
                                                    <Badge className="ml-2" variant="outline">
                                                        Admin
                                                    </Badge>
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {new Date(message.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                        <p className="text-sm whitespace-pre-wrap">
                                            {message.message}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No messages yet.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {ticket.status !== 'closed' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Add Reply</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <Label htmlFor="message">Message</Label>
                                    <Textarea
                                        id="message"
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        required
                                        rows={4}
                                        placeholder="Type your reply..."
                                    />
                                    <InputError message={errors.message} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Sending...' : 'Send Reply'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
